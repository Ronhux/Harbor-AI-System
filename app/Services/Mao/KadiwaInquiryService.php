<?php

namespace App\Services\Mao;

use App\Models\KadiwaInquiry;
use App\Models\Producer;
use App\Models\User;
use App\Policies\KadiwaWorkflowPolicy;
use App\Policies\MaoIdentityPolicy;
use App\Support\WorkflowTimestamp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

/**
 * Assisted KADIWA coordination inquiries.
 *
 * An inquiry is a question to the municipal agricultural office about selling opportunities.
 * It is NOT an event application, and no response to an inquiry ever approves participation,
 * reserves capacity or creates a purchase, award or payment.
 */
final class KadiwaInquiryService
{
    public function __construct(private KadiwaWorkflowPolicy $policy) {}

    public function ready(): void
    {
        $this->policy->ready();
    }

    /** An authenticated producer's own inquiries. Never another account's, never an administrator's. */
    public function forOwner(User $actor, int $page): array
    {
        $this->ready();
        $query = KadiwaInquiry::where('owner_id', $actor->getKey());
        $this->assertCanAsk($actor);

        return $this->page($query, $page);
    }

    /** The inquiry-review queue is limited to explicitly assigned MAO reviewers. */
    public function forReviewer(User $actor, int $page): array
    {
        $this->ready();
        abort_unless($this->policy->inquiryReviewer($actor), 403, 'KADIWA inquiry-review assignment is required.');

        return $this->page(KadiwaInquiry::query(), $page);
    }

    public function store(User $actor, array $input): KadiwaInquiry
    {
        $this->ready();
        $this->assertCanAsk($actor);
        Validator::make($input, [
            'locality' => 'required|string|max:160',
            'message' => 'required|string|min:10|max:2000',
            'submission_key' => 'required|uuid',
            'status' => 'prohibited', 'response' => 'prohibited', 'owner_id' => 'prohibited',
        ])->validate();

        $hash = $this->payloadHash((int) $actor->getKey(), $input['locality'], $input['message']);

        return PreparedWorkflowTransaction::run(function () use ($actor, $input, $hash) {
            MaoLockPlan::acquire(['actor' => $actor->getKey()]);
            $current = User::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $this->assertCanAsk($current);

            // An identical unanswered question is returned unchanged instead of duplicated.
            $replay = KadiwaInquiry::where('owner_id', $current->getKey())
                ->where('payload_hash', $hash)->where('status', 'pending')
                ->lockForUpdate()->first();
            if ($replay) return $replay;

            $inquiry = new KadiwaInquiry();
            $this->persist($inquiry, [
                'locality' => trim((string) $input['locality']),
                'message' => trim((string) $input['message']),
                'status' => 'pending',
                'owner_id' => $current->getKey(),
                'reviewer_id' => null,
                'payload_hash' => $hash,
                'row_version' => 0,
            ]);

            return $inquiry->refresh();
        });
    }

    /**
     * Records an MAO response. The outcome is always informational: 'answered' or 'closed',
     * and participation approval remains a separate, separately reviewed decision.
     */
    public function answer(User $actor, int $id, array $input): KadiwaInquiry
    {
        $this->ready();
        abort_unless($this->policy->inquiryReviewer($actor), 403, 'KADIWA inquiry-review assignment is required.');
        Validator::make($input, [
            'response' => 'required|string|min:10|max:2000',
            'status' => 'required|in:answered,closed',
            'version' => 'required|integer|min:0',
            'operation_key' => 'required|uuid',
            'participation_approved' => 'prohibited', 'owner_id' => 'prohibited',
            'locality' => 'prohibited', 'message' => 'prohibited',
        ])->validate();

        return PreparedWorkflowTransaction::run(function () use ($actor, $id, $input) {
            MaoLockPlan::acquire(['actor' => $actor->getKey(), 'users' => [$this->ownerOf($id)]]);
            $current = User::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($this->policy->inquiryReviewer($current), 403);
            $inquiry = KadiwaInquiry::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if((int) $inquiry->status === 'closed', 409, 'This inquiry is already closed.');
            abort_unless((int) $inquiry->row_version === (int) $input['version'], 409,
                'Inquiry changed; review its current state.');
            $owner = User::whereKey($inquiry->owner_id)->lockForUpdate()->firstOrFail();
            // The MAO answering cannot be the producer who asked.
            abort_if((int) $owner->getKey() === (int) $current->getKey(), 403, 'Self-review is not allowed.');

            $this->persist($inquiry, [
                'response' => trim((string) $input['response']),
                'status' => (string) $input['status'],
                'reviewer_id' => $current->getKey(),
                'row_version' => (int) $inquiry->row_version + 1,
            ]);

            return $inquiry->refresh();
        });
    }

    public function present(KadiwaInquiry $inquiry): array
    {
        return [
            'id' => (int) $inquiry->getKey(),
            'locality' => (string) $inquiry->locality,
            'message' => (string) $inquiry->message,
            'response' => $inquiry->response === null ? null : (string) $inquiry->response,
            'status' => (string) $inquiry->status,
            'row_version' => (int) $inquiry->row_version,
            'created_at' => WorkflowTimestamp::format($inquiry->created_at),
            'participation_approved' => false,
        ];
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /** Any approved producer account may ask about opportunities outside their own locality. */
    private function assertCanAsk(User $actor): void
    {
        $producer = Producer::where('user_id', $actor->getKey())->first();
        abort_unless($producer && app(MaoIdentityPolicy::class)->producer($actor, $producer), 403,
            'An approved producer account is required.');
    }

    private function page(\Illuminate\Database\Eloquent\Builder $query, int $page): array
    {
        $perPage = (int) config('kadiwa.limits.inquiry_page', 20);
        $paginator = $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', max(1, $page));

        return [
            'data' => $paginator->getCollection()->map(fn (KadiwaInquiry $q) => $this->present($q))->all(),
            'current_page' => (int) $paginator->currentPage(),
            'last_page' => (int) $paginator->lastPage(),
            'total' => (int) $paginator->total(),
        ];
    }

    private function ownerOf(int $id): int
    {
        $owner = KadiwaInquiry::whereKey($id)->value('owner_id');
        abort_unless($owner, 404);

        return (int) $owner;
    }

    private function payloadHash(int $ownerId, string $locality, string $message): string
    {
        return hash('sha256', $ownerId . '|' . mb_strtolower(trim($locality)) . '|' . trim($message));
    }

    private function persist(Model $record, array $attributes): void
    {
        abort_unless($record->forceFill($attributes)->save(), 503, 'KADIWA inquiry could not be persisted.');
    }
}
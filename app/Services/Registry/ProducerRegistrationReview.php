<?php
namespace App\Services\Registry;

use App\Models\{Producer, User, TransactionNotification};
use Illuminate\Support\Facades\DB;

/** Registration decisions reuse the existing audit and durable database inbox. */
class ProducerRegistrationReview
{
    public function decide(User $actor, int $producerId, string $decision, ?string $reason): Producer
    {
        abort_unless(in_array($decision, ['Verified', 'Rejected'], true), 422);
        abort_if($decision === 'Rejected' && (mb_strlen(trim($reason ?? '')) < 10 || mb_strlen($reason ?? '') > 2000 || !preg_match('/[\pL\pN]/u', $reason ?? '')), 422, 'A meaningful applicant-facing reason is required.');
        return DB::transaction(function () use ($actor, $producerId, $decision, $reason) {
            $admin = User::whereKey($actor->user_id)->lockForUpdate()->firstOrFail();
            abort_unless($admin->user_type === 'Admin' && $admin->is_active !== false, 403);
            $reference = Producer::findOrFail($producerId);
            abort_if((int) $reference->user_id === (int) $admin->user_id, 403);
            $owner = User::whereKey($reference->user_id)->lockForUpdate()->firstOrFail();
            $producer = Producer::whereKey($producerId)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($owner->user_type, ['Farmer', 'Fisherfolk', 'Producer'], true), 404);
            if ($producer->verification_status === $decision) return $producer->load('user');
            abort_unless(strtolower((string) $producer->verification_status) === 'pending', 409, 'This registration has already been decided. Refresh the review queue.');
            if ($decision === 'Verified') {
                $match = app(ProducerRegistryMatcher::class)->match($producer->rsbsa_number, trim($owner->first_name . ' ' . $owner->last_name), true);
                abort_unless($match['valid_match'] ?? false, 422, 'A valid RSBSA registry identity match is required before approval.');
                abort_if($producer->registered_producer_id && (int) $producer->registered_producer_id !== (int) $match['registry_id'], 422, 'The linked registry identity must be reconciled before approval.');
            }
            $producer->verification_status = $decision;
            $producer->saveOrFail();
            DB::table('admin_activity_logs')->insert([
                'user_id' => $admin->user_id,
                'action' => $decision === 'Verified' ? 'Approved producer verification' : 'Rejected producer verification',
                'subject' => 'producer_id=' . $producer->producer_id,
                'status' => 'success',
                'details' => json_encode(['producer_id' => $producer->producer_id, 'previous_status' => 'Pending', 'new_status' => $decision, 'applicant_reason' => $decision === 'Rejected' ? $reason : null], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
            TransactionNotification::create([
                'recipient_user_id' => $owner->user_id,
                'title' => $decision === 'Verified' ? 'Producer registration approved' : 'Producer registration not approved',
                'body' => $decision === 'Verified' ? 'Your registration has been approved. You may now sign in to your Producer account.' : 'Your Producer registration was not approved. Reason: ' . $reason . ' Please contact the Municipal Agriculture Office for clarification.',
            ]);
            // Old pending credentials cannot retain protected API access after rejection.
            if ($decision === 'Rejected') $owner->tokens()->delete();
            return $producer->load('user');
        });
    }

    /** Never return general audit details/internal notes to an applicant. */
    public function applicantReason(Producer $producer): ?string
    {
        if ($producer->verification_status !== 'Rejected') return null;
        $log = DB::table('admin_activity_logs')->where('subject', 'producer_id=' . $producer->producer_id)
            ->where('action', 'Rejected producer verification')->where('status', 'success')->orderByDesc('id')->first();
        $details = $log ? json_decode($log->details ?? '{}', true) : null;
        return is_array($details) && is_string($details['applicant_reason'] ?? null) ? $details['applicant_reason'] : null;
    }
}

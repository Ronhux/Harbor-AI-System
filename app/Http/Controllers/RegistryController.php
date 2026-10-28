<?php

namespace App\Http\Controllers;

use App\Models\RegisteredProducer;
use App\Models\Producer;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\Registry\ProducerRegistryMatcher;

class RegistryController extends Controller
{
    private const PRODUCER_USER_TYPES = ['Farmer', 'Fisherfolk', 'Producer'];

    public function index()
    {
        $registries = $this->attachProducerAccounts(
            RegisteredProducer::query()->paginate(20)
        );

        return response()->json($registries);
    }

    public function verifyProducerRecord(Request $request)
    {
        $request->merge(['rsbsa_number' => trim((string) $request->input('rsbsa_number', ''))]);
        $validator = Validator::make($request->all(), [
            'rsbsa_number' => ['required', 'string', 'max:19', 'regex:/^(?:\d{15}|\d{2}-\d{2}-\d{2}-\d{3}-\d{6})$/'],
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'extension_name' => 'nullable|string|max:50',
            'suffix' => 'nullable|string|max:50',
            'sex' => 'required|string|max:20',
            'barangay' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $result = app(ProducerRegistryMatcher::class)->verifyExactMatch($request->all());

        if (!($result['valid_match'] ?? false)) {
            $status = $result['status'] ?? 'REGISTRY_INFORMATION_DOES_NOT_MATCH';

            if ($status === 'NO_REGISTRY_MATCH') {
                return response()->json([
                    'message' => 'Registry record not found. Please check your RSBSA Number and try again.',
                    'status' => 'registry_not_found',
                    'registry_match' => false,
                ], 404);
            }

            return response()->json([
                'message' => 'The information provided does not match the registry record associated with this RSBSA Number. Please review your details.',
                'status' => 'registry_mismatch',
                'registry_match' => false,
            ], 422);
        }

        $registry = RegisteredProducer::findOrFail($result['registry_id']);

        return response()->json([
            'message' => 'Registry verified successfully.',
            'status' => 'registry_verified',
            'registry_match' => true,
            'registry' => [
                'rsbsa_number' => $registry->rsbsa_number,
                'full_name' => trim(implode(' ', array_filter([
                    $registry->first_name,
                    $registry->middle_name,
                    $registry->last_name,
                    $registry->extension_name,
                ], fn ($value) => is_string($value) && trim($value) !== ''))),
                'barangay' => $registry->barangay,
                'municipality' => $registry->municipality,
            ],
        ]);
    }

    public function search(Request $request)
    {
        $query = RegisteredProducer::query();

        if ($request->filled('name')) {
            $term = trim((string) $request->name);
            $query->where(function ($nameQuery) use ($term) {
                $nameQuery->where('rsbsa_number', 'like', '%' . $term . '%')
                    ->orWhere('full_name', 'like', '%' . $term . '%')
                    ->orWhere('name', 'like', '%' . $term . '%')
                    ->orWhere('first_name', 'like', '%' . $term . '%')
                    ->orWhere('middle_name', 'like', '%' . $term . '%')
                    ->orWhere('last_name', 'like', '%' . $term . '%')
                    ->orWhere('extension_name', 'like', '%' . $term . '%')
                    ->orWhere('barangay', 'like', '%' . $term . '%')
                    ->orWhere('mobile_number', 'like', '%' . $term . '%')
                    ->orWhere('farm_location', 'like', '%' . $term . '%');
            });
        }

        if ($request->filled('barangay')) {
            $query->where('barangay', 'like', '%' . $request->barangay . '%');
        }

        if ($request->filled('type')) {
            $query->where('farm_type', $request->type);
        }

        $results = $this->attachProducerAccounts(
            $query->paginate(20)
        );

        return response()->json($results);
    }

    private function normalizeRegistryRecord(RegisteredProducer $registry): RegisteredProducer
    {
        $fullNameParts = [];
        foreach (['first_name', 'middle_name', 'last_name', 'extension_name'] as $field) {
            $value = $registry->{$field};
            if (is_string($value) && trim($value) !== '') {
                $fullNameParts[] = trim($value);
            }
        }

        $fullName = trim(implode(' ', $fullNameParts));
        $registry->setAttribute('full_name', $fullName !== '' ? $fullName : trim((string) ($registry->full_name ?: $registry->name ?: '')));

        $addressParts = [];
        foreach (['house_number', 'zone', 'street', 'barangay', 'municipality', 'province', 'region'] as $field) {
            $value = $registry->{$field};
            if (is_string($value) && trim($value) !== '') {
                $addressParts[] = trim($value);
            }
        }
        $registry->setAttribute('address', $addressParts !== [] ? implode(', ', $addressParts) : null);

        $areaValue = $registry->area_ha ?? $registry->farm_size ?? null;
        $registry->setAttribute('area_display', is_numeric((string) $areaValue)
            ? number_format((float) $areaValue, 2, '.', '') . ' ha'
            : null);

        return $registry;
    }

    private function attachProducerAccounts(LengthAwarePaginator $registries): LengthAwarePaginator
    {
        $collection = $registries->getCollection();
        $rsbsaNumbers = $collection->pluck('rsbsa_number')
            ->filter(fn ($number) => trim((string) $number) !== '')
            ->map(fn ($number) => mb_strtolower(trim((string) $number)))
            ->unique()
            ->values();

        if ($rsbsaNumbers->isEmpty()) {
            $registries->setCollection($collection->map(function (RegisteredProducer $registry) {
                return $this->normalizeRegistryRecord($registry->setAttribute('producer_accounts', []));
            }));

            return $registries;
        }

        $matcher = app(ProducerRegistryMatcher::class);
        $producerAccounts = Producer::query()
            ->with('user')
            ->whereIn(DB::raw('LOWER(TRIM(rsbsa_number))'), $rsbsaNumbers)
            ->whereHas('user', fn ($query) => $query->whereIn('user_type', self::PRODUCER_USER_TYPES))
            ->get()
            ->map(function (Producer $producer) use ($matcher): array {
                $name = trim(($producer->user?->first_name ?? '') . ' ' . ($producer->user?->last_name ?? ''));
                $match = $matcher->match($producer->rsbsa_number, $name);

                return [
                    'producer_id' => (int) $producer->producer_id,
                    'user_id' => (int) $producer->user_id,
                    'name' => $name ?: 'Producer',
                    'email' => $producer->user?->email,
                    'contact_number' => $producer->user?->contact_number,
                    'account_status' => $producer->user?->is_active === false ? 'Suspended' : 'Active',
                    'user_type' => $producer->user?->user_type,
                    'producer_type' => $producer->producer_type,
                    'verification_status' => $producer->verification_status,
                    'registry_match' => $match,
                ];
            })
            ->groupBy(fn (array $producer) => mb_strtolower(trim((string) $producer['registry_match']['registry_rsbsa_number'])));

        $registries->setCollection($collection->map(function (RegisteredProducer $registry) use ($producerAccounts) {
            $number = mb_strtolower(trim((string) $registry->rsbsa_number));
            $accounts = $producerAccounts->get($number, collect())
                ->map(function (array $producer) use ($registry): array {
                    $producer['link_status'] = $producer['registry_match']['valid_match']
                        && $producer['registry_match']['registry_id'] === (int) $registry->registry_id
                        ? 'Linked'
                        : 'Needs review';

                    return $producer;
                })
                ->values()
                ->all();

            return $this->normalizeRegistryRecord($registry->setAttribute('producer_accounts', $accounts));
        }));

        return $registries;
    }

    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $handle = null;
        try {
            $handle = fopen($request->file('file')->getRealPath(), 'r');
            if ($handle === false) {
                return response()->json(['message' => 'The registry file could not be read.'], 422);
            }
            $header = fgetcsv($handle);
            if (!is_array($header) || count($header) !== 8) {
                return response()->json(['message' => 'Expected an eight-column registry CSV header.'], 422);
            }
            $rows = [];
            $seen = [];
            $line = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $line++;
                if ($row === [null]) {
                    continue;
                }
                if (count($row) !== 8 || count($rows) >= 10000) {
                    return response()->json(['message' => 'Invalid registry row shape or row limit exceeded.', 'row' => $line], 422);
                }
                $values = array_map(fn ($value) => trim((string) $value), $row);
                $data = array_combine([
                    'rsbsa_number', 'name', 'municipality', 'province',
                    'farm_type', 'farm_size', 'contact_number', 'email',
                ], $values);
                $rowValidator = Validator::make($data, [
                    'rsbsa_number' => 'required|string|max:255',
                    'name' => 'required|string|max:255',
                    'municipality' => 'required|string|max:255',
                    'province' => 'required|string|max:255',
                    'farm_type' => 'required|string|max:255',
                    'farm_size' => 'nullable|string|max:255',
                    'contact_number' => 'nullable|string|max:255',
                    'email' => 'nullable|email|max:255',
                ]);
                if ($rowValidator->fails() || isset($seen[$data['rsbsa_number']])) {
                    // Do not reflect registry values or database exception details.
                    return response()->json(['message' => 'Invalid or duplicate registry row.', 'row' => $line], 422);
                }
                $seen[$data['rsbsa_number']] = true;
                $rows[] = $data;
            }
            if ($rows === []) {
                return response()->json(['message' => 'The registry file contains no records.'], 422);
            }

            DB::transaction(function () use ($rows): void {
                foreach ($rows as $data) {
                    $key = ['rsbsa_number' => $data['rsbsa_number']];
                    unset($data['rsbsa_number']);
                    RegisteredProducer::updateOrCreate($key, $data + ['registration_date' => now()]);
                }
            });
            return response()->json(['message' => 'Registry imported successfully', 'imported_count' => count($rows)], 200);
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::warning('Registry import failed.', ['exception_type' => get_class($exception)]);
            return response()->json(['message' => 'Registry import failed. No changes were saved.'], 500);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    public function update(Request $request, $id)
    {
        $registry = RegisteredProducer::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'rsbsa_number' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'province' => 'required|string|max:255',
            'farm_type' => 'required|string|max:255',
            'farm_size' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $registry->update($request->all());
        return response()->json(['message' => 'Registry updated successfully'], 200);
    }

    public function sync(Request $request)
    {
        // This would typically sync with an external source
        // For now, just return success
        return response()->json(['message' => 'Registry sync completed'], 200);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Producer;
use App\Models\Buyer;
use App\Models\Admin;
use App\Models\RegisteredProducer;
use App\Services\Policies\PolicyAcceptanceService;
use App\Services\Registry\ProducerRegistryMatcher;
use App\Support\PhilippineMobile;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) $token->delete();
        if ($request->hasSession()) {
            \Illuminate\Support\Facades\Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        return response()->json(['message' => 'Logged out.'])->header('Cache-Control', 'no-store, private');
    }

    public function verifyRegistry(Request $request)
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

        $registryMatch = app(ProducerRegistryMatcher::class)->verifyExactMatch($request->all());

        if (!($registryMatch['valid_match'] ?? false)) {
            $status = $registryMatch['status'] ?? 'REGISTRY_INFORMATION_DOES_NOT_MATCH';
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

        $registry = RegisteredProducer::findOrFail($registryMatch['registry_id']);

        return response()->json([
            'message' => 'Registry verified successfully.',
            'status' => 'registry_verified',
            'registry_match' => true,
            'registry' => [
                'rsbsa_number' => $registry->rsbsa_number,
                'full_name' => $this->registryFullName($registry),
                'barangay' => $registry->barangay,
                'municipality' => $registry->municipality,
            ],
        ]);
    }

    public function register(Request $request)
    {
        $emailInput = trim((string) $request->input('email', ''));
        $request->merge([
            'rsbsa_number' => trim((string) $request->input('rsbsa_number', '')),
            'contact_number' => trim((string) $request->input('contact_number', '')),
            'email' => $emailInput === '' ? null : $emailInput,
        ]);

        $acceptanceService = app(PolicyAcceptanceService::class);

        $validator = Validator::make($request->all(), array_merge([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:20',
            'extension_name' => 'nullable|string|max:50',
            'sex' => 'required_if:user_type,producer|string|max:20',
            'barangay' => 'required_if:user_type,producer|string|max:255',
            // Mobile number is required for agricultural producer accounts and
            // stored in canonical 09XXXXXXXXX form; email is optional.
            'contact_number' => [
                'required',
                'string',
                'max:20',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (PhilippineMobile::normalize($value) === null) {
                        $fail('Enter a valid Philippine mobile number, like 09171234567 or +639171234567.');
                    }
                },
            ],
            'email' => 'nullable|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'user_type' => 'required|in:producer,buyer',
            // New public Producer registration is agriculture-only. A self-declared
            // out-of-scope producer type is rejected outright rather than silently
            // relabelled, so an applicant is never stored under a category they did
            // not choose. Legacy Fisherfolk accounts are unaffected: this rule runs
            // only on registration, and login/access still accept historical types.
            'producer_type' => 'required_if:user_type,producer|nullable|in:Farmer',
            'rsbsa_number' => [
                'required_if:user_type,producer',
                'nullable',
                'string',
                'max:19',
                'regex:/^(?:\d{15}|\d{2}-\d{2}-\d{2}-\d{3}-\d{6})$/',
            ],
        ], $acceptanceService->acceptanceRules()), $acceptanceService->acceptanceMessages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $normalizedMobile = PhilippineMobile::normalize($request->input('contact_number'));
        if ($this->mobileAlreadyRegistered($normalizedMobile)) {
            return response()->json([
                'errors' => [
                    'contact_number' => ['An account already uses this mobile number. Use a different number, or sign in with your existing account.'],
                ],
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request, $acceptanceService, $normalizedMobile) {
                $registryMatch = null;
                $producerType = null;
                $registry = null;

                if ($request->user_type === 'producer') {
                    $registryMatch = app(ProducerRegistryMatcher::class)->verifyExactMatch($request->all());
                    if (!($registryMatch['valid_match'] ?? false)) {
                        $status = $registryMatch['status'] ?? 'REGISTRY_INFORMATION_DOES_NOT_MATCH';
                        if ($status === 'NO_REGISTRY_MATCH') {
                            return response()->json([
                                'message' => 'Registry record not found. Please check your RSBSA Number and try again.',
                                'status' => 'registry_not_found',
                            ], 404);
                        }

                        return response()->json([
                            'message' => 'The information provided does not match the registry record associated with this RSBSA Number. Please review your details.',
                            'status' => 'registry_mismatch',
                        ], 422);
                    }

                    $registry = RegisteredProducer::query()
                        ->whereKey($registryMatch['registry_id'])
                        ->lockForUpdate()
                        ->first();
                    if (!$registry) {
                        return response()->json([
                            'message' => 'Registry record not found. Please check your RSBSA Number and try again.',
                            'status' => 'registry_not_found',
                        ], 404);
                    }

                    $registryMatch = app(ProducerRegistryMatcher::class)->verifyExactMatch($request->all());
                    if (!($registryMatch['valid_match'] ?? false)) {
                        return response()->json([
                            'message' => 'The information provided does not match the registry record associated with this RSBSA Number. Please review your details.',
                            'status' => 'registry_mismatch',
                        ], 422);
                    }

                    $rsbsaDigits = str_replace('-', '', $registry->rsbsa_number);
                    $duplicateRegistryLink = Producer::query()
                        ->where('registered_producer_id', $registry->registry_id)
                        ->orWhereRaw("UPPER(REPLACE(REPLACE(TRIM(rsbsa_number), '-', ''), ' ', '')) = ?", [$rsbsaDigits])
                        ->exists();
                    if ($duplicateRegistryLink) {
                        return response()->json([
                            'message' => 'An account is already associated with this registry record.',
                            'status' => 'duplicate_registry_link',
                        ], 409);
                    }

                    $producerType = in_array($request->input('producer_type'), ['Farmer', 'Fisherfolk'], true)
                        ? $request->input('producer_type')
                        : $registryMatch['producer_type'];
                }

                $accountCreatedAtColumn = User::accountCreatedAtColumn();
                $accountCreatedAt = $accountCreatedAtColumn === 'date_joined'
                    ? now()->toDateString()
                    : now();

                $user = User::create([
                    'email' => $request->email,
                    'password_hash' => Hash::make($request->password),
                    'first_name' => $request->user_type === 'producer' ? $registry->first_name : $request->first_name,
                    'last_name' => $request->user_type === 'producer' ? $registry->last_name : $request->last_name,
                    'contact_number' => $normalizedMobile ?? $request->contact_number,
                    $accountCreatedAtColumn => $accountCreatedAt,
                    'user_type' => $request->user_type === 'producer' ? $producerType : 'Buyer',
                    'is_active' => true,
                ]);

                if ($request->user_type === 'producer') {
                    $producer = Producer::create([
                        'user_id' => $user->user_id,
                        'registered_producer_id' => $registry->registry_id,
                        'rsbsa_number' => $registry->rsbsa_number,
                        'location' => $registry->municipality,
                        'primary_product_type' => $registry->primary_livelihood,
                        'verification_status' => 'Pending',
                        'producer_type' => $producerType,
                    ]);
                } elseif ($request->user_type === 'buyer') {
                    Buyer::create([
                        'user_id' => $user->user_id,
                        'organization_name' => $request->first_name . ' ' . $request->last_name,
                        'buyer_type' => 'Institution',
                        'contact_person' => $request->first_name . ' ' . $request->last_name,
                    ]);
                }

                // Durable, versioned policy acceptance records for the new
                // account. Persisted in the same transaction: if acceptance
                // persistence fails, account creation rolls back. No token is
                // issued here; authentication happens only via login.
                $acceptanceService->recordActiveAcceptances($user);

        // A genuinely new producer record is now awaiting verification. Tell the existing
        // administrators; this grants nobody any authority.
        app(\App\Services\Notifications\AdminNotificationService::class)
            ->producerAwaitingVerification($producer);

                $userData = [
                    'user_id' => $user->user_id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'user_type' => $user->user_type,
                    'verification_status' => $request->user_type === 'producer' ? 'Pending' : 'Active',
                    'account_status' => 'Active',
                ];

                if ($request->user_type === 'producer') {
                    $producer = Producer::where('user_id', $user->user_id)->firstOrFail();
                    $userData['producer_id'] = $producer->producer_id;
                    $userData['producer_type'] = $producer->producer_type;
                    $userData['rsbsa_number'] = $producer->rsbsa_number;
                    $userData['registered_producer_id'] = $producer->registered_producer_id;
                }

                return response()->json([
                    'message' => $request->user_type === 'producer'
                        ? 'Producer account created successfully. Your registry information matched; administrator approval is pending.'
                        : 'Registration submitted successfully.',
                    'verification_status' => 'Pending',
                    'token' => null,
                    'user' => $userData,
                    'registry_match' => (bool) ($registryMatch['valid_match'] ?? false),
                    'registry' => $request->user_type === 'producer' ? [
                        'rsbsa_number' => $registry->rsbsa_number,
                        'full_name' => $this->registryFullName($registry),
                        'barangay' => $registry->barangay,
                        'municipality' => $registry->municipality,
                    ] : null,
                ], 201);
            });

        } catch (QueryException $e) {
            $driverMessage = $e->getPrevious()?->getMessage() ?? '';
            $isUniqueViolation = preg_match('/duplicate entry|unique constraint failed/i', $driverMessage) === 1;
            $duplicateEmail = $isUniqueViolation && str_contains(strtolower($driverMessage), 'email');
            $duplicateMobile = $isUniqueViolation && str_contains(strtolower($driverMessage), 'login_number');
            Log::error('Registration failed.', [
                'exception' => $e::class,
                'sql_state' => $e->getCode(),
                'database_error' => $duplicateEmail
                    ? 'Duplicate email constraint.'
                    : ($duplicateMobile ? 'Duplicate canonical mobile number constraint.' : $driverMessage),
            ]);

            if ($duplicateEmail) {
                return response()->json([
                    'message' => 'An account with this email address already exists.',
                ], 409);
            }

            // Reached when a concurrent registration claimed the same mobile
            // number between the pre-check and the insert.
            if ($duplicateMobile) {
                return response()->json([
                    'errors' => [
                        'contact_number' => ['An account already uses this mobile number. Use a different number, or sign in with your existing account.'],
                    ],
                ], 422);
            }

            if (preg_match('/producers_registered_producer_id_unique|registered_producer_id/i', $driverMessage) === 1) {
                return response()->json([
                    'message' => 'An account is already associated with this registry record.',
                    'status' => 'duplicate_registry_link',
                ], 409);
            }

            return response()->json([
                'message' => 'Registration could not be completed. Please try again.',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Registration failed.', ['exception' => $e::class]);
            return response()->json([
                'message' => 'Registration could not be completed. Please try again.',
            ], 500);
        }
    }

    private function registryFullName(RegisteredProducer $registry): string
    {
        return trim(implode(' ', array_filter([
            $registry->first_name,
            $registry->middle_name,
            $registry->last_name,
            $registry->extension_name,
        ], fn ($value) => is_string($value) && trim($value) !== '')));
    }

    /**
     * Duplicate-account check for mobile numbers. Stored values are compared
     * in normalized form so 09XXXXXXXXX, 639XXXXXXXXX, and +639XXXXXXXXX
     * representations of the same number are treated as one number. Accounts
     * with NULL contact numbers are never treated as duplicates of each
     * other (NULL = not comparable), mirroring the NULL-email behavior.
     *
     * This check is a user-friendly pre-check only. It is not the guarantee:
     * `users.login_number` carries a UNIQUE constraint, so a registration that
     * passes this check can still be rejected by the database when it loses a
     * race with a concurrent registration of the same number.
     */
    private function mobileAlreadyRegistered(?string $normalized): bool
    {
        if ($normalized === null) {
            return false;
        }

        if (User::hasLoginNumberColumn()
            && User::query()->where('login_number', $normalized)->exists()) {
            return true;
        }

        return User::query()
            ->whereNotNull('contact_number')
            ->get()
            ->contains(fn ($stored) => PhilippineMobile::normalize($stored->contact_number) === $normalized);
    }

    /**
     * Locate an account by canonical mobile number.
     *
     * The indexed `login_number` column is checked first, so the common case is
     * a single indexed lookup. Legacy rows that predate the canonical column
     * are still found by normalizing the stored contact number in PHP, which
     * keeps previously stored +63/spaced/dashed variants sign-in compatible.
     * A mobile number alone never authenticates a user; the password check
     * always applies afterwards.
     */
    private function findUserByMobile(string $normalized): ?User
    {
        if (User::hasLoginNumberColumn()) {
            $canonical = User::query()->where('login_number', $normalized)->first();
            if ($canonical) {
                return $canonical;
            }
        }

        // Fallback for rows stored before the canonical column existed (and for
        // rows whose stored number is contested, which keep a NULL canonical
        // value on purpose).
        return User::query()
            ->whereNotNull('contact_number')
            ->get()
            ->first(fn (User $user): bool => PhilippineMobile::normalize($user->contact_number) === $normalized);
    }

    public function login(Request $request)
    {
        $identifierInput = $request->input('identifier', $request->input('email'));
        $identifier = is_string($identifierInput) ? trim($identifierInput) : '';

        $validator = Validator::make($request->all(), [
            // Accepts the unified identifier (mobile number or email) as well
            // as the legacy email field used by existing clients/tests.
            'identifier' => ['required_without:email', 'sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rateLimitKey = 'login:' . sha1(strtolower($request->ip()) . '|' . mb_strtolower($identifier));
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            // Generic response: do not reveal whether the account exists.
            return response()->json(['message' => 'Too many login attempts. Please wait a moment and try again.'], 429);
        }

        $user = null;
        if ($identifier !== '') {
            if (str_contains($identifier, '@')) {
                $user = User::where('email', $identifier)->first();
            } else {
                $normalizedMobile = PhilippineMobile::normalize($identifier);
                if ($normalizedMobile !== null) {
                    $user = $this->findUserByMobile($normalizedMobile);
                }
            }
        }

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            RateLimiter::hit($rateLimitKey, 60);

            // Generic invalid-login message to reduce account enumeration.
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        RateLimiter::clear($rateLimitKey);

        if ($user->is_active === false) {
            return response()->json(['message' => 'This account is inactive.'], 403);
        }

        // Serialize token issuance with registration decisions; status is disclosed only
        // after credentials were verified. A status response never creates a session.
        $authentication = DB::transaction(function () use ($user) {
            $lockedUser = User::whereKey($user->user_id)->lockForUpdate()->firstOrFail();
            if ($lockedUser->is_active === false) return response()->json(['message' => 'This account is inactive.'], 403);
            if (in_array($lockedUser->user_type, ['Farmer', 'Fisherfolk', 'Producer'], true)) {
                $producer = Producer::where('user_id', $lockedUser->user_id)->lockForUpdate()->first();
                if (!$producer || $producer->verification_status !== 'Verified') {
                    $rejected = $producer?->verification_status === 'Rejected';
                    return response()->json([
                        'message' => $rejected ? 'Your Producer registration was not approved following administrative review.' : 'Your registration has been submitted and is awaiting administrative approval.',
                        'registration_status' => $rejected ? 'Rejected' : 'Pending',
                        'applicant_reason' => $rejected ? app(\App\Services\Registry\ProducerRegistrationReview::class)->applicantReason($producer) : null,
                    ], 403)->header('Cache-Control', 'no-store, private');
                }
            }
            return $lockedUser->createToken('auth:sanctum')->plainTextToken;
        });
        if ($authentication instanceof \Illuminate\Http\JsonResponse) return $authentication;
        $token = $authentication;
        if ($user->user_type === 'Admin' || $user->user_type === 'Super Admin') {
            DB::table('admin_activity_logs')->insert([
                'user_id' => $user->user_id,
                'action' => 'Admin login',
                'subject' => 'Authentication',
                'status' => 'success',
                'created_at' => now(),
            ]);
        }

        // Get user role details
        $userData = [
            'user_id' => $user->user_id,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'user_type' => $user->user_type,
        ];

        // Get role-specific information
        if (in_array($user->user_type, ['Farmer', 'Fisherfolk', 'Producer'], true)) {
            $producer = Producer::where('user_id', $user->user_id)->firstOrFail();

            $userData['producer_id'] = $producer->producer_id;
            $userData['verification_status'] = $producer->verification_status;
            $userData['rsbsa_number'] = $producer->rsbsa_number;
            $userData['producer_type'] = $producer->producer_type;
        } elseif ($user->user_type === 'Buyer') {
            $buyer = \App\Models\Buyer::where('user_id', $user->user_id)->first();
            if ($buyer) {
                $userData['buyer_id'] = $buyer->buyer_id;
                $userData['buyer_type'] = $buyer->buyer_type;
                $userData['verification_status'] = $buyer->verification_status;
            }
        } elseif ($user->user_type === 'Admin') {
            $admin = Admin::where('user_id', $user->user_id)->first();
            if ($admin) {
                $userData['admin_id'] = $admin->admin_id;
                $userData['role'] = $admin->role;
            }
        }

        if ($user->user_type === 'Super Admin') {
            $userData['role'] = 'Super Admin';
        }

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $userData
        ], 200);
    }
}

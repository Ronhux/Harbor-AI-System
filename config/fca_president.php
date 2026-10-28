<?php
return [
    // Separate source-reviewed activation. Never enabled by an environment variable.
    'workflow_enabled' => env('APP_ENV') === 'local',
    'verification_method' => 'manual',
    'sms_enabled' => false, // Deferred capstone enhancement; environment credentials cannot activate SMS.
    'notice_version' => null, // Approved phone-processing notice required before onboarding.
    'provider' => env('FCA_OTP_PROVIDER', 'semaphore'),
    'semaphore_key' => env('SEMAPHORE_API_KEY'),
    'sender' => env('SEMAPHORE_SENDER_NAME'),
    'lookup_key' => env('FCA_PHONE_LOOKUP_KEY'),
    'verifier_key' => env('FCA_OTP_VERIFIER_KEY'),
    'expiry_seconds' => (int) env('FCA_OTP_EXPIRY_SECONDS', 300),
    'cooldown_seconds' => (int) env('FCA_OTP_COOLDOWN_SECONDS', 60),
    'max_attempts' => (int) env('FCA_OTP_MAX_ATTEMPTS', 5),
    'hourly_send_limit' => (int) env('FCA_OTP_HOURLY_LIMIT', 3),
    'timeout_seconds' => (int) env('FCA_OTP_TIMEOUT_SECONDS', 10),
    'connect_timeout_seconds' => 3,
    'intent_minutes' => 15,
];

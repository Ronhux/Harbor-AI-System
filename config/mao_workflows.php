<?php

return [
    // Preparation only. Do not enable from environment variables or UI flags.
    'fca_applications_enabled' => env('APP_ENV') === 'local',
    'fca_inspection_persistence_enabled' => env('APP_ENV') === 'local',
    'fca_evidence_access_enabled' => env('APP_ENV') === 'local',
    // Explicit workflow assignments; neither administrator role is sufficient alone.
    'fca_reviewer_user_ids' => [],
    // Local demonstration activation for the restricted KADIWA Manager role only.
    // This is NOT an officially authorized MAO deployment and creates no official
    // events, schedules, coordinators or approvals.
    'kadiwa_enabled' => true,
    // user_id 73 = local demonstration KADIWA Manager account. Explicitly assigned.
    // The role is NOT Admin or Super Admin and cannot satisfy the shared administrator
    // policy that governs FCA, demand and institutional-buyer authority.
    'kadiwa_manager_user_ids' => [73],
    'kadiwa_inquiry_reviewer_user_ids' => [], // Deliberately NOT granted to the demo account.
    'enhanced_demands_enabled' => false,
    'demand_publication_enabled' => false, // Unapproved policy; test-only simulation cannot enable production publication.
    'institutional_buyer_user_ids' => [],
    'demand_reviewer_user_ids' => [],
    'sagip_saka_enabled' => false,
];

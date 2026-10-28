<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Active HarborAI Policy Versions
    |--------------------------------------------------------------------------
    |
    | Server-controlled active versions for the public policy documents.
    | Registration and authenticated acceptance submissions must reference
    | exactly these versions; outdated submissions are rejected so users
    | review and freshly acknowledge changed policy content.
    |
    */

    'terms' => [
        'type' => 'terms',
        'title' => 'HarborAI — Terms and Conditions',
        'version' => '2026-10-08',
        'path' => '/terms-and-conditions',
    ],

    'privacy_policy' => [
        'type' => 'privacy_policy',
        'title' => 'HarborAI Privacy Policy',
        'version' => '2026-10-01',
        'path' => '/privacy',
    ],
];

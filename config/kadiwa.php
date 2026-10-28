<?php

/**
 * KADIWA workflow configuration.
 *
 * Nothing here is an official schedule, venue, coordinator, eligibility decision or approval.
 * `projected_schedule` describes a repeating DATE PATTERN only. It is used to render an
 * explicitly unconfirmed projection; individual events always require a separately
 * published and reviewed record before any producer can apply.
 */
return [

    // ---------------------------------------------------------------------
    // Projected regular dates (NOT an authorized schedule)
    // ---------------------------------------------------------------------
    'projected_schedule' => [
        // Third Monday of the month...
        'ordinal' => 3,
        // ...ISO weekday 1 = Monday.
        'iso_weekday' => 1,
        // ...and the following day.
        'following_day_offset' => 1,
        'timezone' => 'Asia/Manila',
        // How many projected dates to render. Bounded so the list cannot imply a calendar year.
        'occurrences' => 6,
        'notice' => 'These are projected recurring dates derived from a repeating pattern only. '
            .'No KADIWA event, venue, hours, capacity, fee, deadline or coordinator is confirmed by this list. '
            .'Each event must be separately published and confirmed before a producer may apply.',
    ],

    // ---------------------------------------------------------------------
    // Participation limits
    // ---------------------------------------------------------------------
    'limits' => [
        'categories' => 30,
        'requirements' => 30,
        'commodities' => 20,
        'queue_page' => 100,
        'inquiry_page' => 20,
        'reason_max' => 1000,
        'notes_max' => 1000,
        'capacity_max' => 10000,
        'detail_rows' => 200,
        // Participation review rate limit, per authenticated account.
        'review_per_minute' => 20,
        'application_per_minute' => 10,
    ],
];
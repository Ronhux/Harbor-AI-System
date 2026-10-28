<?php

return [
    // Institutional policy is not approved. Null means retain / require review.
    'receipt_ttl_hours' => null,
    'upload_tmp_dir' => null, // Must match a separately approved private PHP upload directory.
    'retention_days' => ['active' => null, 'unattached' => null, 'quarantined' => null, 'rejected' => null, 'revoked' => null],
    'max_inspection_attempts' => null,
    'inspection_adapter_id' => null,
];

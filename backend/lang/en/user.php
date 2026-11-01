<?php

return [
    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'pending' => 'Pending approval',
    ],
    'awaiting_approval' => 'Your Supplier account is awaiting approval. You\'ll be able to log in once an admin approves it.',
    // Stored Notifications keep the key and parameters and are translated when read (#80).
    'notification' => [
        'supplier_awaiting_approval' => 'Supplier :name has registered and is awaiting approval.',
        'supplier_approved' => 'Your Supplier account has been approved.',
    ],
];

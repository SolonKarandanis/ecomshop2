<?php

return [
    'status' => [
        'draft' => 'Draft',
        'paid' => 'Paid',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ],
    'payment' => [
        'status' => [
            'pending' => 'Pending',
            'paid' => 'Paid',
            'failed' => 'Failed',
        ],
    ],
    'shipping' => [
        'method' => [
            'none' => 'None',
        ],
    ],
    // Stored Notifications keep the key and parameters and are translated when read (#80).
    'notification' => [
        'created' => 'Your order #:order has been placed successfully.',
        'payment_confirmed' => 'Payment for order #:order has been confirmed.',
        'payment_failed' => 'Payment for order #:order failed. Please check your payment details.',
        'shipped' => 'Your order #:order has been shipped.',
        'delivered' => 'Your order #:order has been delivered.',
        'cancelled' => 'Your order #:order has been cancelled.',
        'ready_for_supplier' => 'You have a new order to fulfill: #:order.',
    ],
];

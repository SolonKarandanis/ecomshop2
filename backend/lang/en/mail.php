<?php

// Mail and Notification emails, sent in the recipient's preferred locale (#80).
return [
    'greeting' => 'Hello :name,',
    'supplier_approved' => [
        'subject' => 'Your Supplier account is approved - EcomShop',
        'line' => 'An admin has approved your Supplier account. You can now log in and start selling.',
        'action' => 'Log in',
    ],
    'order_placed' => [
        'subject' => 'Order Placed - EcomShop',
        'heading' => 'Order Confirmation',
        'intro' => 'Thank you for your order! We\'ve received it and are currently processing it. You\'ll receive another email once your items have shipped.',
        'button' => 'View Order Details',
        'summary' => 'Order Summary',
        'number' => 'Order Number',
        'date' => 'Order Date',
        'payment_status' => 'Payment Status',
        'item' => 'Item',
        'quantity' => 'Quantity',
        'unit_price' => 'Unit Price',
        'total_price' => 'Total Price',
        'subtotal' => 'Subtotal',
        'shipping' => 'Shipping',
        'total' => 'Total',
        'shipping_address' => 'Shipping Address',
        'questions' => 'If you have any questions, please reply to this email or contact our support team.',
    ],
    'thanks' => 'Thanks,',
];

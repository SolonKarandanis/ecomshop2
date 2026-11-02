<?php

return [
    // An unexpected failure (a bug or an outage): the API's generic 500 message.
    'server_error' => 'Something went wrong on our side. Please try again in a moment.',
    'export_orders' => [
        'limit_error' => 'You cannot export more than 10,000 orders at once. Current results: :count',
    ],
    'update_profile' => [
        'email_taken' => 'This email address is already in use by another account.',
    ],
    'change_password' => [
        'wrong_current' => 'The current password you entered is incorrect.',
    ],
    'submit_review' => [
        'not_eligible' => 'You are not eligible to review this product.',
    ],
    'cart_exceptions' => [
        'empty_cart' => 'Cart is empty',
        'supplier_mismatch' => 'Your cart can only contain products from one supplier at a time. Clear your cart to add this product.',
    ],
    'cart_notices' => [
        'saved_cart_replaced' => 'Your previously saved cart was from a different supplier and has been replaced.',
    ],
    'order_exceptions' => [
        'transition_not_allowed' => 'This order cannot be updated from its current status.',
        'supplier_mismatch' => 'An order can only contain products from a single supplier. Remove the items from the other supplier before saving.',
    ],
    'payment_exceptions' => [
        'create_stripe_session' => 'Failed to create Stripe session',
        'retrieve_stripe_session' => 'Failed to retrieve Stripe session',
        'unsupported_payment_method' => 'Unsupported payment method: :method',
    ],
    'product_exceptions' => [
        'not_found' => 'Product with ID :id not found.',
        'ordered_cannot_be_deleted' => 'This product is part of existing orders and cannot be deleted. Deactivate it instead to hide it from the catalogue.',
    ],
    'supplier_products' => [
        'option_not_of_attribute' => 'The selected option does not belong to the selected attribute.',
        'duplicate_attribute_option' => 'This attribute option has already been added.',
        'too_many_images' => 'A product can have at most :max images.',
    ],
];

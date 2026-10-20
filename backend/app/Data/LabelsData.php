<?php

namespace App\Data;

use App\Models\PaymentMethod;

/**
 * A generic id/key/label entry for lookup lists, where `key` is what a client
 * sends back and `label` is its translated name.
 */
class LabelsData extends BaseData
{
    public function __construct(
        public int $id,
        public string $key,
        public string $label,
    ) {}

    public static function fromPaymentMethod(PaymentMethod $paymentMethod): self
    {
        return new self(
            id: $paymentMethod->id,
            key: $paymentMethod->resource_key,
            label: __($paymentMethod->resource_key),
        );
    }
}

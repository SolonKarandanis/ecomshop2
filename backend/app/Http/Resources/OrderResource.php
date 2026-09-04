<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'order_status' => $this->order_status,
            'payment_status' => $this->payment_status,
            'grand_total' => $this->grand_total !== null ? (float) $this->grand_total : null,
            'currency' => $this->currency,
            'shipping_method' => $this->shipping_method,
            'shipping_amount' => $this->shipping_amount !== null ? (float) $this->shipping_amount : null,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            // Buyer-facing responses don't need to know which Supplier fulfills their
            // Order; only the owning Supplier and Admins see it (ADR-0001 API Resources).
            'supplier_id' => $this->when(
                $viewer !== null && ($viewer->isAdmin() || $viewer->id === $this->supplier_id),
                $this->supplier_id
            ),
            'payment_method' => $this->whenLoaded('paymentMethod', fn () => $this->paymentMethod->resource_key),
            'address' => $this->whenLoaded('address', fn () => $this->address ? new AddressResource($this->address) : null),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}

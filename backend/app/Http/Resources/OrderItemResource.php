<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => new ProductResource($this->product)),
            'quantity' => $this->quantity,
            'unit_amount' => $this->unit_amount !== null ? (float) $this->unit_amount : null,
            'total_amount' => $this->total_amount !== null ? (float) $this->total_amount : null,
            'attributes' => $this->attributes,
        ];
    }
}

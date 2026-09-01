<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (float) $this->price,
            'is_featured' => (bool) $this->is_featured,
            'in_stock' => (bool) $this->in_stock,
            'on_sale' => (bool) $this->on_sale,
            'average_rating' => $this->average_rating !== null ? (float) $this->average_rating : null,
            'reviews_count' => $this->reviews_count,
            'thumbnail' => $this->getThumbnailImage(),
            'image' => $this->getLargeImage(),
            'category' => $this->whenLoaded('category', fn () => new CategoryResource($this->category)),
            'brand' => $this->whenLoaded('brand', fn () => new BrandResource($this->brand)),
        ];
    }
}

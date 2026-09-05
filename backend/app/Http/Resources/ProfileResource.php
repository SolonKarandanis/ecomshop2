<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            // Only an Admin's own profile view needs their User Status; a Buyer/Supplier
            // has no in-app use for it (ADR-0001 API Resources).
            'status' => $this->when($request->user()?->isAdmin(), $this->status),
        ];
    }
}

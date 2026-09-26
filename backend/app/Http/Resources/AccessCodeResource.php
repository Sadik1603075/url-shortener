<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AccessCode
 */
class AccessCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'code'         => $this->code,
            'email'        => $this->email,
            'description'  => $this->description,
            'is_active'    => $this->is_active,
            'expires_at'   => $this->expires_at?->toIso8601String(),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at'   => $this->created_at->toIso8601String(),
            'updated_at'   => $this->updated_at->toIso8601String(),
            'user'         => $this->whenLoaded('user', fn () => new UserResource($this->user)),
        ];
    }
}

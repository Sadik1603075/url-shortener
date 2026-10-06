<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ShortUrl
 */
class ShortUrlResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'short_code'       => $this->short_code,
            'short_url'        => config('shortcode.url_base')."/{$this->short_code}",
            'long_url'         => $this->long_url,
            'is_active'        => $this->is_active,
            'click_count'      => $this->click_count,
            'expires_at'       => $this->expires_at?->toIso8601String(),
            'last_accessed_at' => $this->last_accessed_at?->toIso8601String(),
            'created_at'       => $this->created_at->toIso8601String(),
            'updated_at'       => $this->updated_at->toIso8601String(),
            'user'             => $this->whenLoaded('user', fn () => new UserResource($this->user)),
        ];
    }
}

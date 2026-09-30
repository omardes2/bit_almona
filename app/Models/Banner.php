<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasSchedule;
use App\Services\Media\ImageStorage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'description', 'image', 'link_url', 'starts_at', 'ends_at', 'sort_order', 'is_active'])]
class Banner extends Model
{
    use Auditable, HasFactory, HasSchedule;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderByDesc('id');
    }

    public function imageUrl(): ?string
    {
        return ImageStorage::url($this->image);
    }

    public function thumbnailUrl(): ?string
    {
        return ImageStorage::thumbnailUrl($this->image);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\ReviewImageStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Review extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'user_id',
        'order_id',
        'rating',
        'title',
        'body',
        'images',
        'is_approved',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'is_approved' => 'boolean',
            'points_awarded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // A deleted review takes its photos with it: whichever way it is
        // deleted (the admin's Delete is a soft delete), the files leave the
        // disk once the delete has committed, and a soft-deleted row no longer
        // points at files that are gone.
        static::deleted(function (Review $review): void {
            $images = $review->images;

            if (empty($images)) {
                return;
            }

            DB::afterCommit(fn () => app(ReviewImageStorage::class)->delete($images));

            if (! $review->isForceDeleting()) {
                $review->forceFill(['images' => null])->saveQuietly();
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

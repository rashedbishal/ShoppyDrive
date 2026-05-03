<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderSequence extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'prefix',
        'year',
        'last_number',
    ];
}

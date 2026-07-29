<?php

namespace App\Models;

use App\Modules\Parties\Enums\PartyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'type',
    'name',
    'tax_identifier',
    'email',
    'phone',
    'address',
    'is_active',
])]
class Party extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => PartyType::class,
            'is_active' => 'boolean',
        ];
    }
}

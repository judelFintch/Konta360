<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'type', 'is_active'])]
class Account extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function entryLines(): HasMany
    {
        return $this->hasMany(AccountingEntryLine::class);
    }
}

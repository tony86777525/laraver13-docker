<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'short_name',
    ];

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class, 'primary_supplier_id');
    }
}

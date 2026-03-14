<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseLookupValue extends Model
{
    protected $fillable = ['type', 'value', 'label', 'sort_order'];

    public static function ofType(string $type): \Illuminate\Support\Collection
    {
        return static::where('type', $type)->orderBy('sort_order')->get(['value', 'label']);
    }
}

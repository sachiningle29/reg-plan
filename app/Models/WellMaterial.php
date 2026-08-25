<?php

namespace App\Models;

use Database\Factories\WellMaterialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WellMaterial extends Model
{
    /** @use HasFactory<WellMaterialFactory> */
    use HasFactory;

    protected $fillable = [
        'asset_name',
        'well_name',
        'hptp',
        'item_name',
    ];
}

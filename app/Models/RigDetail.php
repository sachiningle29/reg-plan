<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RigDetail extends Model
{
    protected $fillable = [
        'plan_name', 'record_type', 'rig_name', 'asset', 'platform',
        'well_name', 'type', 'meters', 'wi_well', 'days', 'status',
        'activity', 'start_date', 'finish_date', 'oil_gain', 'gas_gain',
        'imported_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'finish_date' => 'date',
    ];
}


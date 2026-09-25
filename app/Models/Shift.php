<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Shift extends Model
{
    use HasUlids;

    protected $fillable = [
        'employee_id',
        'date',
        'start_time',
        'end_time',
        'country_code',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];
}

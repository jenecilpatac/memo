<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = [
        'branch_code',
       'branch'
    ];

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    
}

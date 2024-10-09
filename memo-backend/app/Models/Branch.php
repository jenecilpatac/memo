<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = [
        'branch_code',
        'branch',
        'acronym',
        'branch_name'
    ];

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function user(){
        return $this->hasMany(User::class);
    }

    public function explain(){
        return $this->hasMany(Explain::class);
    }

    public function memo(){
        return $this->hasMany(Memo::class);
    }

    
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Memo extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'to',
        'from',
        're',
        'memo_body',
        'by',
        'approved_by'
    ];

    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvalProcess()
    {
        return $this->hasMany(ApprovalProcess::class);
    }

    
}

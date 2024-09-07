<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExplainApprovalProcess extends Model
{
    use HasFactory;

    protected $fillable = [
        'explain_id',
        'user_id',
        'level',
        'status',
        'comment',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function explain()
    {
        return $this->belongsTo(Explain::class);
    }
}

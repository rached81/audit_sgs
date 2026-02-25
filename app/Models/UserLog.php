<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nom',
        'prenom',
        'matricule',
        'action',
        'method',
        'parameters',
        'ip_address',
    ];

    protected $casts = [
        'parameters' => 'array', // Automatically handle JSON serialization
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

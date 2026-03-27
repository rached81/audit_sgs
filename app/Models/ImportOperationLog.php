<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportOperationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'run_id',
        'table_name',
        'operation',
        'status',
        'user_id',
        'user_matricule',
        'user_name',
        'ip_address',
        'file_path',
        'file_size_bytes',
        'message',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];
}


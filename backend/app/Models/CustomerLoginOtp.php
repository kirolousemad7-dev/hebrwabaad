<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerLoginOtp extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'code_hash',
        'attempts',
        'expires_at',
        'sent_at',
        'consumed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}

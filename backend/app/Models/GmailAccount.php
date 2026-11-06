<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GmailAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'email', 'refresh_token', 'access_token', 'access_token_expires_at', 'scopes', 'needs_reconnect',
        'last_synced_at', 'last_error',
    ];

    protected $hidden = ['refresh_token', 'access_token'];

    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'access_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'needs_reconnect' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class, 'gmail_account', 'email');
    }
}

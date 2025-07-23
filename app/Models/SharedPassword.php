<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedPassword extends Model
{
    protected $fillable = [
        'password_entry_id',
        'shared_by_user_id',
        'shared_with_user_id',
        'permission',
        'expires_at',
        'is_active',
        'last_accessed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'last_accessed_at' => 'datetime',
    ];

    public function passwordEntry(): BelongsTo
    {
        return $this->belongsTo(PasswordEntry::class);
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by_user_id');
    }

    public function sharedWith(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function canWrite(): bool
    {
        return $this->permission === 'write' && $this->is_active && !$this->isExpired();
    }

    public function canRead(): bool
    {
        return $this->is_active && !$this->isExpired();
    }
}

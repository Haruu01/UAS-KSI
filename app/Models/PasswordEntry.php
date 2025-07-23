<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class PasswordEntry extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'username',
        'encrypted_password',
        'url',
        'notes',
        'is_favorite',
        'password_changed_at',
        'expires_at',
        'password_strength',
        'last_accessed_at',
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
        'password_changed_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
    ];

    protected $hidden = [
        'encrypted_password',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function sharedPasswords(): HasMany
    {
        return $this->hasMany(SharedPassword::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'resource_id')
            ->where('resource_type', self::class);
    }

    // Encrypt password before saving with enhanced security
    public function setPasswordAttribute($value)
    {
        $dataProtection = app(\App\Services\DataProtectionService::class);
        $encryptionData = $dataProtection->encryptPassword($value, [
            'title' => $this->title ?? 'Unknown',
            'user_id' => $this->user_id ?? auth()->id(),
        ]);

        $this->attributes['encrypted_password'] = $encryptionData['encrypted_data'];
        $this->attributes['password_changed_at'] = now();
    }

    // Decrypt password when accessing with verification
    public function getPasswordAttribute()
    {
        if (!$this->encrypted_password) {
            return null;
        }

        try {
            $dataProtection = app(\App\Services\DataProtectionService::class);
            return $dataProtection->decryptPassword($this->encrypted_password);
        } catch (\Exception $e) {
            // Log decryption failure
            \App\Models\AuditLog::log(
                'password_decryption_failed',
                $this,
                [],
                ['error' => $e->getMessage()],
                'high',
                'Failed to decrypt password for entry: ' . $this->title
            );
            return null;
        }
    }

    // Update last accessed timestamp
    public function markAsAccessed()
    {
        $this->update(['last_accessed_at' => now()]);
    }
}

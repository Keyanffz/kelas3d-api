<?php

namespace App\Models;

use App\Enums\DeviceOs;
use App\Enums\DeviceStatus;
use Database\Factories\DeviceFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

// Laptop peserta. Jadi pemilik token Sanctum yang dipakai agent.
#[Fillable(['user_id', 'hostname', 'os', 'agent_version', 'status', 'approved_by', 'approved_at', 'last_seen_at'])]
class Device extends Model implements AuthenticatableContract
{
    /** @use HasFactory<DeviceFactory> */
    use Authenticatable, HasApiTokens, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'os' => DeviceOs::class,
            'status' => DeviceStatus::class,
            'approved_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

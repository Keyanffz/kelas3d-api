<?php

namespace App\Models;

use App\Enums\Activity;
use App\Enums\PresenceState;
use App\Enums\StateReason;
use Database\Factories\PresenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(key: 'user_id', incrementing: false)]
#[Fillable(['user_id', 'classroom_id', 'state', 'state_reason', 'activity', 'app_label', 'project', 'file_name', 'language', 'last_heartbeat_at', 'state_since'])]
class Presence extends Model
{
    /** @use HasFactory<PresenceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => PresenceState::class,
            'state_reason' => StateReason::class,
            'activity' => Activity::class,
            'last_heartbeat_at' => 'datetime',
            'state_since' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}

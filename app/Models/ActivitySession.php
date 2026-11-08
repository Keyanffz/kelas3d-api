<?php

namespace App\Models;

use App\Enums\Activity;
use App\Enums\PresenceState;
use Database\Factories\ActivitySessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'classroom_id', 'state', 'activity', 'app_label', 'project', 'language', 'started_at', 'ended_at', 'duration_seconds'])]
class ActivitySession extends Model
{
    /** @use HasFactory<ActivitySessionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => PresenceState::class,
            'activity' => Activity::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
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

<?php

namespace App\Models;

use App\Enums\NudgeStatus;
use Database\Factories\NudgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Teguran koordinator (from) lewat ketua kelas (to) tentang seorang peserta (about).
#[Fillable(['classroom_id', 'from_user_id', 'to_user_id', 'about_user_id', 'message', 'status', 'read_at', 'handled_at'])]
class Nudge extends Model
{
    /** @use HasFactory<NudgeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => NudgeStatus::class,
            'read_at' => 'datetime',
            'handled_at' => 'datetime',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function aboutUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'about_user_id');
    }
}

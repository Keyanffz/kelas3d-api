<?php

namespace App\Models;

use App\Enums\MemberRole;
use App\Enums\ScreenCloseReason;
use App\Enums\ScreenStatus;
use App\Events\ScreenSessionChanged;
use Database\Factories\ScreenSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Koordinator (viewer) melihat layar mahasiswa (target) lewat ketua kelas (approver). Kalau help_request_id
// terisi, mahasiswa sendiri yang membagikan layar ke yang menangani bantuannya, tanpa persetujuan ketua.
// Baris ini sekaligus log-nya; isi layar tidak pernah lewat server.
#[Fillable([
    'classroom_id', 'viewer_id', 'target_id', 'help_request_id', 'approver_id', 'reason', 'status', 'closed_by', 'close_reason',
    'expires_at', 'approved_at', 'consented_at', 'started_at', 'ended_at',
])]
class ScreenSession extends Model
{
    /** @use HasFactory<ScreenSessionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ScreenStatus::class,
            'close_reason' => ScreenCloseReason::class,
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'consented_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }

    public function helpRequest(): BelongsTo
    {
        return $this->belongsTo(HelpRequest::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ScreenStatus::active(), true);
    }

    /**
     * Yang diberi tahu tiap status berubah. Mahasiswa baru ikut sesudah ketua kelas setuju,
     * jadi permintaan yang ditolak ketua tidak pernah sampai ke dia. Share sukarela cuma untuk dua ujungnya.
     *
     * @return list<int>
     */
    public function audience(): array
    {
        return array_values(array_unique(array_filter([
            $this->viewer_id,
            $this->help_request_id ? null : $this->classroom->members()->where('role', MemberRole::Admin)->value('user_id'),
            $this->approved_at ? $this->target_id : null,
        ])));
    }

    // Permintaan yang tidak direspons jadi kedaluwarsa; sesi live yang melewati batas waktu ditutup.
    public static function closeExpired(): void
    {
        static::whereIn('status', ScreenStatus::active())
            ->where('expires_at', '<', now())
            ->get()
            ->each(function (self $session) {
                $live = $session->status === ScreenStatus::Live;
                $session->update([
                    'status' => $live ? ScreenStatus::Selesai : ScreenStatus::Kedaluwarsa,
                    'close_reason' => $live ? ScreenCloseReason::BatasWaktu : null,
                    'ended_at' => now(),
                ]);

                ScreenSessionChanged::dispatch($session);
            });
    }
}

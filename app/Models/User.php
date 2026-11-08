<?php

namespace App\Models;

use App\Enums\MemberRole;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;

#[Fillable(['lms_user_id', 'name', 'email', 'password', 'role', 'avatar'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'avatar' => 'array',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ClassroomMember::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function presence(): HasOne
    {
        return $this->hasOne(Presence::class);
    }

    public function activitySessions(): HasMany
    {
        return $this->hasMany(ActivitySession::class);
    }

    public function isKoordinator(): bool
    {
        return $this->role === UserRole::Koordinator;
    }

    public function membershipIn(Classroom $classroom): ?ClassroomMember
    {
        return $this->memberships->firstWhere('classroom_id', $classroom->id);
    }

    public function isAdminOf(Classroom $classroom): bool
    {
        return $this->membershipIn($classroom)?->role === MemberRole::Admin;
    }

    // Slug kelas yang sedang dikunjungi koordinator; null berarti di ruang koordinator.
    public function visiting(): ?string
    {
        return Cache::get("coordinator_location:{$this->id}");
    }

    public function visit(?string $slug): void
    {
        $slug === null
            ? Cache::forget("coordinator_location:{$this->id}")
            : Cache::forever("coordinator_location:{$this->id}", $slug);
    }

    // Ketua kelas dari salah satu kelas yang diikuti $other.
    public function isAdminOver(User $other): bool
    {
        return $this->memberships()
            ->where('role', MemberRole::Admin)
            ->whereIn('classroom_id', $other->memberships()->select('classroom_id'))
            ->exists();
    }
}

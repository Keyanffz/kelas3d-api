<?php

namespace App\Models;

use Database\Factories\ClassroomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'network_cidr', 'public_ip'])]
#[RouteKey('slug')]
class Classroom extends Model
{
    /** @use HasFactory<ClassroomFactory> */
    use HasFactory;

    public function members(): HasMany
    {
        return $this->hasMany(ClassroomMember::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class);
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    public function helpRequests(): HasMany
    {
        return $this->hasMany(HelpRequest::class);
    }

    public function nudges(): HasMany
    {
        return $this->hasMany(Nudge::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    /**
     * Kode kursi yang sah, sesuai titik seat_<kelas>_<kode> di gedung.glb.
     *
     * @return list<string>
     */
    public function seatCodes(): array
    {
        return config("kelas3d.seats.{$this->slug}", []);
    }
}

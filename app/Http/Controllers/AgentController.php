<?php

namespace App\Http\Controllers;

use App\Enums\DeviceStatus;
use App\Enums\NudgeStatus;
use App\Http\Requests\AgentLoginRequest;
use App\Http\Requests\HeartbeatRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\Announcement;
use App\Models\AppRule;
use App\Models\Classroom;
use App\Models\Nudge;
use App\Models\User;
use App\Support\InstallCode;
use App\Support\PresenceRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Endpoint untuk kelas3d-agent. Respons tidak dibungkus "data".
class AgentController extends Controller
{
    /**
     * Login laptop dengan kode pribadi.
     *
     * Kode dibuat ketua kelas atau koordinator lewat `POST /classrooms/{slug}/members/{id}/install-code` dan
     * cuma berlaku sekali. Laptop langsung tercatat atas nama pemilik kode, aktif tanpa perlu di-approve.
     */
    public function login(AgentLoginRequest $request): JsonResponse
    {
        $issued = InstallCode::redeem($request->validated('code'));

        if (! $issued) {
            throw ValidationException::withMessages(['code' => 'Kode tidak ditemukan, sudah dipakai, atau kedaluwarsa.']);
        }

        $user = User::findOrFail($issued['user_id']);
        $device = $user->devices()->create([
            ...$request->safe()->only(['hostname', 'os', 'agent_version']),
            'status' => DeviceStatus::Approved,
            'approved_by' => $issued['issued_by'],
            'approved_at' => now(),
        ]);

        return response()->json([
            'token' => $device->createToken('agent')->plainTextToken,
            'device_id' => $device->id,
            'user' => ['id' => $user->id, 'name' => $user->name],
        ]);
    }

    /**
     * Link masuk web tanpa password.
     *
     * Agent membuka `url` di browser (perintah `kelas3d buka`). Halaman web menukar `token` di URL itu lewat
     * `POST /login/link`. Link berlaku sekali selama `expires_in` detik.
     */
    public function webLink(Request $request): JsonResponse
    {
        $token = Str::random(40);
        $ttl = config('kelas3d.web_link_ttl');

        Cache::put("web_login:$token", $request->user()->user_id, $ttl);

        return response()->json([
            'url' => config('kelas3d.web_link_url').'?token='.$token,
            'expires_in' => $ttl,
        ]);
    }

    /**
     * Jadwal kelas dan app_rules.
     *
     * Kelas yang diikuti pemilik laptop beserta jadwalnya, plus `app_rules` aktif untuk OS laptop ini,
     * urut prioritas tertinggi dulu.
     */
    public function config(Request $request): JsonResponse
    {
        $device = $request->user();
        $classrooms = Classroom::whereHas('members', fn ($members) => $members->where('user_id', $device->user_id))
            ->with('schedules')
            ->get();

        return response()->json([
            'heartbeat_interval' => config('kelas3d.heartbeat_interval'),
            'idle_limit' => config('kelas3d.idle_limit'),
            'classrooms' => $classrooms->map(fn (Classroom $classroom) => [
                'slug' => $classroom->slug,
                'name' => $classroom->name,
                'network_cidr' => $classroom->network_cidr,
                'schedules' => ScheduleResource::collection($classroom->schedules),
            ]),
            'app_rules' => AppRule::where('is_active', true)
                ->where(fn ($rules) => $rules->whereNull('os')->orWhere('os', $device->os))
                ->orderByDesc('priority')
                ->get(['match_on', 'pattern', 'os', 'activity', 'label', 'priority']),
        ]);
    }

    /**
     * Kirim heartbeat.
     *
     * Server menentukan ruangan dan keadaan, lalu membalas pengumuman dan teguran yang belum diterima agent ini
     * (id di atas `last_announcement_id` dan `last_nudge_id`; kalau kosong, pengumuman hari ini dan teguran yang
     * belum dibaca). `nudges` cuma terisi untuk ketua kelas. `queued` dipakai untuk riwayat saja.
     */
    public function heartbeat(HeartbeatRequest $request, PresenceRecorder $recorder): JsonResponse
    {
        $device = $request->user();
        $user = $device->user;
        $now = now();

        foreach (collect($request->validated('queued', []))->sortBy('sent_at') as $item) {
            $at = Carbon::parse($item['sent_at'])->setTimezone(config('app.timezone'));

            if ($at->lt($now)) {
                $recorder->heartbeat($user, $item, $request->ip(), $at, broadcast: false);
            }
        }

        $recorder->heartbeat($user, $request->validated(), $request->ip(), $now);
        $device->update(['agent_version' => $request->validated('agent_version'), 'last_seen_at' => $now]);

        $classroomIds = $user->memberships()->pluck('classroom_id');
        $lastAnnouncement = $request->validated('last_announcement_id');
        $lastNudge = $request->validated('last_nudge_id');

        $announcements = Announcement::where(fn ($query) => $query->whereNull('classroom_id')->orWhereIn('classroom_id', $classroomIds))
            ->when($lastAnnouncement !== null,
                fn ($query) => $query->where('id', '>', $lastAnnouncement),
                fn ($query) => $query->where('created_at', '>=', $now->copy()->startOfDay()))
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'message']);

        $nudges = Nudge::with('aboutUser')
            ->where('to_user_id', $user->id)
            ->where('status', NudgeStatus::Terkirim)
            ->when($lastNudge !== null, fn ($query) => $query->where('id', '>', $lastNudge))
            ->orderBy('id')
            ->limit(20)
            ->get();

        return response()->json([
            'announcements' => $announcements,
            'nudges' => $nudges->map(fn (Nudge $nudge) => [
                'id' => $nudge->id,
                'about' => $nudge->aboutUser->name,
                'message' => $nudge->message,
            ]),
        ]);
    }
}

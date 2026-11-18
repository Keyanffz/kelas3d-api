<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Throwable;

// Web seseorang dianggap terbuka kalau channel pribadinya (user.{id}) sedang didengar di Reverb.
// Layar diambil browser, jadi tanpa web yang terbuka permintaan lihat layar tidak bisa dijawab.
class WebOnline
{
    public function has(User $user): bool
    {
        $broadcaster = Broadcast::connection();

        if (! $broadcaster instanceof PusherBroadcaster) {
            return false;
        }

        try {
            return (bool) ($broadcaster->getPusher()->getChannelInfo("private-user.{$user->id}")->occupied ?? false);
        } catch (Throwable) {
            return false;
        }
    }
}

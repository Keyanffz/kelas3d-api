<?php

namespace App\Policies;

use App\Models\Nudge;
use App\Models\User;

class NudgePolicy
{
    // Yang menandai dibaca atau selesai cuma ketua kelas penerimanya.
    public function update(User $user, Nudge $nudge): bool
    {
        return $user->id === $nudge->to_user_id;
    }
}

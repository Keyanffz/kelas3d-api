<?php

namespace App\Models;

use App\Enums\Activity;
use App\Enums\DeviceOs;
use App\Enums\MatchOn;
use Database\Factories\AppRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['match_on', 'pattern', 'os', 'activity', 'label', 'priority', 'is_active'])]
class AppRule extends Model
{
    /** @use HasFactory<AppRuleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'match_on' => MatchOn::class,
            'os' => DeviceOs::class,
            'activity' => Activity::class,
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}

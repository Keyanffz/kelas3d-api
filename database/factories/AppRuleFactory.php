<?php

namespace Database\Factories;

use App\Enums\Activity;
use App\Enums\DeviceOs;
use App\Enums\MatchOn;
use App\Models\AppRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppRule>
 */
class AppRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_on' => MatchOn::Process,
            'pattern' => 'Code.exe',
            'os' => DeviceOs::Windows,
            'activity' => Activity::Ngoding,
            'label' => 'VS Code',
            'priority' => 10,
            'is_active' => true,
        ];
    }
}

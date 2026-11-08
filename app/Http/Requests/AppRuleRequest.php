<?php

namespace App\Http\Requests;

use App\Enums\Activity;
use App\Enums\DeviceOs;
use App\Enums\MatchOn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// POST mengirim semua field, PATCH boleh sebagian.
class AppRuleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'match_on' => [$required, Rule::enum(MatchOn::class)],
            'pattern' => [$required, 'string', 'max:255'],
            // null = semua OS
            'os' => ['sometimes', 'nullable', Rule::enum(DeviceOs::class)],
            'activity' => [$required, Rule::enum(Activity::class)],
            'label' => [$required, 'string', 'max:100'],
            'priority' => [$required, 'integer', 'between:0,1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

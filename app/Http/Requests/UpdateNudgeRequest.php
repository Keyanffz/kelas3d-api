<?php

namespace App\Http\Requests;

use App\Enums\NudgeStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNudgeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(NudgeStatus::class)->only([NudgeStatus::Dibaca, NudgeStatus::Selesai])],
        ];
    }
}

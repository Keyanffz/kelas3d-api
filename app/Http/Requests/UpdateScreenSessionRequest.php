<?php

namespace App\Http\Requests;

use App\Enums\ScreenCloseReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScreenSessionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['setujui', 'tolak', 'izinkan', 'mulai', 'stop'])],
            // Cuma untuk `stop`: kenapa sesi berhenti kalau bukan dihentikan sendiri.
            'reason' => ['nullable', Rule::enum(ScreenCloseReason::class)->only([ScreenCloseReason::IzinBrowser, ScreenCloseReason::Terputus])],
        ];
    }
}

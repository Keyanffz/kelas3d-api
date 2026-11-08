<?php

namespace App\Http\Requests;

use App\Enums\DeviceOs;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgentLoginRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Kode pribadi dari ketua kelas, contoh BCDF-GHJK.
            'code' => ['required', 'string', 'max:20'],
            'hostname' => ['required', 'string', 'max:255'],
            'os' => ['required', Rule::enum(DeviceOs::class)],
            'agent_version' => ['required', 'string', 'max:50'],
        ];
    }
}

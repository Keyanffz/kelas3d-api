<?php

namespace App\Http\Requests;

use App\Enums\DeviceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Laptop langsung aktif saat login dengan kode, jadi yang tersisa cuma revoke.
            'status' => ['required', Rule::enum(DeviceStatus::class)->only([DeviceStatus::Revoked])],
        ];
    }
}

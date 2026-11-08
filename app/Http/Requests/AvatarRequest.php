<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AvatarRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $hex = 'regex:/^#[0-9a-fA-F]{6}$/';

        return [
            'tipe_badan' => ['required', Rule::in(config('kelas3d.avatar.tipe_badan'))],
            'rambut' => ['required', Rule::in(config('kelas3d.avatar.rambut'))],
            'warna' => ['required', 'array:kulit,rambut,celana'],
            'warna.kulit' => ['required', 'string', $hex],
            'warna.rambut' => ['required', 'string', $hex],
            'warna.celana' => ['required', 'string', $hex],
        ];
    }
}

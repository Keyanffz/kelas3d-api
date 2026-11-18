<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AksesorisRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $slot = fn (string $slot) => ['present', 'nullable', Rule::in(collect(config('kelas3d.aksesoris'))->where('slot', $slot)->pluck('id'))];

        return [
            'topi' => $slot('topi'),
            'meja' => $slot('meja'),
        ];
    }
}

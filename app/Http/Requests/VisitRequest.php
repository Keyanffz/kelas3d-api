<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VisitRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Slug kelas tujuan, atau null untuk kembali ke ruang koordinator.
            'classroom' => ['present', 'nullable', 'string', 'exists:classrooms,slug'],
        ];
    }
}

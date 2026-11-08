<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AnnouncementRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:255'],
            // Slug kelas tujuan, atau null untuk semua kelas.
            'classroom' => ['nullable', 'string', 'exists:classrooms,slug'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\MemberRole;
use App\Models\Classroom;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreNudgeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'classroom' => ['required', 'string', 'exists:classrooms,slug'],
            // Peserta yang mau ditegur, harus anggota kelas itu.
            'about_user_id' => ['required', 'integer', Rule::exists('classroom_members', 'user_id')
                ->where('classroom_id', Classroom::where('slug', $this->input('classroom'))->value('id'))],
            'message' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $hasAdmin = Classroom::where('slug', $this->input('classroom'))
                ->whereHas('members', fn ($members) => $members->where('role', MemberRole::Admin))
                ->exists();

            if (! $validator->errors()->has('classroom') && ! $hasAdmin) {
                $validator->errors()->add('classroom', 'Kelas ini belum punya ketua kelas.');
            }
        }];
    }
}

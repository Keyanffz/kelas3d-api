<?php

namespace App\Http\Requests;

use App\Enums\MemberRole;
use App\Http\Requests\Concerns\SeatCodeRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    use SeatCodeRules;

    // Mengubah role sama dengan menunjuk ketua kelas, dan itu cuma hak koordinator.
    public function authorize(): bool
    {
        return ! $this->has('role') || $this->user()->can('update', $this->route('classroom'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'seat_code' => ['sometimes', ...$this->seatCodeRules()],
            'role' => ['sometimes', Rule::enum(MemberRole::class)],
        ];
    }
}

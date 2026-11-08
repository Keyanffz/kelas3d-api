<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SeatCodeRules;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    use SeatCodeRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $classroom = $this->route('classroom');
        $email = $this->input('email');
        $existing = is_string($email) ? User::firstWhere('email', $email) : null;

        return [
            'email' => ['required', 'string', 'email', 'max:255', function (string $attribute, mixed $value, Closure $fail) use ($existing, $classroom) {
                if ($existing?->isKoordinator()) {
                    $fail('Koordinator tidak bisa jadi anggota kelas.');
                } elseif ($existing?->membershipIn($classroom)) {
                    $fail('User ini sudah jadi anggota kelas.');
                }
            }],
            // Nama cuma dipakai kalau akunnya belum ada. Peserta tidak butuh password karena masuk lewat
            // kode install dan link dari agent; password cuma untuk yang mau login web dengan email.
            'name' => [Rule::requiredIf($existing === null), 'nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
            'seat_code' => $this->seatCodeRules(),
        ];
    }
}

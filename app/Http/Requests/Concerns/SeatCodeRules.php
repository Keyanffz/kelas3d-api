<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

// Dipakai request tambah dan ubah anggota.
trait SeatCodeRules
{
    // Kode kursi disimpan huruf kecil supaya cocok dengan nama titik di gedung.glb.
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('seat_code'))) {
            $this->merge(['seat_code' => strtolower($this->input('seat_code'))]);
        }
    }

    /**
     * @return array<mixed>
     */
    protected function seatCodeRules(): array
    {
        $classroom = $this->route('classroom');

        return [
            'nullable',
            'string',
            Rule::in($classroom->seatCodes()),
            Rule::unique('classroom_members')->where('classroom_id', $classroom->id)->ignore($this->route('member')),
        ];
    }
}

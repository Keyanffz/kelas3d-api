<?php

namespace App\Http\Requests;

use App\Enums\HelpStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHelpRequest extends FormRequest
{
    // Staf kelas boleh menangani dan menyelesaikan; peserta yang angkat tangan cuma boleh menurunkannya.
    public function authorize(): bool
    {
        $help = $this->route('helpRequest');

        return $this->user()->can('manageMembers', $help->classroom)
            || ($help->user_id === $this->user()->id && $this->input('status') === HelpStatus::Selesai->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(HelpStatus::class)->only([HelpStatus::Ditangani, HelpStatus::Selesai])],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\Activity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HeartbeatRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Isi satu laporan. Heartbeat yang tertunda di antrean agent (queued) memakai bentuk yang sama.
        $report = [
            'activity' => ['nullable', Rule::enum(Activity::class)],
            'app_label' => ['nullable', 'string', 'max:255'],
            'project' => ['nullable', 'string', 'max:255'],
            'file_name' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:50'],
            'idle_seconds' => ['required', 'integer', 'min:0'],
            'local_ip' => ['nullable', 'ip'],
            'sent_at' => ['required', 'date'],
        ];

        $queued = [];
        foreach ($report as $field => $rules) {
            $queued["queued.*.$field"] = $rules;
        }

        return [
            ...$report,
            'agent_version' => ['required', 'string', 'max:50'],
            'last_announcement_id' => ['nullable', 'integer', 'min:0'],
            'last_nudge_id' => ['nullable', 'integer', 'min:0'],
            'queued' => ['sometimes', 'array', 'max:500'],
            ...$queued,
        ];
    }
}

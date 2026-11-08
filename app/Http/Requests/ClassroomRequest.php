<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\IpUtils;

// Dipakai POST dan PUT, jadi semua field dikirim lengkap.
class ClassroomRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('classrooms')->ignore($this->route('classroom'))],
            'network_cidr' => ['nullable', 'bail', 'string', function (string $attribute, string $value, Closure $fail) {
                if (! preg_match('#^([^/]+)/\d{1,3}$#', $value, $match) || ! IpUtils::checkIp($match[1], $value)) {
                    $fail('Format :attribute harus CIDR, contoh 192.168.10.0/24.');
                }
            }],
            'public_ip' => ['nullable', 'ip'],
        ];
    }
}

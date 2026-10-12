<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebcamMatchSignalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'in:offer,answer,ice,restart'],
            'payload' => ['required', 'array'],
            'payload.sdp' => ['required_if:kind,offer,answer', 'string', 'max:20000'],
            'payload.type' => ['required_if:kind,offer,answer', 'in:offer,answer'],
            'payload.roles' => ['nullable', 'array'],
            'payload.roles.*' => ['in:board,face'],
            'payload.candidate' => ['nullable', 'array'],
        ];
    }
}

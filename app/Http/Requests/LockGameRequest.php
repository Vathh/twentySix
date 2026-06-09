<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LockGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'gameId' => 'required|integer|min:1',
            'type' => 'required|string|in:group,playoff',
        ];
    }
}

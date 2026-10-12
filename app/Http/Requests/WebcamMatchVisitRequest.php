<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebcamMatchVisitRequest extends FormRequest
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
            'score' => ['required', 'integer', 'min:0', 'max:180'],
            'remaining_before' => ['required', 'integer', 'min:0', 'max:501'],
            'remaining_after' => ['required', 'integer', 'min:0', 'max:501'],
            'darts_in_visit' => ['required', 'integer', 'min:1', 'max:3'],
            'bust' => ['present', 'boolean'],
            'closed_leg' => ['present', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'score.required' => 'Podaj sumę wizyty.',
            'score.max' => 'Suma wizyty musi być od 0 do 180.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebMatchResultRequest extends FormRequest
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
        if ($this->boolean('walkover')) {
            return [
                'walkover' => 'required|boolean',
                'winner_id' => 'required|integer|exists:players,id',
            ];
        }

        return [
            'player1_score' => 'required|integer|min:0|max:2',
            'player2_score' => 'required|integer|min:0|max:2',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'player1_score.required' => 'Podaj wynik pierwszego gracza w legach.',
            'player2_score.required' => 'Podaj wynik drugiego gracza w legach.',
            'winner_id.required' => 'Wybierz zwycięzcę walkovera.',
        ];
    }
}

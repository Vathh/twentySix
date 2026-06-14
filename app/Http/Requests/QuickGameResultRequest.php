<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuickGameResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gameId' => 'required|integer|exists:quick_games,id',
            'achievements' => 'nullable|array',
            'achievements.*.playerId' => 'required|integer|exists:players,id',
            'achievements.*.type' => 'required|string',
            'achievements.*.value' => 'nullable|integer',
        ];
    }
}

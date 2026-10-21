<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authentication handled by Sanctum middleware
    }

    public function rules(): array
    {
        return [
            'score' => ['required', 'integer'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slot' => ['sometimes', 'integer', 'between:0,9'],
            'data' => ['required', 'array'],
            'revision' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}

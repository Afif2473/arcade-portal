<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadGameZipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller checks developer ownership
    }

    public function rules(): array
    {
        return [
            // Max 100MB zip file
            'bundle' => ['required', 'file', 'mimes:zip', 'max:102400'],
        ];
    }

    public function messages(): array
    {
        return [
            'bundle.required' => 'Please select a game zip file to upload.',
            'bundle.mimes' => 'The uploaded file must be a valid .zip archive.',
            'bundle.max' => 'The game archive must not exceed 100MB.',
        ];
    }
}

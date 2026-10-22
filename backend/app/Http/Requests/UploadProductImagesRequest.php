<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UploadProductImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('supplier-action');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }
}

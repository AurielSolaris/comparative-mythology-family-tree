<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReligionRequest extends FormRequest
{
    /**
     * "Automatic colour" clears the chosen one.
     */
    protected function prepareForValidation(): void
    {
        if ($this->boolean('automatic_color')) {
            $this->merge(['color' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('religions')->ignore($this->route('religion'))],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'region' => ['nullable', 'string', 'max:255'],
            'time_period' => ['nullable', 'string', 'max:255'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Enums\Gender;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EntityRequest extends FormRequest
{
    /**
     * Aliases arrive as a textarea with one alias per line; an empty one
     * clears them.
     */
    protected function prepareForValidation(): void
    {
        // The primary religion is never also an additional one.
        $this->merge([
            'additional_religion_ids' => array_values(array_diff(
                (array) $this->input('additional_religion_ids', []),
                [(string) $this->input('religion_id')],
            )),
        ]);

        $aliases = $this->input('aliases');

        if ($aliases === null) {
            $this->merge(['aliases' => []]);
        } elseif (is_string($aliases)) {
            $this->merge([
                'aliases' => Str::of($aliases)->explode("\n")->map(fn (string $alias) => trim($alias))->filter()->values()->all(),
            ]);
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
            'religion_id' => ['required', 'integer', Rule::exists('religions', 'id')],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('entities')->where('religion_id', $this->integer('religion_id'))->ignore($this->route('entity')),
            ],
            'additional_religion_ids' => ['array'],
            'additional_religion_ids.*' => ['integer', 'distinct', Rule::exists('religions', 'id')],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'element' => ['nullable', 'string', 'max:255'],
            'syncretic_origin_id' => [
                'nullable', 'integer', Rule::exists('entities', 'id'),
                Rule::notIn(array_filter([$this->route('entity')?->id])),
            ],
            'region' => ['nullable', 'string', 'max:255'],
            'origin' => ['nullable', 'string', 'max:5000'],
            'aliases' => ['nullable', 'array', 'max:50'],
            'aliases.*' => ['string', 'max:255', 'distinct:ignore_case'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'This religion already has an entity with that name.',
            'aliases.*.distinct' => 'Each alias should only be listed once.',
            'syncretic_origin_id.not_in' => 'An entity cannot be its own syncretic origin.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'religion_id' => 'religion',
            'additional_religion_ids.*' => 'additional religion',
            'aliases.*' => 'alias',
            'syncretic_origin_id' => 'syncretic origin',
        ];
    }
}

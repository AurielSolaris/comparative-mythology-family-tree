<?php

namespace App\Http\Requests\Admin;

use App\Enums\Classification;
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
            'classification' => ['nullable', Rule::enum(Classification::class)],
            'classification_term' => ['nullable', 'string', 'max:255'],
            'memberships' => ['array'],
            'memberships.*.classification' => ['nullable', Rule::enum(Classification::class)],
            'memberships.*.term' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'element' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'origin' => ['nullable', 'string', 'max:5000'],
            'aliases' => ['nullable', 'array', 'max:50'],
            'aliases.*' => ['string', 'max:255', 'distinct:ignore_case'],
        ];
    }

    /**
     * The additional religions with what the entity is in each, ready for
     * sync(): [religion id => [classification, classification_term]].
     *
     * @return array<int, array{classification: ?string, classification_term: ?string}>
     */
    public function memberships(): array
    {
        $details = (array) $this->validated('memberships', []);

        return collect($this->validated('additional_religion_ids'))
            ->mapWithKeys(fn ($id) => [(int) $id => [
                'classification' => $details[$id]['classification'] ?? null ?: null,
                'classification_term' => $details[$id]['term'] ?? null ?: null,
            ]])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'This religion already has an entity with that name.',
            'aliases.*.distinct' => 'Each alias should only be listed once.',
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
            'memberships.*.classification' => 'classification',
            'memberships.*.term' => 'term',
            'aliases.*' => 'alias',
        ];
    }
}

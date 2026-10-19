<?php

namespace App\Http\Requests\Admin;

use App\Enums\RelationCertainty;
use App\Enums\RelationRole;
use App\Models\Entity;
use App\Models\EntityRelation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEntityRelationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(RelationRole::class)],
            'related_entity_id' => [
                'required', 'integer', Rule::exists('entities', 'id'),
                Rule::notIn([$this->entity()->id]),
            ],
            'religion_id' => ['nullable', 'integer', Rule::exists('religions', 'id')],
            'certainty' => ['nullable', Rule::enum(RelationCertainty::class)],
            'period' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Rejects duplicates and a parent link that contradicts an existing one.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $attributes = EntityRelation::attributesFor($this->entity(), $this->role(), $this->relatedEntity());

                if (EntityRelation::query()->where($attributes)->exists()) {
                    $validator->errors()->add('related_entity_id', 'This relation already exists.');

                    return;
                }

                $reversed = ! $attributes['kind']->isSymmetric() && EntityRelation::query()->where([
                    'entity_id' => $attributes['related_entity_id'],
                    'related_entity_id' => $attributes['entity_id'],
                    'kind' => $attributes['kind'],
                ])->exists();

                if ($reversed) {
                    $validator->errors()->add('related_entity_id', 'The reverse of this relation already exists.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'related_entity_id.required' => 'Choose an entity from the search results.',
            'related_entity_id.not_in' => 'An entity cannot be related to itself.',
            'religion_id.exists' => 'Choose a tradition from the list.',
            'certainty.enum' => 'Choose a certainty from the list.',
        ];
    }

    public function entity(): Entity
    {
        return $this->route('entity');
    }

    public function role(): RelationRole
    {
        return $this->enum('role', RelationRole::class);
    }

    public function relatedEntity(): Entity
    {
        return Entity::query()->findOrFail($this->integer('related_entity_id'));
    }
}

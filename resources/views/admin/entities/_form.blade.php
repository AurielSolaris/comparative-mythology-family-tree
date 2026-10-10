<div class="uk-grid-small" uk-grid>
    <div class="uk-width-2-3@s">
        <x-admin.field label="Name" for="name">
            <input @class(['uk-input', 'uk-form-danger' => $errors->has('name')]) id="name" name="name" type="text" value="{{ old('name', $entity->name) }}" required maxlength="255">
        </x-admin.field>
    </div>

    <div class="uk-width-1-3@s">
        <x-admin.field label="Gender" for="gender">
            <select @class(['uk-select', 'uk-form-danger' => $errors->has('gender')]) id="gender" name="gender">
                <option value="">Not specified</option>
                @foreach ($genders as $gender)
                    <option value="{{ $gender->value }}" @selected(old('gender', $entity->gender?->value) === $gender->value)>{{ $gender->symbol() }} {{ $gender->label() }}</option>
                @endforeach
            </select>
        </x-admin.field>
    </div>
</div>

<div class="uk-margin" x-data="{ primary: @js((string) old('religion_id', $entity->religion_id)) }">
<div class="uk-child-width-1-2@s uk-grid-small" uk-grid>
    <div>
    <x-admin.field label="Religion" for="religion_id">
        <select @class(['uk-select', 'uk-form-danger' => $errors->has('religion_id')]) id="religion_id" name="religion_id" required x-model="primary">
            <option value="" disabled @selected(! old('religion_id', $entity->religion_id))>Choose a religion</option>
            @foreach ($religions as $religion)
                <option value="{{ $religion->id }}" @selected(old('religion_id', $entity->religion_id) == $religion->id)>{{ $religion->name }}</option>
            @endforeach
        </select>
    </x-admin.field>
    </div>

    <div>
    <x-admin.field label="Region / area" for="region">
        <input @class(['uk-input', 'uk-form-danger' => $errors->has('region')]) id="region" name="region" type="text" value="{{ old('region', $entity->region) }}" maxlength="255" list="known-regions" placeholder="e.g. Mesopotamia">
        <datalist id="known-regions">
            @foreach ($regions as $region)
                <option value="{{ $region }}"></option>
            @endforeach
        </datalist>
    </x-admin.field>
    </div>
</div>

@php($additionalIds = array_map('intval', old('additional_religion_ids', $entity->exists ? $entity->additionalReligions->modelKeys() : [])))

<div class="uk-margin">
    <div class="uk-form-label">Also belongs to</div>
    <div class="uk-form-controls">
        <div class="uk-grid-small uk-child-width-1-2 uk-child-width-1-3@s" uk-grid>
            @foreach ($religions as $religion)
                <div x-show="primary !== '{{ $religion->id }}'">
                    <label><input class="uk-checkbox" type="checkbox" name="additional_religion_ids[]" value="{{ $religion->id }}" @checked(in_array($religion->id, $additionalIds, true)) :disabled="primary === '{{ $religion->id }}'"> {{ $religion->name }}</label>
                </div>
            @endforeach
        </div>
    </div>
    <div class="uk-text-meta uk-margin-small-top">Other pantheons this entity is part of besides its primary religion, e.g. Baal: Canaanite, also Eblaite.</div>
    @error('additional_religion_ids.*')
        <div class="uk-text-danger uk-text-small uk-margin-small-top">{{ $message }}</div>
    @enderror
</div>
</div>

<x-admin.field label="Origin" for="origin" help="Where or how the entity came to be, e.g. “Born from the sea foam off Cythera”.">
    <textarea @class(['uk-textarea', 'uk-form-danger' => $errors->has('origin')]) id="origin" name="origin" rows="3" maxlength="5000">{{ old('origin', $entity->origin) }}</textarea>
</x-admin.field>

<x-admin.field label="Aliases" for="aliases" error="aliases.*" help="Other names, epithets and spellings. One per line.">
    <textarea @class(['uk-textarea', 'uk-form-danger' => $errors->has('aliases') || $errors->has('aliases.*')]) id="aliases" name="aliases" rows="4">{{ implode("\n", Arr::wrap(old('aliases', $entity->aliases ?? []))) }}</textarea>
</x-admin.field>

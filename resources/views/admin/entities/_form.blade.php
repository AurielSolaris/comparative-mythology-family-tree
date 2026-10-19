<div class="uk-grid-small" uk-grid>
    <div class="uk-width-1-2@s">
        <x-admin.field label="Name" for="name">
            <input @class(['uk-input', 'uk-form-danger' => $errors->has('name')]) id="name" name="name" type="text" value="{{ old('name', $entity->name) }}" required maxlength="255">
        </x-admin.field>
    </div>

    <div class="uk-width-1-4@s">
        <x-admin.field label="Gender" for="gender">
            <select @class(['uk-select', 'uk-form-danger' => $errors->has('gender')]) id="gender" name="gender">
                <option value="">Not specified</option>
                @foreach ($genders as $gender)
                    <option value="{{ $gender->value }}" @selected(old('gender', $entity->gender?->value) === $gender->value)>{{ $gender->symbol() }} {{ $gender->label() }}</option>
                @endforeach
            </select>
        </x-admin.field>
    </div>

    <div class="uk-width-1-4@s">
        <x-admin.field label="Element" for="element">
            <input @class(['uk-input', 'uk-form-danger' => $errors->has('element')]) id="element" name="element" type="text" value="{{ old('element', $entity->element) }}" maxlength="255" list="known-elements" placeholder="e.g. Storm">
            <datalist id="known-elements">
                @foreach ($elements as $element)
                    <option value="{{ $element }}"></option>
                @endforeach
            </datalist>
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
    <div class="uk-grid-small" uk-grid>
        <div class="uk-width-1-2">
            <x-admin.field label="Is, in this religion" for="classification">
                <select @class(['uk-select', 'uk-form-danger' => $errors->has('classification')]) id="classification" name="classification">
                    <option value="">Not specified</option>
                    @foreach ($classifications as $classification)
                        <option value="{{ $classification->value }}" title="{{ $classification->description() }}" @selected(old('classification', $entity->classification?->value) === $classification->value)>{{ $classification->label() }}</option>
                    @endforeach
                </select>
            </x-admin.field>
        </div>
        <div class="uk-width-1-2">
            <x-admin.field label="Its own word" for="classification_term">
                <input @class(['uk-input', 'uk-form-danger' => $errors->has('classification_term')]) id="classification_term" name="classification_term" type="text" value="{{ old('classification_term', $entity->classification_term) }}" maxlength="255" placeholder="e.g. deva, daēva">
            </x-admin.field>
        </div>
    </div>
    </div>
</div>

<x-admin.field label="Region / area" for="region">
        <input @class(['uk-input', 'uk-form-danger' => $errors->has('region')]) id="region" name="region" type="text" value="{{ old('region', $entity->region) }}" maxlength="255" list="known-regions" placeholder="e.g. Mesopotamia">
        <datalist id="known-regions">
            @foreach ($regions as $region)
                <option value="{{ $region }}"></option>
            @endforeach
        </datalist>
</x-admin.field>

@php($additionalIds = array_map('intval', old('additional_religion_ids', $entity->exists ? $entity->additionalReligions->modelKeys() : [])))

<div class="uk-margin">
    <div class="uk-form-label">Also belongs to</div>
    <div class="uk-form-controls">
        <div class="uk-grid-small uk-child-width-1-2 uk-child-width-1-3@s" uk-grid>
            @foreach ($religions as $religion)
                @php($membership = $entity->exists ? $entity->additionalReligions->firstWhere('id', $religion->id)?->pivot : null)
                <div x-show="primary !== '{{ $religion->id }}'" x-data="{ member: @js(in_array($religion->id, $additionalIds, true)) }">
                    <label><input class="uk-checkbox" type="checkbox" name="additional_religion_ids[]" value="{{ $religion->id }}" x-model="member" :disabled="primary === '{{ $religion->id }}'"> {{ $religion->name }}</label>
                    <div class="uk-grid-collapse uk-margin-small-top" uk-grid x-show="member" x-cloak>
                        <div class="uk-width-1-2">
                            <select class="uk-select uk-form-small" name="memberships[{{ $religion->id }}][classification]" aria-label="What it is in {{ $religion->name }}" :disabled="!member">
                                <option value="">Is…</option>
                                @foreach ($classifications as $classification)
                                    <option value="{{ $classification->value }}" @selected(old("memberships.{$religion->id}.classification", $membership?->classification?->value) === $classification->value)>{{ $classification->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="uk-width-1-2">
                            <input class="uk-input uk-form-small" type="text" name="memberships[{{ $religion->id }}][term]" value="{{ old("memberships.{$religion->id}.term", $membership?->classification_term) }}" maxlength="255" placeholder="Own word" aria-label="{{ $religion->name }}'s own word for it" :disabled="!member">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="uk-text-meta uk-margin-small-top">Other pantheons or traditions this entity appears in besides its primary religion, and what it is there, e.g. Indra: Vedic God, also a Zoroastrian demon (daēva).</div>
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

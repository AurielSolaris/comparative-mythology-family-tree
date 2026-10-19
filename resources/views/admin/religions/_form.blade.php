<x-admin.field label="Name" for="name" help="For example: Greek, Norse, Vedic, Egyptian.">
    <input @class(['uk-input', 'uk-form-danger' => $errors->has('name')]) id="name" name="name" type="text" value="{{ old('name', $religion->name) }}" required maxlength="255" autofocus>
</x-admin.field>

<x-admin.field label="Region" for="region" help="Where it was practised. For example: Northern Syria, Iran, the Hejaz.">
    <input @class(['uk-input', 'uk-form-danger' => $errors->has('region')]) id="region" name="region" type="text" value="{{ old('region', $religion->region) }}" maxlength="255">
</x-admin.field>

<x-admin.field label="Time period" for="time_period" help="When it was practised. For example: c. 2400–2300 BCE, Iron Age, 6th c. BCE – 7th c. CE.">
    <input @class(['uk-input', 'uk-form-danger' => $errors->has('time_period')]) id="time_period" name="time_period" type="text" value="{{ old('time_period', $religion->time_period) }}" maxlength="255">
</x-admin.field>

<div class="uk-margin" x-data="{ automatic: @js((bool) old('automatic_color', blank($religion->color))) }">
    <label class="uk-form-label" for="color">Colour</label>
    <div class="uk-form-controls">
        <div class="uk-grid-small uk-flex-middle" uk-grid>
            <div class="uk-width-auto">
                <input @class(['uk-input', 'uk-form-width-xsmall', 'admin-color-input', 'uk-form-danger' => $errors->has('color')])
                       id="color" name="color" type="color" value="{{ old('color', $religion->color ?? '#1e87f0') }}" :disabled="automatic">
            </div>
            <div class="uk-width-expand">
                <label><input class="uk-checkbox" type="checkbox" name="automatic_color" value="1" x-model="automatic"> Automatic colour</label>
            </div>
        </div>
    </div>
    <div class="uk-text-meta uk-margin-small-top">Used for this religion's stripe on each entity, its column when comparing, and its labels.</div>
    @error('color')
        <div class="uk-text-danger uk-text-small uk-margin-small-top">{{ $message }}</div>
    @enderror
</div>

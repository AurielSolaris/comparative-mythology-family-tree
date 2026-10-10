<?php

namespace App\Http\Requests\Admin;

use App\Enums\SourceKind;
use App\Models\Source;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Changes to a source. Its kind stays the same: a link can change its URL,
 * a file source can be replaced by a new upload (or keep its file).
 */
class UpdateSourceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Source $source */
        $source = $this->route('source');

        return [
            ...$source->kind === SourceKind::Link
                ? ['url' => ['required', 'url:http,https', 'max:2048']]
                : ['file' => ['nullable', 'file', 'extensions:'.implode(',', Source::ALLOWED_EXTENSIONS), 'max:'.Source::maxUploadKilobytes()]],
            'ref' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.required' => 'Enter the link to the source.',
            'file.extensions' => 'Upload a document or image ('.implode(', ', Source::ALLOWED_EXTENSIONS).').',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'url' => 'link',
            'ref' => 'reference',
        ];
    }
}

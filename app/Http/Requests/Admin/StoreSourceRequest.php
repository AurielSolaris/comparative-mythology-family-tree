<?php

namespace App\Http\Requests\Admin;

use App\Enums\SourceKind;
use App\Models\Source;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new source: a link (URL) or an uploaded file, plus an optional
 * line / verse / page reference.
 */
class StoreSourceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(SourceKind::class)],
            'url' => ['exclude_unless:kind,link', 'required', 'url:http,https', 'max:2048'],
            'file' => ['exclude_unless:kind,file', 'required', 'file', 'extensions:'.implode(',', Source::ALLOWED_EXTENSIONS), 'max:'.Source::maxUploadKilobytes()],
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
            'file.required' => 'Choose a file to upload.',
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

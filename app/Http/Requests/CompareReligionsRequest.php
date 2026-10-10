<?php

namespace App\Http\Requests;

use App\Models\Religion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * ?religions=canaanite,eblaite — the religions to show side by side, in
 * order from left to right.
 */
class CompareReligionsRequest extends FormRequest
{
    public const MIN = 2;

    public const MAX = 4;

    /** @var Collection<int, Religion>|null */
    private ?Collection $religions = null;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'religions' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $slugs = $this->slugs();
                $found = $this->religions()->count();

                if (count($slugs) < self::MIN || count($slugs) > self::MAX) {
                    $validator->errors()->add('religions', 'Choose between '.self::MIN.' and '.self::MAX.' religions to compare.');
                } elseif ($found !== count($slugs)) {
                    $validator->errors()->add('religions', 'One of the chosen religions does not exist.');
                }
            },
        ];
    }

    /**
     * The chosen religions in the order they were listed.
     *
     * @return Collection<int, Religion>
     */
    public function religions(): Collection
    {
        if ($this->religions === null) {
            $slugs = $this->slugs();

            $this->religions = Religion::query()
                ->whereIn('slug', $slugs)
                ->get()
                ->sortBy(fn (Religion $religion) => array_search($religion->slug, $slugs, true))
                ->values();
        }

        return $this->religions;
    }

    /**
     * @return list<string>
     */
    private function slugs(): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $this->input('religions'))))));
    }
}

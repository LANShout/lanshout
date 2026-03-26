<?php

namespace App\Http\Requests\Chat;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFilterChainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isModerator() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['contains', 'regex', 'exact'])],
            'pattern' => ['required', 'string', 'max:1000'],
            'action' => ['required', 'string', Rule::in(['block', 'replace', 'warn'])],
            'replacement' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'priority' => ['integer', 'min:0', 'max:1000'],
        ];
    }
}

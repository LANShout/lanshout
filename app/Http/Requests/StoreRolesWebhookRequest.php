<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRolesWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string', 'in:user.roles_updated'],
            'user' => ['required', 'array'],
            'user.id' => ['required', 'integer'],
            'user.username' => ['required', 'string'],
            'user.roles' => ['required', 'array'],
            'user.roles.*' => ['string'],
        ];
    }
}

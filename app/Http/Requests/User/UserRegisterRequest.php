<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;


class UserRegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'username' => 'required|unique:users,username|regex:/^[a-zA-Z0-9._]+$/u',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:student,school,company',
            'recaptcha_token' => 'nullable', // CAPTCHA disabled for local dev
            'name' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'type' => 'required_if:role,school|in:university,school' //from required to required_if (student and company has no type)
        ];

        $this->addConditionalRule($rules);

        return $rules;
    }

    private function addConditionalRule(&$rules)
    {
        $role = $this->input('role');
        switch ($role) {
            case 'student':
                $rules['school_id'] = 'nullable|max:36|exists:schools,id';
                break;
            default:
                $rules['address'] = 'nullable|string';
                break;
        }
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response([
            "errors" => $validator->getMessageBag()
        ], 400));
    }
}

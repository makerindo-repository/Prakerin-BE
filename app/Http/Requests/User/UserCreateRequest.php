<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UserCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'password' => 'required|string|confirmed',
            'role' => 'required|in:student,school,company,super_admin',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ];

        $this->addConditionalRule($rules);

        return $rules;
    }

    private function addConditionalRule(&$rules)
    {
        $role = $this->input('role');
        switch ($role) {
            case 'student':
                $rules['name'] = 'required|string|max:255';
                if (auth()->user()->tokenCant('school-access')) {
                    $rules['school_id'] = 'required|max:36|exists:schools,id';
                }
                $rules['class'] = 'nullable|in:10,11,12,collage';
                $rules['major_id'] = 'nullable|max:36|exists:majors,id';
                $rules['gender'] = 'nullable|in:male,female';
                $rules['address'] = 'nullable|string';
                $rules['phone_number'] = 'nullable|string|max:20';
                $rules['date_of_birth'] = 'nullable|date_format:Y-m-d';
                break;
            case 'super_admin':
                break;
            default:
                $rules['name'] = 'required|string|max:255';
                $rules['address'] = 'required|string';
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

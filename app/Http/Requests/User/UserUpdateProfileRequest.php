<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserUpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation()
    {
        if ($this->has('is_verified')) {
            $this->merge([
                'is_verified' => filter_var($this->is_verified, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        //Added $this->route('id') for admin updating user so it'll use the route's id instead
        $userId = $this->route('id') ?? $this->user()->id;

        $rules = [
            'username' => [
                'nullable',
                'regex:/^[a-zA-Z0-9._]+$/u',
                Rule::unique('users', 'username')->ignore($userId)
            ],
            'email' => [
                'nullable',
                'email',
                Rule::unique('users', 'email')->ignore($userId)
            ],
            'password' => 'nullable|string|min:6|confirmed',
            'photo_profile' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ];

        // Admin and school can update is_verified field 
        if ($this->user()->tokenCan('admin-access') || $this->user()->tokenCan('school-access')) {
            $rules['is_verified'] = 'nullable|boolean';
        }

        $this->addConditionalRule($rules);

        return $rules;
    }

    private function addConditionalRule(&$rules)
    {
        $userId = $this->route('id') ?? $this->user()->id;
        $targetUser = \App\Models\User::find($userId);
        $role = $targetUser ? $targetUser->role : $this->user()->role;

        $rules['name'] = 'nullable|string|max:255';
        $rules['address'] = 'nullable|string';
        $rules['phone_number'] = [
            'nullable',
            'string',
            'regex:/^\+628[0-9]{8,10}$/',
            'min:10',
            'max:20',
        ];
        switch ($role) {
            case 'student':
                $rules['major_id'] = 'nullable|uuid:4|exists:majors,id';
                $rules['school_id'] = 'nullable|uuid:4|exists:schools,id';
                $rules['date_of_birth'] = 'nullable|date_format:Y-m-d';
                $rules['gender'] = 'nullable|in:male,female';
                $rules['class'] = 'nullable|in:10,11,12,collage';
                $rules['skill'] = 'nullable|string|max:255';
                $rules['portofolio_link'] = 'nullable|url|max:255';
                $rules['social_media_link'] = 'nullable|url|max:255';
                break;
            case 'company':
                $rules['city_regency_id'] = 'nullable|uuid:4|exists:city_regencies,id';
                $rules['sector_id'] = 'nullable|uuid:4|exists:sectors,id';
                $rules['website'] = 'nullable|url|max:255';
                $rules['description'] = 'nullable';
                break;
            case 'school':
                $rules['city_regency_id'] = 'nullable|uuid:4|exists:city_regencies,id';
                $rules['accreditation'] = 'nullable|in:A,B,C';
                $rules['status'] = 'nullable|in:negeri,swasta';
                $rules['npsn'] = 'nullable|string|max:255';
                $rules['website'] = 'nullable|url|max:255';
                $rules['description'] = 'nullable';
                break;
            default:
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

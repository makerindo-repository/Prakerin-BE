<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UserUpdateProfileRequest extends FormRequest
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
            'username' => 'nullable|unique:users,username|regex:/^[a-zA-Z0-9._]+$/u',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'nullable|string|confirmed',
            'photo_profile' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ];

        if ($this->user()->tokenCant('admin-access')) {
            $this->addConditionalRule($rules);
        }

        return $rules;
    }

    private function addConditionalRule(&$rules)
    {
        $role = $this->user()->role;

        $rules['name'] = 'nullable|string|max:255';
        $rules['address'] = 'nullable|string';
        $rules['phone_number'] = [
            'nullable',
            'string',
            'regex:/^(?:\+62[\s\-]?|0)[\s\-]?[0-9]+([\s\-]?[0-9]+)*$/',
            'min:10',
            'max:20'  // Lebih panjang karena ada spasi/strip
        ];
        switch ($role) {
            case 'student':
                $rules['major_id'] = 'nullable|uuid:4|exists:majors,id';
                $rules['school_id'] = 'nullable|uuid:4|exists:schools,id';
                $rules['date_of_birth'] = 'nullable|date_format:Y-m-d';
                $rules['gender'] = 'nullable|in:male,female';
                $rules['class'] = 'nullable|in:10,11,12,college';
                $rules['skill'] = 'nullable|string|max:255';
                $rules['portofolio_link'] = 'nullable|url|max:255';
                $rules['social_media_link'] = 'nullable|url|max:255';
                break;
            case 'company':
                $rules['city_regency_id'] = 'nullable|uuid:4|exists:city_regencies,id';
                $rules['sector_id'] = 'nullable|uuid:4|exists:sectors,id';
                $rules['description'] = 'nullable';
                break;
            case 'school':
                $rules['city_regency_id'] = 'nullable|uuid:4|exists:city_regencies,id';
                $rules['accreditation'] = 'nullable|in:a,b,c';
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

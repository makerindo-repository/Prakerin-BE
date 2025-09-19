<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UserUpdateRequest extends FormRequest
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

        return [
            'username' => 'nullable|unique:users,username|regex:/^[a-zA-Z0-9._]+$/u',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'nullable|string|confirmed',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone_number' => 'nullable|string|regex:/^(?:\+62|0)[0-9]{9,13}$/',
            'school_id' => 'nullable|uuid:4|exists:schools,id',
            'date_of_birth' => 'nullable|date_format:Y-m-d',
            'gender' => 'nullable|in:male,female',
            'city_regency_id' => 'nullable|uuid:4|exists:city_regencies,id',
            'sector_id' => 'nullable|uuid:4|exists:sectors,id',
            'website' => 'nullable|url|max:255',
            'npsn' => 'nullable|string|max:8',
            'accreditation' => 'nullable|in:A,B,C',
            'status' => 'nullable|in:negeri,swasta',
            'is_verified' => 'nullable|boolean',
        ];

    }


    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response([
            "errors" => $validator->getMessageBag()
        ], 400));
    }

}



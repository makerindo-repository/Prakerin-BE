<?php

namespace App\Http\Requests\Student;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StudentUpdateRequest extends FormRequest
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
            'name' => 'string|max:255|nullable',
            'date_of_birth' => 'date_format:Y-m-d|nullable',
            'gender' => 'in:male,female|nullable',
            'address' => 'string|nullable',
            'phone_number' => 'string|max:20|nullable',
            'class' => 'in:10,11,12,collage|nullable',
            'major_id' => 'max:36|exists:majors,id|nullable',
            'image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048|nullable',
        ];

        if ($this->user()->role === 'super_admin') {
            $rules['school_id'] = 'max:36|exists:schools,id|nullable';
        }
        return $rules;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response([
            "errors" => $validator->getMessageBag()
        ], 400));
    }
}

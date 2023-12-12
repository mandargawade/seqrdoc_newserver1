<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExcelValidationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'field_file' => 'max:2000'
        ];
    }
    public function messages(){
        return[
            'field_file.max' => 'Please upload excel sheet having size less than 2 MB'
        ];
    }
}

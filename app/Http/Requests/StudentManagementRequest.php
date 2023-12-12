<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentManagementRequest extends FormRequest
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
            'field_file'=>'required|file|mimes:xls,xlsx|max:10000', 
        ];
    }
    public function messages()
    {
        return [
            'field_file.required'=>'Please enter valid excel sheet',
            'field_file.mimes'=>'Accepted file format XLS or XLSX.',
            'field_file.max'=>'Max file size 10 MB',
        ];
    }
}

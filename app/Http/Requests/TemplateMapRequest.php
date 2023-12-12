<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TemplateMapRequest extends FormRequest
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
          'field_file' => 'required|mimes:xls,xlsx',
        ];
    }
    public function messages()
    {
        return [
            "field_file.required"=>"Please Choose file",
            'field_file.mimes'=>'Accepted file format XLS or XLSX.'
        ];
    }
}

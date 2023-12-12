<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class processExcelRequest extends FormRequest
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
          'excel_data' => 'required|mimes:xls,xlsx',
        ];
    }
    public function messages()
    {
        return [
            "excel_data.required"=>"Please Choose file",
            'excel_data.mimes'=>'Accepted file format XLS or XLSX.'
        ];
    }
}

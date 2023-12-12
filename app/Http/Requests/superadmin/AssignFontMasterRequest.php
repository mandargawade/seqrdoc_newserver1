<?php

namespace App\Http\Requests\Superadmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Auth;
class AssignFontMasterRequest extends FormRequest
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
    public function rules(Request $request)
    {
      
   
     return [
          'font_id_assign' => 'required|max:100',
          'font_name_assign' => 'required|max:100',
          'dest_instance'=>'required',

        ];
    }
    public function messages()
    {
        return [
            "font_id_assign.required"=>"Font id not found.",
            "font_name_assign.required"=>"Font name not found.",
            "dest_instance.required"=>"Please select instance.",
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Auth;
class FontMasterRequest extends FormRequest
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
      
      $id=$request['font_id'];
      $site_id=Auth::guard('admin')->user()->site_id;

     return [
          'font_name' => 'required|max:100|unique:font_master,font_name,'.$id.',id,site_id,'.$site_id.',deleted_at,NULL',
         /* 'upload_font_N'=>'sometimes|file|mimes:ttf',*/
          /*'upload_font_B'=>'mimes:ttf',  
          'upload_font_I'=>'mimes:ttf',
          'upload_font_BI'=>'mimes:ttf',*/
          'opt_status'=>'required',

        ];
    }
    public function messages()
    {
        return [
            "font_name.required"=>"Please enter font name",
            "opt_status.required"=>"Please select status",
        ];
    }
}

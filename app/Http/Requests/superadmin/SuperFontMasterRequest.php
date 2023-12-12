<?php

namespace App\Http\Requests\Superadmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Auth;
class SuperFontMasterRequest extends FormRequest
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
      
      if(isset($request['font_id_assign'])){
         return [
          'font_id_assign' => 'required|max:100',
          'dest_instance'=>'required',

        ];
      } else{
        $id=$request['font_id'];
      $site_id=Auth::guard('superadmin')->user()->site_id;

     return [
          'font_name' => 'required|max:100|unique:super_font_master,font_name,'.$id.',id,deleted_at,NULL',
         /* 'upload_font_N'=>'sometimes|file|mimes:ttf',*/
          /*'upload_font_B'=>'mimes:ttf',  
          'upload_font_I'=>'mimes:ttf',
          'upload_font_BI'=>'mimes:ttf',*/
          'opt_status'=>'required',

        ];
      }
      
    }
    public function messages()
    {
        return [
            "font_name.required"=>"Please enter font name",
            "opt_status.required"=>"Please select status",
            "font_id_assign.required"=>"Please font id",
            "dest_instance.required"=>"Please select instance.",
        ];
    }
}

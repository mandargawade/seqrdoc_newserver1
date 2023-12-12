<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Auth;
class UserManagementRequest extends FormRequest
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
        $id=null;
        $id=$request['user_id'];
        $method_type=$request->isMethod('post');
        $site_id=Auth::guard('admin')->user()->site_id;
        
        if(empty($id))
        {

          return [
            'username'=>'required|max:100|unique:user_table,username,'.$id.',id,site_id,'.$site_id,
            'user_password'=>'required|min:6|max:32',
            'fullname'=>'required|max:100',
            'email_id'=>'required|email|max:100|unique:user_table,email_id,'.$id.',id,site_id,'.$site_id,
            'mobile_no'=>'required|max:10|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|unique:user_table,mobile_no,'.$id.',id,site_id,'.$site_id,
            'status'=>'required',
            'role'=>'required',
          ];

        }
        else
        {
          return [
            'username'=>'required|max:100|unique:user_table,username,'.$id.',id,site_id,'.$site_id,
            'fullname'=>'required|max:100',
            'email_id'=>'required|email|max:100|unique:user_table,email_id,'.$id.',id,site_id,'.$site_id,
            'mobile_no'=>'required|max:10|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|unique:user_table,mobile_no,'.$id.',id,site_id,'.$site_id,
            'status'=>'required',
          ];  
        }

    }
    public function messages()
    {
        return [

           'username.required'=>'Please enter user name',
           'user_password.required'=>'Min. 6 character Password is required',
           'password.min'=>'Min. 6 character Password is required',  
           'fullname.required'=>'Fullname only contain letters',
           'fullname.regex'=>'Fullname only contain letters',
           'email_id.required'=>'This field is required.',
           'email_id.unique'=>'email id. already exists',
           'mobile_no.required'=>'10 Digit Mobile number is required',
           'mobile_no.regex'=>'10 Digit Mobile number is required',
           'mobile_no.min'=>'10 Digit Mobile number is required',
           'mobile_no.max'=>'10 Digit Mobile number is required',
           'mobile_no.unique'=>'Mobile no. already exists',
           'opt_status.required'=>'Please select status',
           'roleId.required'=>'This field is required.',  
        ];
    }
}

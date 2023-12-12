<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Auth;
class UserRequest extends FormRequest
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
        $site_id=Auth::guard('admin')->user()->site_id;
        $id=\Request::segment(3);

        if(empty($id)){
       
            return [
                'username'=>'required|max:100|unique:admin_table,username,'.$id.',id,site_id,'.$site_id,
                'fullname'=>'required|max:100',
                'permissions' => 'required',
                'password'=>'required|min:6|max:32',
                'email'=>'required|email|max:100|unique:admin_table,email,'.$id.',id,site_id,'.$site_id,
                'mobile_no'=>'nullable|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:10|unique:admin_table,mobile_no,'.$id.',id,site_id,'.$site_id,
                'role' => 'required',
                'status' => 'required',
            ];
        }
        else
        {
            return [
                'username'=>'required|max:100|unique:admin_table,username,'.$id.',id,site_id,'.$site_id,
                'fullname'=>'required|max:100',
                'permissions' => 'required',
                'mobile_no'=>'nullable|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:10|unique:admin_table,mobile_no,'.$this->id.',id,site_id,'.$site_id,
                'email' => 'required|email|unique:admin_table,email,'. $id.',id,site_id,'.$site_id,
                'role' => 'required',
                'status' => 'required',
            ];
        }
        
    }

    public function messages() {
        $messages = [
            'username.required' => "Please enter user name",
            'permissions.required' => "Please select permission",
            'email.required' => "Please enter email id",
            'fullname.required'=>'Fullname only contain letters',
            'email.unique' => "This email id is already exists",
            'email.email' => "Please enter valid email id",
            'role.required' => "Please select role",
            'is_active.required' => "Please select user status",
            'mobile_no.required'=>'10 Digit Mobile number is required',
           'mobile_no.regex'=>'10 Digit Mobile number is required',
           'mobile_no.min'=>'10 Digit Mobile number is required',
           'mobile_no.unique'=>'Mobile no. already exists',
           'mobile_no.max'=>'10 Digit Mobile number is required',
           'password.required'=>'Min. 6 character Password is required',
        ];
        return $messages;

    }
}

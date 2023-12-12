<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SuperAdminUserRequest extends FormRequest
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
        switch ($this->method()) {
        case 'POST':
        {
            return [
                'username'=>'required|max:100|unique:super_admin_login',
                'permission' => 'required',
                'password'=>'required|min:6|max:32',
                'role' => 'required',
                'status' => 'required',
            ];
        }
        case 'PATCH':
        {
            return [
                'username'=>'required|max:100|unique:super_admin_login,username,'.$this->id,
                'permission' => 'required',
                'role' => 'required',
                'status' => 'required',
            ];
        }
        default:break;
        }
    }

    public function messages() {
        $messages = [
            'username.required' => "Please enter user name",
            'permission.required' => "Please select permission",
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

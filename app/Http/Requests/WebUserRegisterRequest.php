<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebUserRegisterRequest extends FormRequest
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
            'fullname'   => 'required',
            'email_id'   => 'required|email|unique:user_table,email_id',
            'mobile_no'   => 'required|numeric|min:10|unique:user_table,mobile_no',
            'username'   => 'required|unique:user_table,username',
            'password' => 'required|min:8',
         ];
    }
     public function messages()
    {
        return [
            'fullname.required'=>'Fullname is required',
            'email_id.required'=>'Email is required',
            'email_id.email'=>'Valid Email is required',
            'email_id.unique'=>'Email ID already exists',
            'mobile_no.required'=>'10 Digit Mobile number is required',
            'mobile_no.numeric'=>'10 Digit Mobile number is required',
            'mobile_no.min'=>'10 Digit Mobile number is required',
            'mobile_no.unique'=>'Mobile no. already exists',          
            'username.required'=>'Username is required',
            'username.unique'=>'Username already exists',              
            'password.required'=>'Min. 6 character Password is required',            
            'password.min'=>'Min. 6 character Password is required',   
        ];
    }
}

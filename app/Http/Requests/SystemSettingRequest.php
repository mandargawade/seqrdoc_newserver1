<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SystemSettingRequest extends FormRequest
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
             
           'printer_name'=>'required|max:100',
           'port'=>'nullable|max:6',
           'sender_email'=>'nullable|email|max:100',
           'password'=>'nullable|min:6|max:32',
        ];
    }

    public function messages(){

        return [
          'sender_email.email'=>'Enter Valid Email',
          'password.min'=>'Min. 6 character Password is required'
        ];
    }
}

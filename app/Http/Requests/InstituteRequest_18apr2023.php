<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Auth;
class InstituteRequest extends FormRequest
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
        $id = $request->id;
        $site_id=Auth::guard('admin')->user()->site_id;
        $publish = 1;
          if(empty($id))
           {
                $rules = [
                    'institute_username'=>'required|max:100|unique:institute_table,institute_username,'.$id.',id,site_id,'.$site_id.',publish,'.$publish,
                    'username' => 'required|max:100',
                    'status' => 'required',
                    'password' => 'required|min:6|max:32',
                ];
           
            }
            else
            {
                $rules = [
                    'institute_username' => 'required|max:100|unique:institute_table,institute_username,'.$id.',id,site_id,'.$site_id.',publish,'.$publish,
                    'username' => 'required|max:100',
                    'status' => 'required',
                ];

            }   
        return $rules;
    }

    public function messages()
    {
        return [
            "institute_username.required"=>"Institute user name cannot be empty",
            "username.required"=>"Full name cannot be empty",
            "password.required"=>"password cannot be empty",
            "status.required"=>"status cannot be empty",
        ];
    }
}

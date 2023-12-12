<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Auth;
class SessionsMasterRequest extends FormRequest
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
            'session_name'=>'required|max:256|unique:exam_master',
          
        ];
    }
    public function messages()
    {
        return [
           'session_name.required'=>'Enter Session Name',
           'session_name.unique'=>'Session name alreday taken'
        ];
    }
}

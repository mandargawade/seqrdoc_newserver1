<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Auth;
class DegreeMasterRequest extends FormRequest
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
            'degree_name'=>'required|max:256|unique:degree_master',
          
        ];
    }
    public function messages()
    {
        return [
           'degree_name.required'=>'Enter Degree',
           'degree_name.unique'=>'Degree alreday taken'
        ];
    }
}

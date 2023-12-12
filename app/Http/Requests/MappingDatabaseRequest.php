<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MappingDatabaseRequest extends FormRequest
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
            'db_name'=>'required|max:100',
            'host_address'=>'required|max:100',
            'username'=>'required|max:100',
            'password'=>'required',
            'port'=>'required|max:6',
            'table_name'=>'required',
        ];
    }
    public function messages()
    {
        return [

             'db_name.required'=>'Please enter database name',
             'host_address.required'=>'Please enter database host address',
             'username.required'=>'Please enter user name',
             'password.required'=>'Please enter password',
             'port.required'=>'Please enter database port',
             'table_name.required'=>'Please enter table name',

        ];
    }
}

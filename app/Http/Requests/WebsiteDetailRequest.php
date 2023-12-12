<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebsiteDetailRequest extends FormRequest
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
        $id=\Request::segment(3);
        return [
            'website_url'=>'required|max:300|unique:website_details,website_url,'.$id.',id',
            'db_name'=>'required|max:100',
            'db_host_address'=>'required|max:200',
            'username'=>'required|max:100',
            'password'=>'required|max:50',
            'status'=>'required',
        ];
    }
}

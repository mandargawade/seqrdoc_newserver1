<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebUserVerificationRequest extends FormRequest
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
            'email_id'   => 'required|email',
         ];
    }
     public function messages()
    {
        return [
            'email_id.required'=>'Email is required',
            'email_id.email'=>'Valid Email is required',
        ];
    }

     
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
class AnswerBookletRequest extends FormRequest
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
            'prefix_word'=>'required|max:100',
            'booklet_size'=>'required',
            'start_serial_no'=>'required',
            'quantity'=>'required'
        ];
    }
    public function messages()
    {
        return [
           'prefix_word.required'=>'Enter prefix',
           'booklet_size.required'=>'Enter book size',
           'start_serial_no.required'=>'Enter starting serial no',
           'quantity.required'=>'Enter quantity'
        ];
    }
}

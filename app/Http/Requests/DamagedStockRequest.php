<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DamagedStockRequest extends FormRequest
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
            'card_category'=>'required',
            'serial_no'=>'required',
            'type_damaged'=>'required',
            'remark'=>'required',
            'reg_no'=>'min:14|max:15',
        ];
    }

    public function messages()
    {
        return [
            "card_category.required"=>"Card Category is required",
            "serial_no.required"=>"Serial Number of card is required",
            'type_damaged.required'=>'Type is required',
            "remark.required"=>"Remark is required",
            'reg_no.min'=>'Registration no. should be of 14/15 charactes.',
            'reg_no.max'=>'Registration no. should be of 14/15 charactes.'
        ];
    }
}

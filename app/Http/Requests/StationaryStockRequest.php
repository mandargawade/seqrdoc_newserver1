<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StationaryStockRequest extends FormRequest
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
            'academic_year'=>'required',
            'date_of_received'=>'required',
            'serial_no_from'=>'required',
            'serial_no_to'=>'required',
            'quantity'=>'required',
        ];
    }

    public function messages()
    {
        return [
            "card_category.required"=>"Card Category is required",
            "academic_year.required"=>"Academic year is required",
            'date_of_received.required'=>'Date of received is required',
            "serial_no_from.required"=>"Serial no. from is required",
            'serial_no_to.required'=>'Serial no. to is required',
            'quantity.required'=>'Quantity is required'
        ];
    }
}

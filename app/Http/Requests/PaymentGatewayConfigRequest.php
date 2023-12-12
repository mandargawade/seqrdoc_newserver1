<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentGatewayConfigRequest extends FormRequest
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
             'opt_pg'=>'required',
             'amt_charge'=>'required|not_in:0',
             'opt_crenden'=>'required',
             'opt_wl'=>'required',
        ];
    }
    public function messages()
    {
        return [
            'opt_wl.required'=>'Please select payment gateway',
            'amt_charge.required'=>'Please enter amount to charge',
            'amt_charge.not_in'=>'Please enter amount to charge 0 above',
            'opt_crenden.required'=>'Please select crendentials',  
            'opt_pg.required'=>'Please select payment status', 
        ];
    }
}

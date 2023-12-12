<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Auth;
class PaymentGatewayRequest extends FormRequest
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
         //$id=$request['font_id'];
        $id=\Request::segment(3);
        $site_id=Auth::guard('admin')->user()->site_id; 
       
        return [
            'pg_name'=>'required|max:100|unique:payment_gateway,pg_name,'.$id.',id,site_id,'.$site_id,
            'opt_status'=>'required'
        ];
    }
    public function messages()
    {
        return [
           'pg_name.required'=>'Enter Name',
           'pg_name.unique'=>'Payment Gateway name alreday taken',
           'opt_status.required'=>'Select Status'
        ];
    }
}

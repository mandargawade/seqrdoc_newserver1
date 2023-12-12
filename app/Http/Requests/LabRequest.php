<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Auth;
class LabRequest extends FormRequest
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
        $id = $request->id;
        $site_id=Auth::guard('admin')->user()->site_id;
        $publish = 1;
          if(empty($id))
           {
                $rules = [
                    'lab_title'=>'required|max:100|unique:lab_table,lab_title,'.$id.',id,site_id,'.$site_id.',publish,'.$publish,
                    'status' => 'required',
                ];
           
            }
            else
            {
                $rules = [
                    'lab_title' => 'required|max:100|unique:lab_table,lab_title,'.$id.',id,site_id,'.$site_id.',publish,'.$publish,
                    'status' => 'required',
                ];

            }   
        return $rules;
    }

    public function messages()
    {
        return [
            "lab_title.required"=>"Title cannot be empty",
            "status.required"=>"Status cannot be empty",
        ];
    }
}

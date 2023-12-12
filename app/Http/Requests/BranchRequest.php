<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class BranchRequest extends FormRequest
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
        $id=$request['branch_id'];
        $degree_id=$request['degree_id'];
        // dd($request);
        return [
            'degree_id'=>'required',
            'branch_name_long'=>'required|unique:branch_master,branch_name_long,'.$id.',id,degree_id,'.$degree_id,
            'branch_name_short'=>'required|unique:branch_master,branch_name_short,'.$id.',id,degree_id,'.$degree_id,
        ];
    }

    public function messages()
    {
        return [
            "degree_id.required"=>"Degree is required",
            "branch_name_long.required"=>"Branch full name is required",
            'branch_name_short.required'=>'Branch Short name is required',
            "branch_name_long.unique"=>"Branch full name already exists",
            'branch_name_short.unique'=>'Branch Short name already exists'
        ];
    }
}

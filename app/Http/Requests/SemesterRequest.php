<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class SemesterRequest extends FormRequest
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
        $id=$request['semester_id'];
        // dd($request);
        return [
            'semester_name'=>'required|unique:semester_master,semester_name,'.$id,
            'semester_full_name'=>'required|unique:semester_master,semester_full_name,'.$id,
        ];
    }

    public function messages()
    {
        return [
            "semester_name.required"=>"Semester name required",
            'semester_full_name.required'=>'Semester full name required',
            "semester_name.unique"=>"Semester name already exists",
            'semester_full_name.unique'=>'Semester full name already exists'
        ];
    }
}

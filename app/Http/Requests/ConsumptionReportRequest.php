<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class ConsumptionReportRequest extends FormRequest
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
        $rules = [
            'exam'=>'required',
            'degree'=>'required',
            'branch'=>'required',
            'scheme'=>'required',
            'term'=>'required',
            'section'=>'required',
        ];
        if(!empty($request['fromDate']) && !empty($request['toDate'])){
            $rules['toDate'] = 'after_or_equal:fromDate';
        }
        // dd($request->all());
        return $rules;
    }

    public function messages()
    {
        return [
            "exam.required"=>"Exam is required",
            "degree.required"=>"Degree is required",
            'branch.required'=>'Branch is required',
            "scheme.required"=>"Scheme is required",
            "term.required"=>"Term is required",
            "section.required"=>"Section is required",
            'toDate.after_or_equal'=>'For date filter from & to date are required. From date should be less than to date.'
        ];
    }
}

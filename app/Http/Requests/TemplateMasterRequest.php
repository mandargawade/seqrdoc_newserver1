<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Auth;
class TemplateMasterRequest extends FormRequest
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
        /*ini_set('max_input_vars', 5000);
         print_r(count($_POST['actual_template_name']));
        exit;*/
        $id = $request['template_id'];
        $site_id=Auth::guard('admin')->user()->site_id;
        return [
            'template_name' => 'required|unique:template_master,actual_template_name,'.$id.',id,site_id,'.$site_id,
            'template_desc' => 'required',
            'height' => 'required|numeric|min:148|max:841',
           'width' => 'required|numeric|min:105|max:594',
        ];
    }

    public function messages()
    {
        return [
            "template_name.required"=>"Template name cannot be empty",
            "template_name.unique"=>"This Template Name already taken please try again",
            "template_desc.required"=>"Template description cannot be empty",
            "height.required"=>"Template height cannot be empty",
            "width.required"=>"Template width cannot be empty",
            "height.numeric"=>"Template height must be numeric",
            "width.numeric"=>"Template width must be numeric",
            "height.min"=>"Template height must be between 148 and 841",
            "width.min"=>"Template width must be between 105 and 594",
            "height.max"=>"Template height must be between 148 and 841",
            "width.max"=>"Template width must be between 105 and 594",
        ];
    }
}

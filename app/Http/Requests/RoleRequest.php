<?php

namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Auth;
class RoleRequest extends FormRequest
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
        $site_id=Auth::guard('admin')->user()->site_id;
        $id=\Request::segment(3);

         return [
                  'name' => 'required|unique:roles,name,'.$id.',id,site_id,'.$site_id,
                    'description' => 'required',
                    'permissions' => 'required',
                    'status' => 'required',
                ];
            
        
    }

    public function messages() {

        $messages = [
            'name.required' => "Please enter role",
            'name.unique' => "This name already exists",
            'description.required' => "Please enter description",
            'permissions.required' => "Please select permission",
            'status.required' => "Please select role status",
        ];
        return $messages;
    }
}

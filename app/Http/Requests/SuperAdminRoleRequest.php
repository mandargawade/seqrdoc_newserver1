<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class SuperAdminRoleRequest extends FormRequest
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
        $id=$request->id;
        // dd($request->all());
        switch ($this->method()) {
            case 'POST':
            {
                return [
                    'site_url' => 'required|unique:sites,site_url,'.$id.',site_id',
                    'permissions' => 'required',
                    'status' => 'required',
                ];
            }
            case 'PATCH':
            {
                return [
                    'site_url' => 'required|unique:sites,site_url,'.$id.',site_id',
                   
                    'permissions' => 'required',
                    'status' => 'required',
                ];
            }
            default:break;
        }
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

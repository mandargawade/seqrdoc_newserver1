<?php

namespace App\Http\Controllers\admin;

use App\Exports\BackgroundTemplateExport;
use App\Http\Controllers\Controller;
use App\models\BackgroundTemplateMaster;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Response;
use Validator;
use Auth;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;

class BackgroundTemplateMasterController extends Controller
{
    public function index(Request $request)
    {
    	if($request->ajax())
        {
            $data = $request->all();
            $where_str = '1 = ?';
            $where_params = [1];


            if($request->has('sSearch'))
            {
                $search = $request->get('sSearch');
                $where_str .= " and ( background_name like \"%{$search}%\""
                           . ")";
            }

            $status=$request->get('status');

            if($status==1)
            {
                $status=1;
                $where_str.= " and (background_template_master.status = '$status')";
            }
            else if($status==0 && $status!=null)
            {
                $status=0;
                $where_str.=" and (background_template_master.status = '$status')";

            }  
            $auth_site_id=Auth::guard('admin')->user()->site_id;
            
            $backgroundTemplateMaster = BackgroundTemplateMaster::select('id','background_name','image_path','width','height','status')
                ->where('site_id',$auth_site_id)
                ->whereRaw($where_str, $where_params);
                
            $backgroundTemplateMaster_count = BackgroundTemplateMaster::select('id')
                                        ->whereRaw($where_str, $where_params)
                                         ->where('site_id',$auth_site_id)
                                        ->count();
           
            $columns = ['id','background_name','image_path','width','height','status'];

            if($request->has('iDisplayStart') && $request->get('iDisplayLength') !='-1'){
                $backgroundTemplateMaster = $backgroundTemplateMaster->take($request->get('iDisplayLength'))->skip($request->get('iDisplayStart'));
            }

            if($request->has('iSortCol_0')){
                for ( $i = 0; $i < $request->get('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->get('iSortCol_' . $i)];
                    if (false !== ($index = strpos($column, ' as '))) {
                        $column = substr($column, 0, $index);
                    }
                    $backgroundTemplateMaster = $backgroundTemplateMaster->orderBy($column,$request->get('sSortDir_'.$i));
                }
            }

            $backgroundTemplateMaster = $backgroundTemplateMaster->get();
            
             $response['iTotalDisplayRecords'] = $backgroundTemplateMaster_count;
             $response['iTotalRecords'] = $backgroundTemplateMaster_count;

            $response['sEcho'] = intval($request->get('sEcho'));

            $response['aaData'] = $backgroundTemplateMaster;

            return $response;
        }
    	return view('admin.backgroundTemplateMaster.index');
    }
    public function create()
    {
        return view('admin.backgroundTemplateMaster.create');
    }

    public function store(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    { 
        //check upload to aws/local (var(get_file_aws_local_flag) coming through provider)
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

    	$data = $request->all();

        $id=null;
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $this->validate($request,
         [  
        'background_name' =>'required | unique:background_template_master,background_name,'.$id.',id,site_id,'.$auth_site_id,
        'image_path' =>'required | image | mimes:jpeg,png,jpg',
        'width' =>'required | numeric|min:50|max:594',
        'height' =>'required | numeric|min:50|max:841',
        'status' =>'required',
         ]      
         ,BackgroundTemplateMaster::$messages);
        $backgroundTemplateMaster = new BackgroundTemplateMaster($data);
        if($request->hasfile('image_path'))
        {
            if($get_file_aws_local_flag->file_aws_local == '1'){
                $path ='/'.$subdomain[0].'/backend/canvas/bg_images/';
            }
            else{
                $bg_directory = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/';

                if(!is_dir($bg_directory)){
                    //Directory does not exist, then create it.
                    mkdir($bg_directory, 0777);
                }
                $path = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/';
            }

            $file = $request->file('image_path');
            $filename = $file->getClientOriginalName();
            $filename = str_replace(' ', '_', $filename);
            $filename = str_replace(' ', '_', $data['background_name']).'_'.$filename;
            $backgroundTemplateMaster->image_path = $filename;
            $path=$path.$filename;
            if($get_file_aws_local_flag->file_aws_local == '1'){
                Storage::disk('s3')->put($path, file_get_contents($file));
            }
            else{
                \File::copy($file,$path);
            }

        }
        $backgroundTemplateMaster->created_by = 1;
        $backgroundTemplateMaster->updated_by = 1;
        $backgroundTemplateMaster->site_id =$auth_site_id;
        $backgroundTemplateMaster->save();
        return redirect()->route('background-master.index')->with('message','Background Template Added Successfully.')
                                                        ->with('message_type','success'); 
    }

    public function show(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    {
        $data = $request->all();
        $id = $data['id'];
        $bgTemplate = BackgroundTemplateMaster::find($id)->toArray();
        //check upload to aws/local (var(get_file_aws_local_flag) coming through provider)
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        return Response::json(['success'=>true,'resp'=>$bgTemplate,'get_file_aws_local_flag'=>$get_file_aws_local_flag]);
    }

    public function edit($id,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    { 
        //check upload to aws/local (var(get_file_aws_local_flag) coming through provider)
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        $backgroundTemplate = BackgroundTemplateMaster::find($id);
        $this->args['backgroundTemplate'] = $backgroundTemplate;
        $this->args['get_file_aws_local_flag'] = $get_file_aws_local_flag;
        return view('admin.backgroundTemplateMaster.edit',$this->args);
    }

    public function update(Request $request,$id,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    {
        //check upload to aws/local (var(get_file_aws_local_flag) coming through provider)
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $data = $request->all(); 
        
        $site_id=Auth::guard('admin')->user()->site_id;
        $rules = [
            'background_name' =>'required | unique:background_template_master,background_name, '.$id.',id,site_id,'.$site_id,
            'image_path' =>'image | mimes:jpeg,png,jpg',
            'width' =>'required | numeric|min:50|max:594',
            'height' =>'required | numeric|min:50|max:841',
            'status' =>'required',
        ];

        $messages = [
            'background_name.required' =>"Background Template Name required",
            'image_path.image' =>'Image must be an Image(jpeg, png, jpg)',
            'width.required' =>'Width required',
            'height.required' =>'Height required',
            'status.required' =>'Satus required',
        ];
        
        $this->validate($request,$rules,$messages);

        $backgroundTemplate = BackgroundTemplateMaster::find($id);

        if($request->hasfile('image_path'))
        {
            $path ='/'.$subdomain[0].'/backend/canvas/bg_images/';
            $file = $request->file('image_path');
            $filename = $file->getClientOriginalName();
            $filename = str_replace(' ', '_', $filename);
            $data['background_name'] = str_replace(' ', '_', $data['background_name']);
            $filename = str_replace(' ', '_', $data['background_name']).'_'.$filename;
            $data['image_path'] = $filename;
            $path_main=$path.$filename;
             $backgroundTemplate->image_path = $filename;
            if($get_file_aws_local_flag->file_aws_local == '1'){
                Storage::disk('s3')->put($path_main, file_get_contents($file));
               
                $delete_backgroundTemplate_image_path = $path.$backgroundTemplate['image_path'];
                if(Storage::disk('s3')->exists($delete_backgroundTemplate_image_path))
                {
                    Storage::disk('s3')->delete($delete_backgroundTemplate_image_path);
                }
            }
            else{
                \File::copy($file,public_path().$path_main);

                $delete_backgroundTemplate_image_path = public_path().$path.$backgroundTemplate['image_path'];
                
                if(file_exists($delete_backgroundTemplate_image_path))
                {
                    unlink($delete_backgroundTemplate_image_path);
                }
            }
        }

        $backgroundTemplate->background_name = $data['background_name'];
       
        $backgroundTemplate->width = $data['width'];
        $backgroundTemplate->height = $data['height'];
        $backgroundTemplate->status = $data['status'];
        $backgroundTemplate->background_opicity = $data['background_opicity'];
        $backgroundTemplate->save();

        return redirect()->route('background-master.index')->with('message','Background Template Updated Successfully.')
                                                        ->with('message_type','success');
    }

    public function delete(){}

    public function excelExport()
    {
        $sheet_name = 'BackgroundTemplate_'. date('Y_m_d_H_i_s').'.xls'; 
        return Excel::download(new BackgroundTemplateExport(),$sheet_name);
    }
}

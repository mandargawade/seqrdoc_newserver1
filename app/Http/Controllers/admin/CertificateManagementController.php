<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\StudentTable;
use App\models\SbStudentTable;
use App\models\SystemConfig;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CertificateManagementExport;
use QrCode;
use Auth;

use App\models\TemplateMaster;
use App\models\SuperAdmin;

use DB,Event;

use App\Http\Requests\ExcelValidationRequest;
use App\Http\Requests\MappingDatabaseRequest;
use App\Http\Requests\TemplateMapRequest;
use App\Http\Requests\TemplateMasterRequest;
use App\Imports\TemplateMapImport;
use App\Imports\TemplateMasterImport;
use App\Jobs\PDFGenerateJob;
use App\models\BackgroundTemplateMaster;
use App\Events\BarcodeImageEvent;
use App\Events\TemplateEvent;
use App\models\FontMaster;
use App\models\FieldMaster;
use App\models\User;

use TCPDF,Session;

use App\Jobs\PreviewPDFGenerateJob;
use App\Exports\TemplateMasterExport;
use Storage;

use App\Library\Services\CheckUploadedFileOnAwsORLocalService;




class CertificateManagementController extends Controller
{
    public function index(Request $request)
    {

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

    	if($request->ajax())
        {
            $data = $request->all();
            $where_str    = "1 = ?";
            $where_params = array(1); 


            if($request->has('sSearch'))
            {
                $search = $request->get('sSearch');
                $where_str .= " and ( certificate_filename like \"%{$search}%\""
                			. " or serial_no like \"%{$search}%\""
                           . ")";
            }

            $status=$request->get('status');
            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                if($status==1)
                {
                    $status=1;
                }
                else if($status==0 && $status!=null)
                {
                    $status=0;
                }
                $condition = " and (sb_student_table.status = '$status')";
            }
            else
            {   
                if($status==1)
                {
                    $status=1;
                }
                else if($status==0 && $status!=null)
                {
                    $status=0;
                }
                $condition = " and (student_table.status = '$status')";
            }
            $publish = "and (student_table.publish='1')";

                    
                $where_str.= $condition;
                $where_str.= $publish;

            $auth_site_id=Auth::guard('admin')->user()->site_id;

            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){  
                $certificate = SbStudentTable::select('sb_student_table.id','serial_no','certificate_filename','actual_template_name','sb_student_table.status','sb_student_table.created_at','sb_student_table.template_id')
                                    ->leftjoin('template_master','template_master.id','sb_student_table.template_id')
                                    ->where('sb_student_table.site_id',$auth_site_id)
                                    ->whereRaw($where_str, $where_params);

                $certificate_count = SbStudentTable::select('id')
                                            ->leftjoin('template_master','template_master.id','sb_student_table.template_id')
                                            ->whereRaw($where_str, $where_params)
                                            ->where('sb_student_table.site_id',$auth_site_id)
                                            ->count();
                $columns = ['serial_no','certificate_filename','actual_template_name','status','sb_student_table.created_at','id'];
            }
            else
            {
                $certificate = StudentTable::select('student_table.id','serial_no','certificate_filename','actual_template_name','student_table.status','student_table.created_at','student_table.template_id')
                                    ->leftjoin('template_master','template_master.id','student_table.template_id')
                                    ->where('student_table.site_id',$auth_site_id)
                                    ->whereRaw($where_str, $where_params);

                $certificate_count = StudentTable::select('id')
                                            ->leftjoin('template_master','template_master.id','student_table.template_id')
                                            ->whereRaw($where_str, $where_params)
                                            ->where('student_table.site_id',$auth_site_id)
                                            ->count();
                $columns = ['serial_no','certificate_filename','actual_template_name','status','student_table.created_at','id'];
            }

            if($request->has('iDisplayStart') && $request->get('iDisplayLength') !='-1'){
                $certificate = $certificate->take($request->get('iDisplayLength'))->skip($request->get('iDisplayStart'));
            }
            
            if($request->has('iSortCol_0')){
                for ( $i = 0; $i < $request->get('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->get('iSortCol_' . $i)];
                    if (false !== ($index = strpos($column, ' as '))) {
                        $column = substr($column, 0, $index);
                    }

                    $certificate = $certificate->orderBy($column,$request->get('sSortDir_'.$i));
                }
            }

            $certificate = $certificate->get()->toArray();
            
            foreach ($certificate as $key => $value) {
            	if ($value['status']==1 || $value['status']=='1') {
            		$certificate[$key]['status'] = 'Active';
            	}
            	else
            	{
            		$certificate[$key]['status'] = 'Inactive';
            	}
            }

            $response['iTotalDisplayRecords'] = $certificate_count;
            $response['iTotalRecords'] = $certificate_count;

            $response['sEcho'] = intval($request->get('sEcho'));

            $response['aaData'] = $certificate;

            return $response;
        }
    	return view('admin.certificateManagement.index');
    }

    public function update(Request $request)
    {
    	$data = $request->all();

        if ($data['status'] == "Active") {
            $data['status'] = '1';
            $status = '0';
        }
        else
        {
            $data['status'] = '0';
            $status = '1';
        }
    	$status_update = StudentTable::where('id',$data['id'])
    							->where('status',$data['status'])
    							->update(['status'=>$status]);

    	return response(['success'=>true]);
    }

    public function excelExport()
    {
        $sheet_name = 'CertificateManagement_'. date('Y_m_d_H_i_s').'.xlsx'; 
        
        return Excel::download(new CertificateManagementExport(),$sheet_name,'Xlsx');
    }

    public function generateQRCode(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    {
        //check upload to aws/local (var(get_file_aws_local_flag) coming through provider)
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $data = $request->all();

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        
        if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){  
            $studentData = SbStudentTable::select('key')->where('id',$data['id'])->get()->first();
        }
        else
        {
             $studentData = StudentTable::select('key')->where('id',$data['id'])->get()->first();
        }
        
        
        $key = $studentData['key'];
        
        $temp_path = public_path().'/backend/qr/'.$key.'.png';

        QrCode::format('png')
                ->size(200)
                ->generate($key,$temp_path);

        if($get_file_aws_local_flag->file_aws_local == '1'){      
            $aws_qr_path = '/'.$subdomain[0].'/backend/canvas/images/qr/'.$key.'.png';

            $aws_qr = \Storage::disk('s3')->put($aws_qr_path, file_get_contents($temp_path), 'public');
            
            $get_qr_image = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/images/qr/'.$key.'.png';
        }
        else{

            $local_server_qr_path = public_path().'/'.$subdomain[0].'/backend/canvas/images/qr/'.$key.'.png';
            $localQrDir= public_path().'/'.$subdomain[0].'/backend/canvas/images/qr';

            if(!is_dir($localQrDir)){

                mkdir($localQrDir, 0777);
            }
            
            
            copy($temp_path,$local_server_qr_path);
            
            $get_qr_image = 'http://'.$subdomain[0].'.seqrdoc.com/'.$subdomain[0].'/backend/canvas/images/qr/'.$key.'.png';

        }

        @unlink($temp_path);

       
        $this->args['key'] = $key;
        $this->args['get_qr_image'] = $get_qr_image;
        return response(['success'=>true,'data'=>['key'=>$key,'path'=>$get_qr_image]]);
    }

}

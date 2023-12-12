<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\ExceUploadHistory;
use Auth;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use App\models\SystemConfig;
use App\models\SbExceUploadHistory;


//show printerinformation
class PrinterReportController extends Controller
{
    public function index(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    {
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        $file_aws_local = $get_file_aws_local_flag['file_aws_local'];
        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $config = $systemConfig['sandboxing'];

    	if($request->ajax())
        {
            $data = $request->all();
            $where_str = '1 = ?';
            $where_params = [1];
       
            $table_name = 'excelupload_history';
           
             if (!empty($request->input('sSearch')))
            {
                $search = $request->get('sSearch');
                $where_str .= " and ( ".$table_name.".template_name like \"%{$search}%\""
                                . " or ".$table_name.".user like \"%{$search}%\""
                                . " or ".$table_name.".excel_sheet_name like \"%{$search}%\""
                                . " or ".$table_name.".pdf_file like \"%{$search}%\""
                                . " or ".$table_name.".created_at like \"%{$search}%\""
                                . " or ".$table_name.".no_of_records like \"%{$search}%\""
                               . ")";
            }
 
            $auth_site_id=Auth::guard('admin')->user()->site_id;
            
                $printingReport = ExceUploadHistory::select('excelupload_history.id','template_master.actual_template_name as template_name','excelupload_history.excel_sheet_name','excelupload_history.pdf_file','excelupload_history.user','excelupload_history.no_of_records','excelupload_history.created_at','template_master.id as template_id','excelupload_history.template_name as template_name_excel')
                                    ->leftjoin('template_master','template_master.id','excelupload_history.template_name')
                                    ->whereRaw($where_str, $where_params);
                                

                $printingReport_count = ExceUploadHistory::select('id')
                                        ->whereRaw($where_str, $where_params)
                                        ->count();
            
                                        
           
            $columns = ['id','template_name','pdf_file','excel_sheet_name','user','no_of_records','created_at'];

            if($request->has('iDisplayStart') && $request->get('iDisplayLength') !='-1'){
                $printingReport = $printingReport->take($request->get('iDisplayLength'))->skip($request->get('iDisplayStart'));
            }

            if($request->has('iSortCol_0')){
                for ( $i = 0; $i < $request->get('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->get('iSortCol_' . $i)];
                    if (false !== ($index = strpos($column, ' as '))) {
                        $column = substr($column, 0, $index);
                    }
                    $printingReport = $printingReport->orderBy($column,$request->get('sSortDir_'.$i));
                }
            }

            $printingReport = $printingReport->get();
            foreach ($printingReport as $key => $value) {
               $value['created_on'] =  date("d M Y h:i A", strtotime($value['created_at']));
            }

             $response['iTotalDisplayRecords'] = $printingReport_count;
             $response['iTotalRecords'] = $printingReport_count;

            $response['sEcho'] = intval($request->get('sEcho'));

            $response['aaData'] = $printingReport;
            
            return $response;
        }
    	return view('admin.printerReport.index',compact('file_aws_local','config'));
    }
}

<?php

namespace App\Http\Controllers\admin;
// created by Rushik Joshi
// date : 19-12-2019
// process excel and merge excel
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\ExcelMergeLogs;
use App\Http\Request\processExcelRequest;
use App\Events\processExcelEvent;
use App\Events\processExcelRaisoniEvent;
use DB;
use Event;
use Auth;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;

class ProcessExcelController extends Controller
{
	// function for list excel merge logs
    public function index(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){

        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
    	if($request->ajax()){

            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and (raw_excel like \"%{$search}%\""
                . " or processed_excel like \"%{$search}%\""
                 . " or total_unique_records like \"%{$search}%\""
                 . " or date_time like \"%{$search}%\""
                 . " or status like \"%{$search}%\""
                . ")";
            }

            $auth_site_id=Auth::guard('admin')->user()->site_id;                                 
            //for serial number
            DB::statement(DB::raw('set @rownum=0'));

            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'raw_excel','processed_excel','total_unique_records','date_time','status'];
            

            $process_excel_count = ExcelMergeLogs::select($columns)
            ->whereRaw($where_str, $where_params)
            ->where('site_id',$auth_site_id)
            ->count();

            $process_excel_list = ExcelMergeLogs::select($columns)
            ->where('site_id',$auth_site_id)
            ->whereRaw($where_str, $where_params);
            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $process_excel_list = $process_excel_list->take($request->input('iDisplayLength'))
                ->skip($request->input('iDisplayStart'));
            }          

            if($request->input('iSortCol_0')){
                $sql_order='';
                for ( $i = 0; $i < $request->input('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->input('iSortCol_' . $i)];
                    if(false !== ($index = strpos($column, ' as '))){
                        $column = substr($column, 0, $index);
                    }
                    $process_excel_list = $process_excel_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            }
            $process_excel_list = $process_excel_list->get();

            $response['iTotalDisplayRecords'] = $process_excel_count;
            $response['iTotalRecords'] = $process_excel_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $process_excel_list->toArray();

            return $response;
        }
        
    	return view('admin.processExcel.index',compact('get_file_aws_local_flag'));
    }
    // use for merging two sheets of excel using registration no.
    function mergeExcel(Request $request){

    	$domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $merge_excel_data = $request->all();
        //    print_r($merge_excel_data);
        if($subdomain[0]=="raisoni"||$subdomain[0]=="demo"){
        $merge_excel_response = Event::dispatch(new processExcelRaisoniEvent($merge_excel_data));

        }else{
        $merge_excel_response = Event::dispatch(new processExcelEvent($merge_excel_data));

        }    

    	return response()->json(['data'=>$merge_excel_response]);	
    }
}

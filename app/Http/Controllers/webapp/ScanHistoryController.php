<?php

namespace App\Http\Controllers\webapp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\ScannedHistory;
use App\models\StudentTable;
use Response,Auth;
use QrCode;
class ScanHistoryController extends Controller
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
                $where_str  .= " and ( scanned_data like \"%{$search}%\""
                            . " or scan_result like \"%{$search}%\""
                            . " or date_time like \"%{$search}%\""
                           . ")";
            }

            $device_type=$request->get('device_type');
            $user_id=Auth::guard('webuser')->user()->id;
            $where_str.= " and (scanned_history.scan_by = '$user_id')";
            
            if($device_type=='WebApp')
            {
                $device_type="WebApp";
                $where_str.= " and (scanned_history.device_type = '$device_type')";
                
            }
            else if($device_type=='android')
            {
                $device_type='android';
                $where_str.= " and (scanned_history.device_type = '$device_type')";

            }  
            else if($device_type=='iOS')
            {
            	$device_type='ios';
                $where_str.= " and (scanned_history.device_type = '$device_type')";
            }
            
            $scanHistory = ScannedHistory::select('id','device_type','scanned_data','scan_result','date_time')
                                		->whereRaw($where_str, $where_params);
            
            $scanHistory_count = ScannedHistory::select('id')
                                        ->whereRaw($where_str, $where_params)
                                        ->count();
           
            $columns = ['id','device_type','scanned_data','scan_result','date_time'];

            if($request->has('iDisplayStart') && $request->get('iDisplayLength') !='-1'){
                $scanHistory = $scanHistory->take($request->get('iDisplayLength'))->skip($request->get('iDisplayStart'));
            }

            if($request->has('iSortCol_0')){
                for ( $i = 0; $i < $request->get('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->get('iSortCol_' . $i)];
                    if (false !== ($index = strpos($column, ' as '))) {
                        $column = substr($column, 0, $index);
                    }
                    $scanHistory = $scanHistory->orderBy($column,$request->get('sSortDir_'.$i));
                }
            }

            $scanHistory = $scanHistory->get();
            foreach ($scanHistory as $key => $value) {
               $value['date_time'] =  date("d M Y h:i A", strtotime($value['date_time']));
            }

             $response['iTotalDisplayRecords'] = $scanHistory_count;
             $response['iTotalRecords'] = $scanHistory_count;

            $response['sEcho'] = intval($request->get('sEcho'));

            $response['aaData'] = $scanHistory;

            return $response;
        }

    	return view('webapp.scanHistory.index');
    }

    public function show(Request $request)
    {

        $hostUrl = \Request::getHttpHost();
        $subdomain = explode('.', $hostUrl);

    	$data = $request->all();
        
    	$student_info = StudentTable::where('key',$data['key'])->get()->first();

        $qr_path = config('constant.qrcode_show_webapp');
        if($subdomain[0]=='monad'){
        $pdf_path = config('constant.monad_base_url').'pdf_file';
        }else{
            $qr_path_generate = public_path().'/'.$subdomain[0].'/backend/canvas/images/qr/'.$data['key'].'.png';
            if(!file_exists($qr_path_generate)){

                QrCode::format('png')
                        ->size(200)
                        ->generate($data['key'],$qr_path_generate);

            }
        $pdf_path = config('constant.show_pdf'); 
        }
        
        return Response::json(['success'=>true,'resp'=>$student_info,'qr_path'=>$qr_path,'pdf_path'=>$pdf_path]);
    }
}

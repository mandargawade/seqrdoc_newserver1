<?php

namespace App\Http\Controllers\verify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\raisoni\ScanningRequests;
use App\models\raisoni\VerificationDocuments;
use DB,Auth;
class ScanHistoryController extends Controller
{
    public function index(Request $request){

    	if($request->ajax())
        {
            $data = $request->all();
            
            $where_str = '1 = ?';
            $where_params = [1];


            if($request->has('sSearch'))
            {
                $search = $request->get('sSearch');
                 $where_str .= " and (request_number like \"%{$search}%\""
                 . " or device_type like \"%{$search}%\""
                 . " or total_amount like \"%{$search}%\""
                 . ")";
            }
            $user_id=Auth::guard('webuser')->user()->id;

            $where_str .= " and (payment_status='Paid')";
            $where_str .= " and (user_id=".$user_id.")";
            
            if (isset($request['fromDate']) && isset($request['toDate']) && !empty($request['fromDate']) && !empty($request['toDate'])) {
				$fromDate = date('Y-m-d', strtotime($request['fromDate']));
				$toDate = date('Y-m-d', strtotime($request['toDate']));
				$where_str .= " AND (DATE(created_date_time) >= '" . $fromDate . "' AND DATE(created_date_time) <= '" . $toDate . "')";
			}
            
            DB::statement(DB::raw('set @rownum=0'));   
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'id','request_number','device_type','no_of_documents','created_date_time','total_amount'];
            $backgroundTemplateMaster = ScanningRequests::select($columns)
                ->whereRaw($where_str, $where_params);
                
            $backgroundTemplateMaster_count = ScanningRequests::select('id')
                                        ->whereRaw($where_str, $where_params)
                                        ->count();
           
           

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
    	return view('verify.scan_history');
    }
    public function info(Request $request){

    	$request_id = (isset($request['id'])) ? $this->sanitizeVar($request['id']) : '';
		if (!empty($request_id)) {
			$requestData = $this->getRequest($request_id);
			
			$requestDetails = $this->getRequestDetails($request_id);
			if (isset($requestData) && isset($requestDetails)) {
				$message = array('type' => 'success', 'message' => 'success', 'requestData' => $requestData, 'requestDetails' => $requestDetails);
			} else {
				$message = array('type' => 'error', 'message' => 'Something went wrong.');
			}
		} else {
			$message = array('type' => 'error', 'message' => 'Requst id not found.');
		}
    	echo json_encode($message);
    }
    public function getRequest($request_id){


    	$sql = DB::select( DB::raw("SELECT sr.*, DATE_FORMAT(sr.created_date_time, '%d %b %Y %h:%i %p') as created_date_time,ut.fullname,ut.l_name,tr.trans_id_ref as payment_transaction_id,tr.trans_id_gateway as payment_gateway_id, tr.payment_mode,tr.amount,DATE_FORMAT(tr.created_at, '%d %b %Y %h:%i %p') as payment_date_time FROM scanning_requests as sr INNER JOIN user_table as ut ON sr.user_id=ut.id LEFT JOIN transactions as tr ON sr.request_number=tr.student_key AND tr.trans_status=1 WHERE sr.payment_status='Paid' AND  sr.id='" . $request_id . "'") );

    	
    	 return $sql[0];

    }
    public function getRequestDetails($request_id){
         $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        if($subdomain[0]=="monad"){

        \DB::statement("SET SQL_MODE=''");
        $sql = DB::select( DB::raw("SELECT sd.id, (CASE 
                            WHEN sd.is_valid = 0 THEN NULL
                            WHEN sd.is_valid = 1 THEN certificate_filename
                            ELSE NULL
                        END) AS pdf_path, sd.is_valid,sd.document_key,st.path as qr_code_path FROM scanned_documents as sd LEFT JOIN student_table as st ON sd.document_key=st.key OR (sd.document_key = st.serial_no AND st.status=1) where sd.request_id='" . $request_id. "' GROUP BY sd.id") );
        
        }else{
    	$sql = DB::select( DB::raw("SELECT sd.*, st.certificate_filename as pdf_path,st.path as qr_code_path FROM scanned_documents as sd INNER JOIN student_table as st ON sd.document_key = st.key WHERE sd.request_id='" . $request_id . "'") );
        }
		 return $sql;
    }
    public function sanitizeVar($data) {
		$data = trim($data);
		$data = stripslashes($data);
		$data = htmlspecialchars($data);
		return $data;
	}
}

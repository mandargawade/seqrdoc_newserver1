<?php

namespace App\Http\Controllers\verify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\raisoni\VerificationRequests;
use App\models\raisoni\VerificationDocuments;
use DB;
use Auth;

class VerificationStatusController extends Controller
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
                 . " or student_name like \"%{$search}%\""
                 . " or total_amount like \"%{$search}%\""
                 . ")";
            }
            $user_id= Auth::guard('webuser')->user()->id;

            $where_str .= " and (payment_status='Paid')";
            $where_str .= " and (user_id=".$user_id.")";
            if (isset($request['fromDate']) && isset($request['toDate']) && !empty($request['fromDate']) && !empty($request['toDate'])) {
				$fromDate = date('Y-m-d', strtotime($request['fromDate']));
				$toDate = date('Y-m-d', strtotime($request['toDate']));
				$where_str .= " AND (DATE(verification_requests.created_date_time) >= '" . $fromDate . "' AND DATE(verification_requests.created_date_time) <= '" . $toDate . "')";
			}

            DB::statement(DB::raw('set @rownum=0'));   
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'id','request_number','student_name','no_of_documents','created_date_time','total_amount','verification_status'];
            $backgroundTemplateMaster = VerificationRequests::select($columns)
                ->whereRaw($where_str, $where_params);
            
            $backgroundTemplateMaster_count = VerificationRequests::select('id')
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
            $backgroundTemplateMaster = $backgroundTemplateMaster->orderBy('id','DESC');
            $backgroundTemplateMaster = $backgroundTemplateMaster->get();
            
             $response['iTotalDisplayRecords'] = $backgroundTemplateMaster_count;
             $response['iTotalRecords'] = $backgroundTemplateMaster_count;

            $response['sEcho'] = intval($request->get('sEcho'));

            $response['aaData'] = $backgroundTemplateMaster;

            return $response;
        }
    	return view('verify.verification_status');
    }
    public function info(Request $request){

    	$request_id = (isset($request['id'])) ? $request['id'] : '';

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


    	$sql = DB::select( DB::raw("SELECT vr.*,DATE_FORMAT(vr.created_date_time, '%d %b %Y %h:%i %p') as created_date_time,DATE_FORMAT(vr.updated_date_time, '%d %b %Y %h:%i %p') as updated_date_time,dm.degree_name,bm.branch_name_long,ut.fullname,ut.l_name,tr.trans_id_ref as payment_transaction_id,tr.trans_id_gateway as payment_gateway_id, tr.payment_mode,tr.amount,DATE_FORMAT(tr.created_at, '%d %b %Y %h:%i %p') as payment_date_time,a.username FROM verification_requests as vr
										INNER JOIN user_table as ut ON vr.user_id=ut.id
										INNER JOIN degree_master as dm ON vr.degree=dm.id
										INNER JOIN branch_master as bm ON vr.branch=bm.id
										LEFT JOIN transactions as tr ON vr.request_number=tr.student_key AND tr.trans_status=1
										LEFT JOIN admin_table as a ON vr.updated_by=a.id
									 WHERE vr.id='" . $request_id . "'") );

    	
    	 return $sql[0];

    }
    public function getRequestDetails($request_id){

    	$sql = DB::select( DB::raw("SELECT vd.*,em.session_name as exam_name_display,sm.semester_name as semester_name_display,rt.transaction_ref_id,rt.request_id as refund_request_id,rt.transaction_id_payu,rt.status,rt.created_date_time as refund_date_time,rt.updated_date_time as refund_updated_date_time FROM verification_documents as vd	
		  LEFT JOIN exam_master as em ON vd.exam_name = em.session_no
		  LEFT JOIN semester_master as sm ON vd.semester = sm.id
		  LEFT JOIN refund_transactions as rt ON vd.refund_id = rt.id AND rt.status_code='1' AND vd.is_refunded='1'
		  WHERE vd.request_id='" . $request_id . "'") );

		 return $sql;
    }
}

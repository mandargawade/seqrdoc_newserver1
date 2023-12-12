<?php

namespace App\Http\Controllers\admin\raisoni;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\raisoni\VerificationRequests;
use App\models\raisoni\VerificationDocuments;
use App\models\raisoni\SemesterMaster;
use App\models\raisoni\RefundTransaction;
use App\models\User;
use DB;
use Auth;
use Config;
class OldDocumentsController extends Controller
{
    public function index(Request $request){
    	if($request->ajax()){
		    $where_str    = "1 = ?";
		    $where_params = array(1); 

		    if (!empty($request->input('sSearch')))
		    {
		        $search     = $request->input('sSearch');
		        $where_str .= " and ( verification_requests.request_number like \"%{$search}%\""
		        . " or user_table.fullname like \"%{$search}%\""
		        . " or verification_requests.device_type like \"%{$search}%\""
		        . " or verification_requests.no_of_documents like \"%{$search}%\""
		        . ")";
		    }  
		    $where_str .= " and (verification_requests.payment_status='" . $request['payment_status'] . "' AND verification_requests.verification_status='" . $request['verification_status'] . "')";
		    if (isset($request['fromDate']) && isset($request['toDate']) && !empty($request['fromDate']) && !empty($request['toDate'])) {
				$fromDate = date('Y-m-d', strtotime($request['fromDate']));
				$toDate = date('Y-m-d', strtotime($request['toDate']));
				$where_str .= " AND (DATE(verification_requests.created_date_time) >= '" . $fromDate . "' AND DATE(verification_requests.created_date_time) <= '" . $toDate . "')";
			}
		     //for serial number
            DB::statement(DB::raw('set @rownum=0'));   
		    $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'verification_requests.id','verification_requests.request_number','user_table.fullname','verification_requests.student_name','verification_requests.registration_no','verification_requests.verification_status'];

		    $degree_master_count = VerificationRequests::select($columns)
		    	->leftjoin('user_table','verification_requests.user_id','=','user_table.id')
		    	->where('verification_requests.payment_status', "Paid")
		        ->whereRaw($where_str, $where_params)
		        ->count();

		    $degree_master_list = VerificationRequests::select($columns)
		    		->leftjoin('user_table','verification_requests.user_id','=','user_table.id')
		         ->where('verification_requests.payment_status', "Paid")
		          ->whereRaw($where_str, $where_params);

		    if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
		        $degree_master_list = $degree_master_list->take($request->input('iDisplayLength'))
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
		            $degree_master_list = $degree_master_list->orderBy($column,$request->input('sSortDir_'.$i));   
		        }
		    } 
		    $degree_master_list = $degree_master_list->get();
		    foreach ($degree_master_list->toArray() as $key => $value) {
		    	$result = $this->documentVerificationCount($value['id']);
		    	$degree_master_list[$key]['verification_count'] = $result;
		    }

		    $response['iTotalDisplayRecords'] = $degree_master_count;
		    $response['iTotalRecords'] = $degree_master_count;
		    $response['sEcho'] = intval($request->input('sEcho'));
		    $response['aaData'] = $degree_master_list->toArray();
		    
		    return $response;
		}
    	return view('admin.raisoni.old-documents.index');
    }

	public function documentVerificationCount($request_number){
    	$sql1 = VerificationDocuments::where('result_found_status','!=','Pending')->where('request_id',$request_number)->count();

    	$sql2 = VerificationDocuments::where('request_id',$request_number
    )->count();

    	if($sql1){
    		$msg = $sql1 . ' out of ' . $sql2;
    	}else{
    		$msg = '0 out of ' . $sql2;
    	}
    	return $msg;
    }
    public function documentCount(Request $request){
    	$sql1 = VerificationDocuments::where('result_found_status','!=','Pending')->where('request_id',$request['request_id'])->count();

    	$sql2 = VerificationDocuments::where('request_id',$request['request_id'])->count();

    	if($sql1){
    		$msg = $sql1 . ' out of ' . $sql2;
    	}else{
    		$msg = '0 out of ' . $sql2;
    	}
    	$message = array('type' => 'success', 'message' =>$msg);
    	echo json_encode($message);
    }
    public function infoData(Request $request){

    	$request_id = (isset($request['id'])) ? $request['id'] : '';
		if (!empty($request_id)) {
			
			$requestData = $this->getRequest($request_id);
			$requestDetails =  $this->getRequestDetails($request_id);
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
    public function editData(Request $request){
    	$request_id = (isset($request['id'])) ? $request['id'] : '';
		if (!empty($request_id)) {
			
			
			$requestData = $this->getRequest($request_id);
			
			$requestDetails =  $this->getRequestDetails($request_id);
			
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
    function getRequest($request_id) {
 


		 $sql = DB::select( DB::raw("SELECT vr.*,DATE_FORMAT(vr.created_date_time, '%d %b %Y %h:%i %p') as created_date_time,DATE_FORMAT(vr.updated_date_time, '%d %b %Y %h:%i %p') as updated_date_time,dm.degree_name,bm.branch_name_long,ut.fullname,ut.l_name,tr.trans_id_ref as payment_transaction_id,tr.trans_id_gateway as payment_gateway_id, tr.payment_mode,tr.amount,DATE_FORMAT(tr.created_at, '%d %b %Y %h:%i %p') as payment_date_time,a.username FROM verification_requests as vr INNER JOIN user_table as ut ON vr.user_id=ut.id INNER JOIN degree_master as dm ON vr.degree=dm.id INNER JOIN branch_master as bm ON vr.branch=bm.id INNER JOIN transactions as tr ON vr.request_number=tr.student_key AND tr.trans_status=1 LEFT JOIN admin_table as a ON vr.updated_by=a.id WHERE vr.id='".$request_id."'") );

		 return $sql[0];
		
	}
	function getRequestDetails($request_id) {

		

		 $sql = DB::select( DB::raw("SELECT vd.*,em.session_name as exam_name_display,sm.semester_name as semester_name_display,rt.transaction_ref_id,rt.request_id as refund_request_id,rt.transaction_id_payu,rt.status,rt.created_date_time as refund_date_time,rt.updated_date_time as refund_updated_date_time FROM verification_documents as vd
													  LEFT JOIN exam_master as em ON vd.exam_name = em.session_no
													  LEFT JOIN semester_master as sm ON vd.semester = sm.id
													  LEFT JOIN refund_transactions as rt ON vd.refund_id = rt.id AND rt.status_code='1' AND vd.is_refunded='1'
													  WHERE vd.request_id='" . $request_id . "'") );
		 return $sql;
		
	}
	public function semester(Request $request){
		$whereStr    = "";

		if (isset($request['semester_id']) && !empty($request['semester_id'])) {
			$whereStr = 'is_active=1 AND semester_id="' . $request['semester_id'] . '"';
		}
		
		$semester = $sql = DB::select( DB::raw("SELECT id,semester_name,semester_full_name FROM semester_master WHERE is_active = 1" . $whereStr));
		$optionStr = '<option value="" readonly selected>Select Semester</option>';
		if ($semester) {
			foreach ($semester as $readValue) {
				if (isset($_POST['dropdownType']) && $_POST['dropdownType'] == "value") {
					$optionStr .= '<option value="' . $readValue->semester_full_name. '">' . $readValue->semester_name . '</option>';
				} else {
					$optionStr .= '<option value="' . $readValue->id . '">' . $readValue->semester_name . '</option>';
				}
			}
		}
		$message = array('type' => 'success', 'message' => 'success', 'data' => $optionStr);
		echo json_encode($message);
	}
	public function exam(Request $request){
		
		$whereStr = '';
		if (isset($request['exam_id']) && !empty($request['exam_id'])) {
			$whereStr = ' AND session_no="' . $request['exam_id'] . '"';
		}
		
		$exam = $sql = DB::select( DB::raw("SELECT session_no,session_name FROM exam_master WHERE is_active = 1" . $whereStr . " ORDER BY session_no DESC"));
		$optionStr = '<option value="" readonly selected>Select Exam</option>';
		if ($exam) {
			foreach ($exam as $readValue) {
				if (isset($_POST['dropdownType']) && $_POST['dropdownType'] == "value") {
					$optionStr .= '<option value="' . $readValue->session_name. '">' . $readValue->session_name . '</option>';
				} else {
					$optionStr .= '<option value="' . $readValue->session_no . '">' . $readValue->session_name . '</option>';
				}
			}
		}
		$message = array('type' => 'success', 'message' => 'success', 'data' => $optionStr);
		echo json_encode($message);
	}
	public function updateForm(Request $request){

		$domain = \Request::getHost();
	    $subdomain = explode('.', $domain);

		$request_id = (isset($_POST['request_id'])) ? $_POST['request_id'] : '';
		$document_id = (isset($_POST['document_id'])) ? $_POST['document_id'] : '';
		$resultFound = (isset($_POST['resultFound'])) ? $_POST['resultFound'] : '';
		$remark = (isset($_POST['remark'])) ? $_POST['remark'] : '';
		$exam = (isset($_POST['exam'])) ? $_POST['exam'] : '';
		$semester = (isset($_POST['semester'])) ? $_POST['semester'] : '';
		$doc_year = (isset($_POST['resultYear'])) ? $_POST['resultYear'] : '';
		$refundArr = (isset($_POST['refund'])) ? $_POST['refund'] : [];

		if (!empty($request_id) && is_array($document_id)) {
			$document_size = sizeof($document_id);
			if (!empty($document_size)) {

				$requestData = $this->getRequest($request_id);
				
				$error = false;
				if ($requestData && ($requestData->verification_status == "Pending" || ($requestData->verification_status == "Completed" ))) {

					$refundAmount = 0;
					$pendingStatus = 0;

					$last_updated_by = auth::guard('admin')->user()->id;;
					$updated_date_time = date('Y-m-d H:i:s');

						for ($i = 0; $i < $document_size; $i++) {

						switch ($resultFound[$i]) {
						case 'Incorrect':
							if (empty($remark[$i])) {
								$errorMessage = 'For Incorrect result remark is compulsory.';
								$error = true;
							}
							break;
						case 'Correct':
							continue 2;
							break;
						case 'Return':
							if (empty($remark[$i])) {
								$errorMessage = 'For Return result remark is compulsory.';
								$error = true;
							}
							break;
						default:
							$pendingStatus++;
							break;
						}
						
						}
					

					
						
						if ($error) {
							$errorMessage = $errorMessage;
						
						} else {
							for ($i = 0; $i < $document_size; $i++) {
							
							$res = VerificationDocuments::where('id',$document_id[$i])->first();
							//$resExam = SessionsMaster::where('session_no',$exam[$i])->first();
							
							if (!empty($res)) {
								//Refund Amount
								if (in_array($document_id[$i], $refundArr) && $res['is_refunded'] == 0) {
									$refundAmount = $refundAmount + $res['document_price'];
								}

								// dd($exam);
								$result = VerificationDocuments::where('id',$document_id[$i])->update(['result_found_status'=>$resultFound[$i],'remark'=>$remark[$i],'exam_name'=>$exam[$i],'semester'=>$semester[$i],'semester'=>$semester[$i],'doc_year'=>$doc_year[$i],'last_updated_by'=>$last_updated_by]);
								
								
							} else {
								$error = true;
								$errorMessage = 'Something went wrong. Contact administrator.';
							}
						}
					}
				} else {
					$error = true;
					$errorMessage = 'This request is already in completed state. Please contact administrator.';
				}
				
				if ($error) {
					$message = array('type' => 'error', 'message' => $errorMessage);
				} else {
					
					$refundStatus = false;
					if (!empty($refundArr)) {

						if ($requestData['amount'] >= $refundAmount) {
							//Call Refund API
							$transaction_id = $requestData['payment_gateway_id'];
							if ($requestData['amount'] == $refundAmount) {
								$txnType = "Full";
							} else {
								$txnType = "Partial";
							}

							$refundResp = refundTransaction($transaction_id, $refundAmount, $last_updated_by, $requestData['request_number']);
							

							if ($refundResp['status'] == 1) {

								$created_date_time = date('Y-m-d H:i:s');
								$refund_transaction = new RefundTransaction;
							$refund_transaction->transaction_ref_id = $refundResp['transaction_ref_id'];
							$refund_transaction->request_id = $refundResp['request_id'];
							$refund_transaction->request_no = $requestData->request_number;
							$refund_transaction->status_code = $refundResp['status'];
							$refund_transaction->transaction_id_payu = $transaction_id;
							$refund_transaction->amount =  $refundAmount;
							$refund_transaction->status = $refundResp['msg'];
							$refund_transaction->pg_error_code = $refundResp['error_code'];
							$refund_transaction->bank_ref_num = $refundResp['bank_ref_num'];
							$refund_transaction->user_id =  $last_updated_by;
							$refund_transaction->created_date_time = $created_date_time;
							$refund_transaction->txn_type = $txnType;
							$refund_transaction->save();

							
							$refund_id  = $refund_transaction->id;
							$refundStatus = true;
							$is_refunded = 1;
							$document_id_str = implode(',', $refundArr);
							VerificationDocuments::whereIn('id',$document_id_str)->update(['refund_id'=>$refund_id,'is_refunded'=>$is_refunded,'last_updated_by'=>$last_updated_by,'updated_date_time'=>$updated_date_time]);
								
							} else {
								$errorMsg = $refundResp['msg'];
								$refundStatus = false;
							}
							$insertTrans = 1;
						} else {
							$insertTrans = 0;
							$refundStatus = false;
							$errorMsg = 'Refund amount is greater than actual transaction amount.';

						}

					} else {
						$refundStatus = true;
					}
					if ($refundStatus) {
						if (empty($pendingStatus)) {


							$reresultFinal = VerificationRequests::where('id',$request_id)->update(['verification_status'=>'Completed','updated_by'=>$last_updated_by,'updated_date_time'=>$updated_date_time]);
							

							if ($requestData) {

								$user_id = $requestData->user_id;
								$requestDetails = $this->getRequestDetails($request_id);
				
								$userData = User::where('id',$user_id)->first();
								$requestData->offer_letter = (!empty($requestData->offer_letter)) ? '<a href="' . Config::get('constant.local_base_path').$subdomain[0] .'/'. $requestData->offer_letter . '" target="_blank">Link</a>' : 'Not Uploded';
								$requestDetailData = array();
								foreach ($requestDetails as $readData) {
									$requestDetailData[] = array("document_type" => $readData->document_type,
										"document_path" => '<a href="' .  Config::get('constant.local_base_path').$subdomain[0].'/' . $readData->document_path . '" target="_blank">Link</a>',
										"result" => (!empty($readData->result_found_status)) ? $readData->result_found_status : '-',
										"remark" => (!empty($readData->remark)) ? $readData->remark : '-',
										"exam" => (!empty($readData->exam_name)) ? $readData->exam_name_display : '-',
										"semester" => (!empty($readData->semester)) ? $readData->semester_name_display : '-',
										"doc_year" => (!empty($readData->doc_year)) ? $readData->doc_year : '-',
										"device_type" => (!empty($requestData->device_type)) ? $requestData->device_type : '-',
									);
								}
								
							}

							$message = array('type' => 'success', 'message' => 'Document status updated successfully.');
							
						} else {

							$message = array('type' => 'success', 'message' => 'Document status updated successfully.');
						}
					} else {
						
						$message = array('type' => 'error', 'message' => $errorMsg);
						if ($insertTrans == 1) {

							$created_date_time = date('Y-m-d H:i:s');

							$refund_transaction = new RefundTransaction;
							$refund_transaction->transaction_ref_id = $refundResp['transaction_ref_id'];
							$refund_transaction->request_id = $refundResp['request_id'];
							$refund_transaction->request_no = $requestData->request_number;
							$refund_transaction->status_code = $refundResp['status'];
							$refund_transaction->transaction_id_payu = $transaction_id;
							$refund_transaction->amount =  $refundAmount;
							$refund_transaction->status = $refundResp['msg'];
							$refund_transaction->pg_error_code = $refundResp['error_code'];
							$refund_transaction->bank_ref_num = $refundResp['bank_ref_num'];
							$refund_transaction->user_id =  $last_updated_by;
							$refund_transaction->created_date_time = $created_date_time;
							$refund_transaction->save();
							
						}
					}
				}

			} else {
				$message = array('type' => 'error', 'message' => 'Required fields not found.');
			}

		} else {
			$message = array('type' => 'error', 'message' => 'Required fields not found.');
		}
		echo json_encode($message);
	}
	function refundTransaction($transaction_id, $amount, $UID, $request_number) {

		$rF = array('transaction_id' => $transaction_id, 'amount' => $amount, 'UID' => $UID, 'request_number' => $request_number);

		$qsF = http_build_query($rF);
		$wsUrlF = dirUrl . "/functions/payment/Cancel_Refund_api.php";
		$cF = curl_init();
		curl_setopt($cF, CURLOPT_URL, $wsUrlF);
		curl_setopt($cF, CURLOPT_POST, 1);
		curl_setopt($cF, CURLOPT_POSTFIELDS, $qsF);
		curl_setopt($cF, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($cF, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($cF, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($cF, CURLOPT_SSL_VERIFYPEER, 0);
		$oF = curl_exec($cF);
		if (curl_errno($cF)) {
			$sad = curl_error($cF);
			throw new Exception($sad);
		}
		curl_close($cF);
		$data = (array) json_decode($oF);
	//	print_r($data);
		return $data;
	}
	function resendMailCompleteVerificaitonAdmin($request_id) {

		$requestData = getRequest($request_id);
		

		if ($requestData) {
			if ($requestData['verification_status'] == "Completed") {
				$user_id = $requestData['user_id'];
				
				$userData = User::where('id',$user_id)->first();
				$requestDetails = getRequestDetails($request_id);

				

				$requestData["offer_letter"] = (!empty($requestData['offer_letter'])) ? '<a href="' . dirUrl . $requestData['offer_letter'] . '" target="_blank">Link</a>' : 'Not Uploded';
				$requestDetailData = array();
				foreach ($requestDetails as $readData) {
					$requestDetailData[] = array("document_type" => $readData['document_type'],
						"document_path" => '<a href="' . dirUrl . $readData['document_path'] . '" target="_blank">Link</a>',
						"result" => (!empty($readData['result_found_status'])) ? $readData['result_found_status'] : '-',
						"remark" => (!empty($readData['remark'])) ? $readData['remark'] : '-',
						"exam" => (!empty($readData['exam_name'])) ? $readData['exam_name_display'] : '-',
						"semester" => (!empty($readData['semester'])) ? $readData['semester_name_display'] : '-',
						"doc_year" => (!empty($readData['doc_year'])) ? $readData['doc_year'] : '-',
						"device_type" => (!empty($requestData['device_type'])) ? $requestData['device_type'] : '-',
					);
				}



			} else {
				echo "Check request details1";
			}
		} else {
			echo "Check request details";
		}

	}
	public function ReportNonQr(Request $request){

		$user_id = Auth::guard('admin')->user()->id;

		$extraWhere = "";
		if (isset($request['fromDate']) && isset($request['toDate']) && !empty($request['fromDate']) && !empty($request['toDate'])) {
			$fromDate = date('Y-m-d', strtotime($request['fromDate']));
			$toDate = date('Y-m-d', strtotime($request['toDate']));
			$extraWhere .= " AND (DATE(vr.created_date_time) >= '" . $fromDate . "' AND DATE(vr.created_date_time) <= '" . $toDate . "')";

		} else {
			$request['fromDate'] = "   -   ";
			$request['toDate'] = "   -   ";
		}
		if (isset($request['refund_transactions']) && $request['refund_transactions'] == 1) {

			$extraWhere .= " AND vr.id= (select `vd`.`request_id` from verification_documents as vd WHERE `vd`.`request_id` = `vr`.`id` AND `vd`.`is_refunded`=1 LIMIT 1) ";
		}

		 $sql = DB::select(DB::raw("SELECT vr.*,DATE_FORMAT(vr.created_date_time, '%d %b %Y %h:%i %p') as created_date_time,DATE_FORMAT(vr.updated_date_time, '%d %b %Y %h:%i %p') as updated_date_time,dm.degree_name,bm.branch_name_long,ut.fullname,ut.l_name,ut.username as submitted_by,tr.trans_id_ref as payment_transaction_id,tr.trans_id_gateway as payment_gateway_id, tr.payment_mode,tr.amount,DATE_FORMAT(tr.created_at, '%d %b %Y %h:%i %p') as payment_date_time,a.username FROM verification_requests as vr INNER JOIN user_table as ut ON vr.user_id=ut.id INNER JOIN degree_master as dm ON vr.degree=dm.id INNER JOIN branch_master as bm ON vr.branch=bm.id INNER JOIN transactions as tr ON vr.request_number=tr.student_key AND tr.trans_status=1 LEFT JOIN admin_table as a ON vr.updated_by=a.id WHERE vr.payment_status='Paid' AND vr.verification_status='" . $_POST['status'] . "' " . $extraWhere));

		
		$filename = 'Report';
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		if(!empty($sql)){

			
			$sheet = $spreadsheet->getActiveSheet()->setTitle('Sheet1'); 
			$wrsheet = $spreadsheet->getSheet(0);
			
			$headerIndex = 3;
			$wrsheet->SetCellValueByColumnAndRow(1, 1, "Report Generated By : {$user_id}      |       NON-QR       |      Start Date : {$request['fromDate']}      |      To End Date : {$request['toDate']}");

			$wrsheet->SetCellValueByColumnAndRow(1, $headerIndex, 'Sr. No.');
			$wrsheet->SetCellValueByColumnAndRow(2, $headerIndex, 'Request Number');
			$wrsheet->SetCellValueByColumnAndRow(3, $headerIndex, 'User Name');
			$wrsheet->SetCellValueByColumnAndRow(4, $headerIndex, 'Submitted By');
			$wrsheet->SetCellValueByColumnAndRow(5, $headerIndex, 'Student Name');
			$wrsheet->SetCellValueByColumnAndRow(6, $headerIndex, 'Institute');
			$wrsheet->SetCellValueByColumnAndRow(7, $headerIndex, 'Degree');
			$wrsheet->SetCellValueByColumnAndRow(8, $headerIndex, 'Branch');
			$wrsheet->SetCellValueByColumnAndRow(9, $headerIndex, 'Registration No');
			$wrsheet->SetCellValueByColumnAndRow(10, $headerIndex, 'Passout Year');
			$wrsheet->SetCellValueByColumnAndRow(11, $headerIndex, 'Name of Recruiter');
			$wrsheet->SetCellValueByColumnAndRow(12, $headerIndex, 'Request Status');
			$wrsheet->SetCellValueByColumnAndRow(13, $headerIndex, 'Submission Date');
			$wrsheet->SetCellValueByColumnAndRow(14, $headerIndex, 'Payment Transaction Id');
			$wrsheet->SetCellValueByColumnAndRow(15, $headerIndex, 'Payment Gateway Id');
			$wrsheet->SetCellValueByColumnAndRow(16, $headerIndex, 'Payment Mode');
			$wrsheet->SetCellValueByColumnAndRow(17, $headerIndex, 'Amount');
			$wrsheet->SetCellValueByColumnAndRow(18, $headerIndex, 'payment Date Time');
			$wrsheet->SetCellValueByColumnAndRow(19, $headerIndex, 'Status Updated By');
			$wrsheet->SetCellValueByColumnAndRow(20, $headerIndex, 'Status Update Date Time');
			
			$i = 4;
			$sheetId = 0;
			$recordCount = 1;
			foreach ($sql as $row) {

				$wrsheet->SetCellValueByColumnAndRow(1, $i, $recordCount);
				$wrsheet->SetCellValueByColumnAndRow(2, $i, $row->request_number);
				$wrsheet->SetCellValueByColumnAndRow(3, $i, $row->submitted_by);
				$wrsheet->SetCellValueByColumnAndRow(4, $i, $row->fullname . ' ' . $row->l_name);
				$wrsheet->SetCellValueByColumnAndRow(5, $i, $row->student_name);
				$wrsheet->SetCellValueByColumnAndRow(6, $i, $row->institute);
				$wrsheet->SetCellValueByColumnAndRow(7, $i, $row->degree_name);
				$wrsheet->SetCellValueByColumnAndRow(8, $i, $row->branch_name_long);
				$wrsheet->SetCellValueByColumnAndRow(9, $i, $row->registration_no);
				$wrsheet->SetCellValueByColumnAndRow(10, $i, $row->passout_year);
				$wrsheet->SetCellValueByColumnAndRow(11, $i, $row->name_of_recruiter);
				$wrsheet->SetCellValueByColumnAndRow(12, $i, $row->verification_status);
				$wrsheet->SetCellValueByColumnAndRow(13, $i, $row->created_date_time);
				$wrsheet->SetCellValueByColumnAndRow(14, $i, $row->payment_transaction_id);
				$wrsheet->SetCellValueByColumnAndRow(15, $i, $row->payment_gateway_id);
				$wrsheet->SetCellValueByColumnAndRow(16, $i, $row->payment_mode);
				$wrsheet->SetCellValueByColumnAndRow(17, $i, $row->amount);
				$wrsheet->SetCellValueByColumnAndRow(18, $i, $row->payment_date_time);
				$wrsheet->SetCellValueByColumnAndRow(19, $i, ($row->username) ? $row->username : '-');
				$wrsheet->SetCellValueByColumnAndRow(20, $i, ($row->updated_date_time) ? $row->updated_date_time : '-');
				$i++;
				if ($i == 65537) {
					$headerIndex = 1;
					
					// Create a new worksheet, after the default sheet
					$spreadsheet->createSheet();
					$sheetId = $sheetId + 1;
					$spreadsheet->setActiveSheetIndex($sheetId);
					$spreadsheet->getActiveSheet()->setTitle('Sheet ' . ($sheetId + 1));
					$wrsheet = $spreadsheet->getSheet($sheetId);
					$wrsheet->SetCellValueByColumnAndRow(1, $headerIndex, 'Sr. No.');
					$wrsheet->SetCellValueByColumnAndRow(2, $headerIndex, 'Request Number');
					$wrsheet->SetCellValueByColumnAndRow(3, $headerIndex, 'User Name');
					$wrsheet->SetCellValueByColumnAndRow(4, $headerIndex, 'Submitted By');
					$wrsheet->SetCellValueByColumnAndRow(5, $headerIndex, 'Student Name');
					$wrsheet->SetCellValueByColumnAndRow(6, $headerIndex, 'Institute');
					$wrsheet->SetCellValueByColumnAndRow(7, $headerIndex, 'Degree');
					$wrsheet->SetCellValueByColumnAndRow(8, $headerIndex, 'Branch');
					$wrsheet->SetCellValueByColumnAndRow(9, $headerIndex, 'Registration No');
					$wrsheet->SetCellValueByColumnAndRow(10, $headerIndex, 'Passout Year');
					$wrsheet->SetCellValueByColumnAndRow(11, $headerIndex, 'Name of Recruiter');
					$wrsheet->SetCellValueByColumnAndRow(12, $headerIndex, 'Request Status');
					$wrsheet->SetCellValueByColumnAndRow(13, $headerIndex, 'Submission Date');
					$wrsheet->SetCellValueByColumnAndRow(14, $headerIndex, 'Payment Transaction Id');
					$wrsheet->SetCellValueByColumnAndRow(15, $headerIndex, 'Payment Gateway Id');
					$wrsheet->SetCellValueByColumnAndRow(16, $headerIndex, 'Payment Mode');
					$wrsheet->SetCellValueByColumnAndRow(17, $headerIndex, 'Amount');
					$wrsheet->SetCellValueByColumnAndRow(18, $headerIndex, 'payment Date Time');
					$wrsheet->SetCellValueByColumnAndRow(19, $headerIndex, 'Status Updated By');
					$wrsheet->SetCellValueByColumnAndRow(20, $headerIndex, 'Status Update Date Time');
					$i = 2;
				}

				$recordCount++;
			}

			for ($z = 0; $z <= $sheetId; $z++) {

				$spreadsheet->setActiveSheetIndex($z);
				// adjust auto column width
				foreach (range('A', 'T') as $columnID) {
					$spreadsheet->getActiveSheet()->getColumnDimension($columnID)
						->setAutoSize(true);

				}
				$wrsheet = $spreadsheet->getSheet($z);

				//Merging cell
				$wrsheet->mergeCells("A1:T1");
				$wrsheet->mergeCells("A2:T2");

				//heading bold
				$wrsheet->getStyle("A" . $headerIndex . ":T" . $headerIndex)->getFont()->setBold(true);

				//creating table structure
				$styleArray = array(
					'borders' => array(
						'allborders' => array(
							'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
							'color' => array('argb' => '000000'),
						),
					),
				);

				if ($z == $sheetId) {
					$totalRecordsInSheet = $i;
				} else {
					$totalRecordsInSheet = 65537;
				}
				$wrsheet->getStyle('A1:T' . ($totalRecordsInSheet - 1))->applyFromArray($styleArray);

			$spreadsheet->getActiveSheet()->getStyle('A' . $headerIndex . ':T' . $headerIndex)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('cdcdcd');
			}

			$spreadsheet->setActiveSheetIndex(0);

			header('Content-Type: application/vnd.ms-excel');
			header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
			header('Cache-Control: max-age=0');
			// If you're serving to IE 9, then the following may be needed
			header('Cache-Control: max-age=1');
			// If you're serving to IE over SSL, then the following may be needed
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
			header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
			header('Pragma: public'); // HTTP/1.0

			$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet,'Xlsx');
			
			ob_clean();
			$writer->save('php://output');
		}else{
			$spreadsheet->getActiveSheet()->setTitle('Sheet 1');
			$wrsheet = $spreadsheet->getSheet(0);
			//User Details
			$headerIndex = 3;
			$wrsheet->SetCellValueByColumnAndRow(1, 1, "Report Generated By : {$_SESSION['username']}      |       NON-QR       |      Start Date : {$_POST['fromDate']}      |      To End Date : {$_POST['toDate']}");
			$wrsheet->SetCellValueByColumnAndRow(1, 4, "No Records Found !");
			$wrsheet->mergeCells("A1:T1");
			$wrsheet->mergeCells("A2:T2");
			$wrsheet->mergeCells("A4:T4");
			//header
			$wrsheet->SetCellValueByColumnAndRow(1, $headerIndex, 'Sr. No.');
			$wrsheet->SetCellValueByColumnAndRow(2, $headerIndex, 'Request Number');
			$wrsheet->SetCellValueByColumnAndRow(3, $headerIndex, 'User Name');
			$wrsheet->SetCellValueByColumnAndRow(4, $headerIndex, 'Submitted By');
			$wrsheet->SetCellValueByColumnAndRow(5, $headerIndex, 'Student Name');
			$wrsheet->SetCellValueByColumnAndRow(6, $headerIndex, 'Institute');
			$wrsheet->SetCellValueByColumnAndRow(7, $headerIndex, 'Degree');
			$wrsheet->SetCellValueByColumnAndRow(8, $headerIndex, 'Branch');
			$wrsheet->SetCellValueByColumnAndRow(9, $headerIndex, 'Registration No');
			$wrsheet->SetCellValueByColumnAndRow(10, $headerIndex, 'Passout Year');
			$wrsheet->SetCellValueByColumnAndRow(11, $headerIndex, 'Name of Recruiter');
			$wrsheet->SetCellValueByColumnAndRow(12, $headerIndex, 'Request Status');
			$wrsheet->SetCellValueByColumnAndRow(13, $headerIndex, 'Submission Date');
			$wrsheet->SetCellValueByColumnAndRow(14, $headerIndex, 'Payment Transaction Id');
			$wrsheet->SetCellValueByColumnAndRow(15, $headerIndex, 'Payment Gateway Id');
			$wrsheet->SetCellValueByColumnAndRow(16, $headerIndex, 'Payment Mode');
			$wrsheet->SetCellValueByColumnAndRow(17, $headerIndex, 'Amount');
			$wrsheet->SetCellValueByColumnAndRow(18, $headerIndex, 'payment Date Time');
			$wrsheet->SetCellValueByColumnAndRow(19, $headerIndex, 'Status Updated By');
			$wrsheet->SetCellValueByColumnAndRow(20, $headerIndex, 'Status Update Date Time');

			//heading bold
			$wrsheet->getStyle("A" . $headerIndex . ":T" . $headerIndex)->getFont()->setBold(true);

			//creating table structure
			$styleArray = array(
				'borders' => array(
					'allborders' => array(
						'style' => PHPExcel_Style_Border::BORDER_THIN,
						'color' => array('argb' => '000000'),
					),
				),
			);

			//header colour
			$spreadsheet->getActiveSheet()->getStyle('A' . $headerIndex . ':T' . $headerIndex)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('cdcdcd');
			
			//creating table structure
			$styleArray = array(
				'borders' => array(
					'allborders' => array(
						'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
						'color' => array('argb' => '000000'),
					),
				),
			);
			$wrsheet->getStyle('A0:T' . (4))->applyFromArray($styleArray);
			foreach (range('A', 'T') as $columnID) {
				$spreadsheet->getActiveSheet()->getColumnDimension($columnID)
					->setAutoSize(true);

			}
			$wrsheet = $spreadsheet->getSheet(0);

			// Redirect output to a client’s web browser (Excel2007)
			//	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Type: application/vnd.ms-excel');
			header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
			header('Cache-Control: max-age=0');
			// If you're serving to IE 9, then the following may be needed
			header('Cache-Control: max-age=1');
			// If you're serving to IE over SSL, then the following may be needed
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
			header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
			header('Pragma: public'); // HTTP/1.0

			$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet,'Xlsx');
			ob_clean();
			$writer->save('php://output');
			exit;
		}
	}
	public function ReportSummary(Request $request){
		$user_id = Auth::guard('admin')->user()->id;

		$extraWhere = "";
		if (isset($request['fromDate']) && isset($request['toDate']) && !empty($request['fromDate']) && !empty($request['toDate'])) {
			$fromDate = date('Y-m-d', strtotime($request['fromDate']));
			$toDate = date('Y-m-d', strtotime($request['toDate']));
			$extraWhere .= " AND (DATE(vr.created_date_time) >= '" . $fromDate . "' AND DATE(vr.created_date_time) <= '" . $toDate . "')";

		}
		if (isset($request['refund_transactions']) && $request['refund_transactions'] == 1) {

			$extraWhere .= " AND vr.id= (select `vd`.`request_id` from verification_documents as vd WHERE `vd`.`request_id` = `vr`.`id` AND `vd`.`is_refunded`=1 LIMIT 1) ";
		}

		$gotData = DB::select(DB::raw("SELECT tm.template_name,count(*) as totalCount,sum(sd.amount) as totalAmount FROM scanning_requests as sr INNER JOIN scanned_documents as sd ON sr.id=sd.request_id INNER JOIN student_table as st ON sd.document_key = st.key INNER JOIN template_master as tm ON st.template_id=tm.id INNER JOIN transactions as tr ON sr.request_number=tr.student_key AND tr.trans_status=1 WHERE sr.payment_status='Paid' " . $extraWhere."group by tm.template_name"));
		
		$filename = 'Report';
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

		if (!empty($gotData)) {

			$wrobjPHPExcel = $spreadsheet->getActiveSheet()->setTitle('Sheet1'); 
			$wrsheet = $spreadsheet->getSheet(0);
			$headerIndex = 3;
			$wrsheet->SetCellValueByColumnAndRow(1, 1, "Report Generated By : {$user_id}      |       NON-QR       |      Start Date : {$_POST['fromDate']}      |      To End Date : {$_POST['toDate']}");
			//header(string)
			$wrsheet->SetCellValueByColumnAndRow(1, $headerIndex, 'Sr. No.');
			$wrsheet->SetCellValueByColumnAndRow(2, $headerIndex, 'Branch');
			$wrsheet->SetCellValueByColumnAndRow(3, $headerIndex, 'Total Request Received');
			$wrsheet->SetCellValueByColumnAndRow(4, $headerIndex, 'Total payment Received');

			$i = 4;
			$sheetId = 0;
			$recordCount = 1;
			
			foreach ($gotData as $row) {
				$wrsheet->SetCellValueByColumnAndRow(1, $i, $recordCount);
				$wrsheet->SetCellValueByColumnAndRow(2, $i, $row->template_name);
				

				$wrsheet->SetCellValueByColumnAndRow(3, $i, $row->totalCount);
				$wrsheet->SetCellValueByColumnAndRow(4, $i, $row->totalAmount);
				$i++;
				if ($i == 65537) {
					$headerIndex = 1;
					
					// Create a new worksheet, after the default sheet
					$wrobjPHPExcel->createSheet();
					$sheetId = $sheetId + 1;
					$wrobjPHPExcel->setActiveSheetIndex($sheetId);
					$wrobjPHPExcel->getActiveSheet()->setTitle('Sheet ' . ($sheetId + 1));
					$wrsheet = $wrobjPHPExcel->getSheet($sheetId);
					$wrsheet->SetCellValueByColumnAndRow(1, $headerIndex, 'Sr. No.');
					$wrsheet->SetCellValueByColumnAndRow(2, $headerIndex, 'Branch');
					$wrsheet->SetCellValueByColumnAndRow(3, $headerIndex, 'Total Request Received');
					$wrsheet->SetCellValueByColumnAndRow(4, $headerIndex, 'Total payment Received');
					$i = 2;
				}

				$recordCount++;
			}
			for ($z = 0; $z <= $sheetId; $z++) {
				$spreadsheet->setActiveSheetIndex($z);
				// adjust auto column width
				foreach (range('A', 'D') as $columnID) {
					$spreadsheet->getActiveSheet()->getColumnDimension($columnID)
						->setAutoSize(true);

				}
				$wrsheet = $spreadsheet->getSheet($z);

				//Merging cell
				$wrsheet->mergeCells("A1:D1");
				$wrsheet->mergeCells("A2:D2");

				//heading bold
				$wrsheet->getStyle("A" . $headerIndex . ":D" . $headerIndex)->getFont()->setBold(true);

				//creating table structure
				$styleArray = array(
					'borders' => array(
						'allborders' => array(
							'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
							'color' => array('argb' => '000000'),
						),
					),
				);

				if ($z == $sheetId) {
					$totalRecordsInSheet = $i;
				} else {
					$totalRecordsInSheet = 65537;
				}
				$wrsheet->getStyle('A1:D' . ($totalRecordsInSheet - 1))->applyFromArray($styleArray);

				//header colour
				
				$spreadsheet->getActiveSheet()->getStyle('A' . $headerIndex . ':D' . $headerIndex)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('cdcdcd');
			


			}
			$wrsheet = $spreadsheet->getSheet(0);
			// Redirect output to a client’s web browser (Excel2007)
			//	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Type: application/vnd.ms-excel');
			header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
			header('Cache-Control: max-age=0');
			// If you're serving to IE 9, then the following may be needed
			header('Cache-Control: max-age=1');
			// If you're serving to IE over SSL, then the following may be needed
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
			header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
			header('Pragma: public'); // HTTP/1.0

			$objWriter =  \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
			ob_clean();
			$objWriter->save('php://output');
			exit;
		}else{
			// Rename worksheet
			$spreadsheet->getActiveSheet()->setTitle('Sheet 1');
			$wrsheet = $spreadsheet->getSheet(0);
			//User Details
			$headerIndex = 3;
			$wrsheet->SetCellValueByColumnAndRow(1, 1, "Report Generated By : {$_SESSION['username']}      |       NON-QR       |      Start Date : {$_POST['fromDate']}      |      To End Date : {$_POST['toDate']}");
			$wrsheet->SetCellValueByColumnAndRow(1, 4, "No Records Found !");
			$wrsheet->mergeCells("A1:S1");
			$wrsheet->mergeCells("A2:S2");
			$wrsheet->mergeCells("A4:S4");
			//header
			$wrsheet->SetCellValueByColumnAndRow(1, $headerIndex, 'Branch');
			$wrsheet->SetCellValueByColumnAndRow(2, $headerIndex, 'Total Request Received');
			$wrsheet->SetCellValueByColumnAndRow(3, $headerIndex, 'Total payment Received');

			//heading bold
			$wrsheet->getStyle("A" . $headerIndex . ":S" . $headerIndex)->getFont()->setBold(true);

			//creating table structure
			$styleArray = array(
				'borders' => array(
					'allborders' => array(
						'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
						'color' => array('argb' => '000000'),
					),
				),
			);


			//header colour
			
			//creating table structure
			$spreadsheet->getActiveSheet()->getStyle('A' . $headerIndex . ':D' . $headerIndex)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('cdcdcd');

			$wrsheet->getStyle('A1:S' . (4))->applyFromArray($styleArray);
			foreach (range('A', 'S') as $columnID) {
				$spreadsheet->getActiveSheet()->getColumnDimension($columnID)
					->setAutoSize(true);

			}
			$wrsheet = $spreadsheet->getSheet(0);

			// Redirect output to a client’s web browser (Excel2007)
			//	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Type: application/vnd.ms-excel');
			header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
			header('Cache-Control: max-age=0');
			// If you're serving to IE 9, then the following may be needed
			header('Cache-Control: max-age=1');
			// If you're serving to IE over SSL, then the following may be needed
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
			header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
			header('Pragma: public'); // HTTP/1.0

			$objWriter = PHPExcel_IOFactory::createWriter($wrobjPHPExcel, 'Excel5');
			ob_clean();
			$objWriter->save('php://output');
		}
	}
}

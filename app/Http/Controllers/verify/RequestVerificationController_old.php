<?php

namespace App\Http\Controllers\verify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\raisoni\DegreeMaster;
use App\models\raisoni\DocumentRateMaster;
use App\models\raisoni\VerificationRequests;
use App\models\raisoni\VerificationDocuments;
use App\models\raisoni\ScanningRequests;
use App\models\PaymentGateway;
use App\models\PaymentGatewayConfig;
use App\models\User;
use App\models\Transactions;
use App\Jobs\SendMailJob;
use PaytmWallet;
use Auth;
use DB,Config;

class RequestVerificationController extends Controller
{
    public function index(){
		
		return view('verify.request_verification');
    }
    public function getDropDown(Request $request){

    	$type = $request['type'];

    	switch ($type) {
    		case 'degree':
    			
    			$degree_master = DegreeMaster::select('id','degree_name')->where('is_active',1)->get()->toArray();
    			$optionStr = '<option value="" disabled selected>Select Degree</option>';
    			if ($degree_master) {
					foreach ($degree_master as $readValue) {
						$optionStr .= '<option value="' . $readValue['id'] . '">' . $readValue['degree_name'] . '</option>';
					}
				}
				$message = array('type' => 'success', 'message' => 'success', 'data' => $optionStr);

    		break;
    		case 'documents_rate_master':

    			$document_rate = DB::select( DB::raw("SELECT *,DATE_FORMAT(updated_at, '%d %b %Y %h:%i %p') as updated_date_time FROM documents_rate_master") );
    			
    			$array = json_decode(json_encode($document_rate), true);
    			
    			$message = array('type' => 'success', 'message' => 'success', 'data' => $array);

    		break;
    		case 'fetchStudentDetails':

    			$user_type= Auth::guard('webuser')->user()->user_type;
    			$user_id= Auth::guard('webuser')->user()->id;
    			
    			if($user_type == 0){


    				$sql  = User::select('fullname','l_name','institute','degree','branch','passout_year','registration_no')->where('id',$user_id)->first();

    				$message = array('type' => 'success', 'message' => 'success', 'data' => $sql);
    			}else{
    				$message = array('type' => 'error', 'message' => 'User type not supported.');	
    			}

    		break;
    		case 'branch':

    			$whereStr = '';
				if (isset($_POST['degree_id']) && !empty($_POST['degree_id'])) {
					$whereStr = ' AND degree_id="' . $_POST['degree_id'] . '"';
				}
				$sql = DB::select( DB::raw("SELECT id,branch_name_long FROM branch_master WHERE is_active = 1" . $whereStr) );

				$sql = json_decode(json_encode($sql), true);
				$optionStr = '<option value="" disabled selected>Select Branch</option>';
				if ($sql) {
					foreach ($sql as $readValue) {
						$optionStr .= '<option value="' . $readValue['id'] . '">' . $readValue['branch_name_long'] . '</option>';
					}
				}
				$message = array('type' => 'success', 'message' => 'success', 'data' => $optionStr);
    		break;
    		default:
    			# code...
    			break;


    	}
    	echo json_encode($message);
    }
    public function saveRequest(Request $request){

    	$domain = \Request::getHost();
	    $subdomain = explode('.', $domain);

    	$student_name = (isset($request['student_name'])) ? $this->sanitizeVar($request['student_name']) : '';
		$student_institute = (isset($request['student_institute'])) ? $this->sanitizeVar($request['student_institute']) : '';
		$student_degree = (isset($request['student_degree'])) ? $this->sanitizeVar($request['student_degree']) : '';
		$student_branch = (isset($request['student_branch'])) ? $this->sanitizeVar($request['student_branch']) : '';
		$passout_year = (isset($request['passout_year'])) ? $this->sanitizeVar($request['passout_year']) : '';
		$student_reg_no = (isset($request['student_reg_no'])) ? $this->sanitizeVar($request['student_reg_no']) : '';
		$payment_status = (isset($request['payment_status'])) ? $this->sanitizeVar($request['payment_status']) : '';
		$total_files = (isset($request['total_files'])) ? $this->sanitizeVar($request['total_files']) : '';
		$total_amount = (isset($request['total_amount'])) ? $this->sanitizeVar($request['total_amount']) : '';
		$name_of_recruiter = (isset($request['name_of_recruiter'])) ? $this->sanitizeVar($request['name_of_recruiter']) : '';
    	
    	if (!empty($student_name) && !empty($student_institute) && !empty($student_degree) && !empty($student_branch) && !empty($passout_year) && !empty($student_reg_no) && !empty($name_of_recruiter)) {


    		if (
    			(isset($request['grade_card'][0]) && !empty($request['grade_card'][0])) ||
    			(isset($request['grade_card'][1]) && !empty($request['grade_card'][1])) || 
    			(isset($request['grade_card'][2]) && !empty($request['grade_card'][2])) || 
    			(isset($request['grade_card'][3]) && !empty($request['grade_card'][3])) || 
    			(isset($request['grade_card'][4]) && !empty($request['grade_card'][4])) || 
    			(isset($request['provision_degree'][0]) && !empty($request['provision_degree'][0])) || 
    			(isset($request['provision_degree'][1]) && !empty($request['provision_degree'][1])) || 
    			(isset($request['original_degree'][0]) && !empty($request['original_degree'][0])) || 
    			(isset($request['original_degree'][1]) && !empty($request['original_degree'][1])) || 
    			(isset($request['marksheet'][0]) && !empty($request['marksheet'][0])) || 
    			(isset($request['marksheet'][1]) && !empty($request['marksheet'][1])) && !empty($total_files) && !empty($total_amount)) {


    			$row = VerificationRequests::orderBy('id','desc')->value('request_number');
    			
    			if (!empty($row)) {
					$theRest = substr($row, 6);
					
					$request_number = "OCV-R-" . ($theRest + 1);
				} else {	
					$request_number = "OCV-R-1";
				}

				$created_date_time = date('Y-m-d H:i:s');
				$errorMsg = '';
				$path = public_path().'/'.$subdomain[0] . '/uploads/' . $request_number . '/offer_letter/';
				$pathdb = 'uploads/' . $request_number . '/offer_letter/';
					
				if (isset($request['offer_letter']) && !empty($request['offer_letter'])) {
					$uploadPhoto = $this->uploadSingleFile('offer_letter', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));
					if ($uploadPhoto['status']) {
						$offer_letter = $pathdb . $uploadPhoto['filename'];
					} else {
						$errorMsg = $uploadPhoto['msg'];
					}
					
				}else{
					$offer_letter ='';
				}
				
				if (empty($errorMsg)) {

					$verification_request = New VerificationRequests;
					$verification_request->request_number = $request_number;
					$verification_request->user_id = Auth::guard('webuser')->user()->id;
					$verification_request->institute = $student_institute;
					$verification_request->degree = $student_degree;
					$verification_request->branch = $student_branch;
					$verification_request->student_name = $student_name;
					$verification_request->registration_no = $student_reg_no;
					$verification_request->passout_year = $passout_year;
					$verification_request->name_of_recruiter = $name_of_recruiter;
					$verification_request->offer_letter = $offer_letter;
					$verification_request->no_of_documents = $total_files;
					$verification_request->total_amount = $total_amount;
					$verification_request->created_date_time = $created_date_time;
					$verification_request->save();

					$last_id = $verification_request->id;
					
					if(!empty($last_id)){

						$errorMsg = '';
						$rates = DocumentRateMaster::get()->toArray();
						
						$grade_card_price = $rates[0]['amount_per_document'];
						$provision_degree_price = $rates[1]['amount_per_document'];
						$original_degree_price = $rates[2]['amount_per_document'];
						$marksheet_price = $rates[3]['amount_per_document'];

						$grade_card1 = '';
						$grade_card2 = '';
						$grade_card3 = '';
						$grade_card4 = '';
						$grade_card5 = '';
						$provision_degree1 = '';
						$provision_degree2 = '';
						$original_degree1 = '';
						$original_degree2 = '';
						$marksheet1 = '';
						$marksheet2 = '';
						$file_count_php = 0;
						$total_amount_php = 0;
						$path = public_path().'/'.$subdomain[0] . '/uploads/' . $request_number . '/grade_card/';
						$pathdb = 'uploads/' . $request_number . '/grade_card/';

						if(isset($request['grade_card'][0])){

							$_FILES['file']['name'] = $_FILES['grade_card']['name'][0];
							$_FILES['file']['type'] = $_FILES['grade_card']['type'][0];
							$_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][0];
							$_FILES['file']['error'] = $_FILES['grade_card']['error'][0];
							$_FILES['file']['size'] = $_FILES['grade_card']['size'][0];

							$uploadPhoto = $this->uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));
							if ($uploadPhoto['status']) {

								$file_count_php++;
								$total_amount_php = $total_amount_php + $grade_card_price;
								$grade_card1 = $pathdb . $uploadPhoto['filename'];

								$verification_documents = new VerificationDocuments;
								$verification_documents->request_id = $last_id;
								$verification_documents->document_type = "Grade Card";
								$verification_documents->document_path = $grade_card1;
								$verification_documents->result_found_status = "Pending";
								$verification_documents->document_price = $grade_card_price;
								$verification_documents->created_date_time = $created_date_time;
								$verification_documents->save();
							}else{
								$errorMsg = $uploadPhoto['msg'];
							}
						}
						if (isset($request['grade_card'][1])) {

							$_FILES['file']['name'] = $_FILES['grade_card']['name'][1];
							$_FILES['file']['type'] = $_FILES['grade_card']['type'][1];
							$_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][1];
							$_FILES['file']['error'] = $_FILES['grade_card']['error'][1];
							$_FILES['file']['size'] = $_FILES['grade_card']['size'][1];

							$uploadPhoto = $this->uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));
							if ($uploadPhoto['status']) {
								$file_count_php++;
								$total_amount_php = $total_amount_php + $grade_card_price;
								$grade_card2 = $pathdb . $uploadPhoto['filename'];
								
								$verification_documents = new VerificationDocuments;
								$verification_documents->request_id = $last_id;
								$verification_documents->document_type = "Grade Card";
								$verification_documents->document_path = $grade_card2;
								$verification_documents->result_found_status = "Pending";
								$verification_documents->document_price = $grade_card_price;
								$verification_documents->created_date_time = $created_date_time;
								$verification_documents->save();

							} else {
								$errorMsg = $uploadPhoto['msg'];

							}
						}
						if (isset($request['grade_card'][2])) {

							$_FILES['file']['name'] = $_FILES['grade_card']['name'][2];
							$_FILES['file']['type'] = $_FILES['grade_card']['type'][2];
							$_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][2];
							$_FILES['file']['error'] = $_FILES['grade_card']['error'][2];
							$_FILES['file']['size'] = $_FILES['grade_card']['size'][2];

							$uploadPhoto = $this->uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));
							if ($uploadPhoto['status']) {
								$file_count_php++;
								$total_amount_php = $total_amount_php + $grade_card_price;
								$grade_card3 = $pathdb . $uploadPhoto['filename'];
								
								$verification_documents = new VerificationDocuments;
								$verification_documents->request_id = $last_id;
								$verification_documents->document_type = "Grade Card";
								$verification_documents->document_path = $grade_card3;
								$verification_documents->result_found_status = "Pending";
								$verification_documents->document_price = $grade_card_price;
								$verification_documents->created_date_time = $created_date_time;
								$verification_documents->save();
							} else {
								$errorMsg = $uploadPhoto['msg'];
							}
						}
						if (isset($request['grade_card'][3])) {

							$_FILES['file']['name'] = $_FILES['grade_card']['name'][3];
							$_FILES['file']['type'] = $_FILES['grade_card']['type'][3];
							$_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][3];
							$_FILES['file']['error'] = $_FILES['grade_card']['error'][3];
							$_FILES['file']['size'] = $_FILES['grade_card']['size'][3];

							$uploadPhoto = $this->uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));
							if ($uploadPhoto['status']) {
								$file_count_php++;
								$total_amount_php = $total_amount_php + $grade_card_price;
								$grade_card4 = $pathdb . $uploadPhoto['filename'];
								
								$verification_documents = new VerificationDocuments;
								$verification_documents->request_id = $last_id;
								$verification_documents->document_type = "Grade Card";
								$verification_documents->document_path = $grade_card4;
								$verification_documents->result_found_status = "Pending";
								$verification_documents->document_price = $grade_card_price;
								$verification_documents->created_date_time = $created_date_time;
								$verification_documents->save();

							} else {
								$errorMsg = $uploadPhoto['msg'];

							}
						}
						if (isset($request['grade_card'][4])) {

							$_FILES['file']['name'] = $_FILES['grade_card']['name'][4];
							$_FILES['file']['type'] = $_FILES['grade_card']['type'][4];
							$_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][4];
							$_FILES['file']['error'] = $_FILES['grade_card']['error'][4];
							$_FILES['file']['size'] = $_FILES['grade_card']['size'][4];

							$uploadPhoto = $this->uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));
							if ($uploadPhoto['status']) {
								$file_count_php++;
								$total_amount_php = $total_amount_php + $grade_card_price;
								$grade_card5 = $pathdb . $uploadPhoto['filename'];
								
								$verification_documents = new VerificationDocuments;
								$verification_documents->request_id = $last_id;
								$verification_documents->document_type = "Grade Card";
								$verification_documents->document_path = $grade_card5;
								$verification_documents->result_found_status = "Pending";
								$verification_documents->document_price = $grade_card_price;
								$verification_documents->created_date_time = $created_date_time;
								$verification_documents->save();
							} else {
								$errorMsg = $uploadPhoto['msg'];

							}
						}

						$path = public_path().'/'.$subdomain[0] . '/uploads/' . $request_number . '/provision_degree/';
						$pathdb = 'uploads/' . $request_number . '/grade_card/';

						if (empty($errorMsg) && isset($request['provision_degree'][0])){

							$_FILES['file']['name'] = $_FILES['provision_degree']['name'][0];
							$_FILES['file']['type'] = $_FILES['provision_degree']['type'][0];
							$_FILES['file']['tmp_name'] = $_FILES['provision_degree']['tmp_name'][0];
							$_FILES['file']['error'] = $_FILES['provision_degree']['error'][0];
							$_FILES['file']['size'] = $_FILES['provision_degree']['size'][0];

							$uploadPhoto = $this->uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));
							if ($uploadPhoto['status']) {

								$file_count_php++;
								$total_amount_php = $total_amount_php + $provision_degree_price;
								$provision_degree1 = $pathdb . $uploadPhoto['filename'];

								$verification_documents = new VerificationDocuments;
								$verification_documents->request_id = $last_id;
								$verification_documents->document_type = "Provisional Degree";
								$verification_documents->document_path = $provision_degree1;
								$verification_documents->result_found_status = "Pending";
								$verification_documents->document_price = $provision_degree_price;
								$verification_documents->created_date_time = $created_date_time;
								$verification_documents->save();
								
							} else {
								$errorMsg = $uploadPhoto['msg'];
							}
						}
						if (empty($errorMsg) &&isset($request['provision_degree'][1])) {

							$_FILES['file']['name'] = $_FILES['provision_degree']['name'][1];
							$_FILES['file']['type'] = $_FILES['provision_degree']['type'][1];
							$_FILES['file']['tmp_name'] = $_FILES['provision_degree']['tmp_name'][1];
							$_FILES['file']['error'] = $_FILES['provision_degree']['error'][1];
							$_FILES['file']['size'] = $_FILES['provision_degree']['size'][1];

							$uploadPhoto = $this->uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));
							if ($uploadPhoto['status']) {

								$file_count_php++;
								$total_amount_php = $total_amount_php + $provision_degree_price;
								$provision_degree2 = $pathdb . $uploadPhoto['filename'];
								
								$verification_documents = new VerificationDocuments;
								$verification_documents->request_id = $last_id;
								$verification_documents->document_type = "Provisional Degree";
								$verification_documents->document_path = $provision_degree2;
								$verification_documents->result_found_status = "Pending";
								$verification_documents->document_price = $provision_degree_price;
								$verification_documents->created_date_time = $created_date_time;
								$verification_documents->save();

							} else {
								$errorMsg = $uploadPhoto['msg'];

							}
						}

						$path =  public_path().'/'.$subdomain[0] . '/uploads/' . $request_number . '/original_degree/';
						$pathdb = 'uploads/' . $request_number . '/original_degree/';
						
						if (empty($errorMsg) && isset($request['original_degree'][0])) {

							$_FILES['file']['name'] = $_FILES['original_degree']['name'][0];
							$_FILES['file']['type'] = $_FILES['original_degree']['type'][0];
							$_FILES['file']['tmp_name'] = $_FILES['original_degree']['tmp_name'][0];
							$_FILES['file']['error'] = $_FILES['original_degree']['error'][0];
							$_FILES['file']['size'] = $_FILES['original_degree']['size'][0];

							$uploadPhoto = $this->uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));
							if ($uploadPhoto['status']) {

								$file_count_php++;
								$total_amount_php = $total_amount_php + $original_degree_price;
								$original_degree1 = $pathdb . $uploadPhoto['filename'];

								$verification_documents = new VerificationDocuments;
								$verification_documents->request_id = $last_id;
								$verification_documents->document_type = "Leaving Certificate";
								$verification_documents->document_path = $original_degree1;
								$verification_documents->result_found_status = "Pending";
								$verification_documents->document_price = $original_degree_price;
								$verification_documents->created_date_time = $created_date_time;
								$verification_documents->save();

							} else {
								$errorMsg = $uploadPhoto['msg'];

							}
						}
						if (empty($errorMsg) && isset($request['original_degree'][1])) {

							$_FILES['file']['name'] = $_FILES['original_degree']['name'][1];
							$_FILES['file']['type'] = $_FILES['original_degree']['type'][1];
							$_FILES['file']['tmp_name'] = $_FILES['original_degree']['tmp_name'][1];
							$_FILES['file']['error'] = $_FILES['original_degree']['error'][1];
							$_FILES['file']['size'] = $_FILES['original_degree']['size'][1];

							$uploadPhoto = $this->uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));
							if ($uploadPhoto['status']) {

								$file_count_php++;
								$total_amount_php = $total_amount_php + $original_degree_price;
								$original_degree2 = $pathdb . $uploadPhoto['filename'];

								$verification_documents = new VerificationDocuments;
								$verification_documents->request_id = $last_id;
								$verification_documents->document_type = "Leaving Certificate";
								$verification_documents->document_path = $original_degree2;
								$verification_documents->result_found_status = "Pending";
								$verification_documents->document_price = $original_degree_price;
								$verification_documents->created_date_time = $created_date_time;
								$verification_documents->save();

							} else {
								$errorMsg = $uploadPhoto['msg'];

							}
						}

						$path = public_path().'/'.$subdomain[0] . '/uploads/' . $request_number . '/marksheet/';
						$pathdb = 'uploads/' . $request_number . '/marksheet/';

						if (empty($errorMsg) && isset($request['marksheet'][0])) {

							$_FILES['file']['name'] = $_FILES['marksheet']['name'][0];
							$_FILES['file']['type'] = $_FILES['marksheet']['type'][0];
							$_FILES['file']['tmp_name'] = $_FILES['marksheet']['tmp_name'][0];
							$_FILES['file']['error'] = $_FILES['marksheet']['error'][0];
							$_FILES['file']['size'] = $_FILES['marksheet']['size'][0];
							$uploadPhoto = $this->uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));
							if ($uploadPhoto['status']) {
								$file_count_php++;
								$total_amount_php = $total_amount_php + $marksheet_price;
								$marksheet1 = $pathdb . $uploadPhoto['filename'];
								
								$verification_documents = new VerificationDocuments;
								$verification_documents->request_id = $last_id;
								$verification_documents->document_type = "Marksheet";
								$verification_documents->document_path = $marksheet1;
								$verification_documents->result_found_status = "Pending";
								$verification_documents->document_price = $marksheet_price;
								$verification_documents->created_date_time = $created_date_time;
								$verification_documents->save();

							} else {
								$errorMsg = $uploadPhoto['msg'];

							}
						}
						if (empty($errorMsg) && isset($request['marksheet'][1])) {
							
							$_FILES['file']['name'] = $_FILES['marksheet']['name'][1];
							$_FILES['file']['type'] = $_FILES['marksheet']['type'][1];
							$_FILES['file']['tmp_name'] = $_FILES['marksheet']['tmp_name'][1];
							$_FILES['file']['error'] = $_FILES['marksheet']['error'][1];
							$_FILES['file']['size'] = $_FILES['marksheet']['size'][1];
							$uploadPhoto = $this->uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));
							if ($uploadPhoto['status']) {
								$file_count_php++;
								$total_amount_php = $total_amount_php + $marksheet_price;
								$marksheet2 = $pathdb . $uploadPhoto['filename'];

								$verification_documents = new VerificationDocuments;
								$verification_documents->request_id = $last_id;
								$verification_documents->document_type = "Marksheet";
								$verification_documents->document_path = $marksheet2;
								$verification_documents->result_found_status = "Pending";
								$verification_documents->document_price = $marksheet_price;
								$verification_documents->created_date_time = $created_date_time;
								$verification_documents->save();

							} else {
								$errorMsg = $uploadPhoto['msg'];
							}
						}
						
						if (empty($errorMsg) && $total_amount_php == $total_amount && $total_files == $file_count_php) {

							$request->session()->put('key_payment', $request_number);
							$request->session()->put('amount_payment', $total_amount_php);
							
							$message = array('status' => 'success', 'message' => 'success', 'payment_status' => $payment_status, "request_number" => $request_number, "firstname" => $student_name);

						} else {
							$dirPath = public_path().'/'.$subdomain[0] . 'uploads/' . $request_number;
							$this->deleteDirectory($dirPath);
							// dd('hi');
							$message = array('status' => 'error', 'message' =>$errorMsg);
						}
					}else{

						$message = array('status' => 'error', 'message' => 'Some error occured. Please try again.');
					}
				}else{

					$dirPath = public_path().'/'.$subdomain[0] . '/uploads/' . $request_number;
					$this->deleteDirectory($dirPath);
					$message = array('type' => 400, 'message' => $errorMsg);
				}
    		}else {
				$message = array('type' => 'error', 'message' => 'Required parameters not found.');
			}
    	}else{
    		$message = array('type' => 'error', 'message' => 'Required parameters not found.');
    	}

    	echo json_encode($message);

    }
    public function SuccessRequest(Request $request){

    	
    	$data = [
    		'firstname'=>$request['firstname'],
    		'request_number'=>$request['request_number'],
    	];

    	return view('verify.success_request',compact('data'));
    }
    public function payTmPayment(Request $request){

    	
    	if(isset($request['key_payment'])){

    		if (isset($request['payment_from']) && $request['payment_from'] == "mobile") {

    				if (isset($request['UID']) && !empty($request['UID'])) {

    					$user = User::select('fullname','email_id','mobile_no')->where('id',$request['UID'])->first();
    					$paramList['EMAIL'] = $user['email_id'];
						$paramList['MSISDN'] = $user['mobile_no'];

						$paramList['ORDER_ID'] = 'SeQR_PU_OCVR_' . $request['UID'] . '_' . strtotime("now");
						$paramList["CUST_ID"] = $request['UID'];
						$paramList["INDUSTRY_TYPE_ID"] = 'Retail';
						$paramList["CHANNEL_ID"] = 'WEB';
						$paramList['CALLBACK_URL'] = $this->getSuccessUrl("mobile",$request['key_payment']);

						$mobile_number = $user['email_id'];
		        		$user_id = $request['UID'];
		        		$email_id = $user['mobile_no'];
		        		$payment_from = 'mobile';
    				}

    		}else{


    			$mobile_number = Auth::guard('webuser')->user()->mobile_no;
		        $user_id = Auth::guard('webuser')->user()->id;
		        $email_id = Auth::guard('webuser')->user()->email_id;

    			$session = $request->session()->all();
    			$payment_from = 'web';
    			
				$mobile_number = Auth::guard('webuser')->user()->mobile_no;
        		$user_id = Auth::guard('webuser')->user()->id;
        		$email_id = Auth::guard('webuser')->user()->email_id;
				
				$paramList['EMAIL'] = $email_id;
				$paramList['MSISDN'] = $mobile_number;
				$paramList["CUST_ID"] = $user_id;
				$paramList["INDUSTRY_TYPE_ID"] = 'Retail';
				$paramList["CHANNEL_ID"] = 'WEB';
				$paramList['ORDER_ID'] = 'SeQR_PU_' . $user_id . '_' . strtotime("now");
    		}
    	}else{
			echo '<h2>Access Forbidden!</h2>';
			exit;
			
    	}

    	$sql = PaymentGateway::select("payment_gateway.merchant_key", "payment_gateway.salt", "payment_gateway.test_merchant_key", "payment_gateway.test_salt","payment_gateway_config.pg_id", "payment_gateway_config.amount", "payment_gateway_config.crendential")
    		->leftjoin('payment_gateway_config','payment_gateway_config.pg_id','payment_gateway.id')
    		->where('payment_gateway.id',7)
    		->where('payment_gateway.status',1)
    		->where('payment_gateway.publish',1)
    		->first();
    	
    	$amount = $sql['amount'];
    	$payment = PaytmWallet::with('receive');
    	$ORDER_ID = 'SeQR_PU_' . 1 . '_' . strtotime("now");
      
        
    	
    	$payment_key = \Session::put('payment_key',$request['key_payment']);
        $payment->prepare([
          'order' => $ORDER_ID,
          'user' => $user_id,
          'mobile_number' => $mobile_number,
          'email' => $email_id,
          'amount' => $amount,
          'callback_url' => $this->getSuccessUrl($payment_from,$request['key_payment'])
        ]);
        return $payment->receive();
        


    }

    public function getSuccessUrl($deviceType,$key_payment){

    	$isQrCodeverification = 0;
    	$verification_requests = VerificationRequests::where('request_number',$key_payment)->first();
    	
    	if ($verification_requests) {
			$_POST['TXN_AMOUNT'] = $verification_requests['total_amount'];
		}
		if (empty($verification_requests)) {

			$total_amount = ScanningRequests::where('request_number',$key_payment)->first();
			
			if($total_amount){
				$_POST['TXN_AMOUNT'] = $total_amount;
				$isQrCodeverification = 1;
			}
		}
		
		if ($isQrCodeverification == 1) {

			if ($deviceType == "web") {

				$url = "/verify/payment/paytm/response-success-qr?key=".$key_payment;
			} else {

				$url = "/verify/payment/paytm/response-success-mobile-qr?key=".$key_payment;
			}
		} else {
			if ($deviceType == "web") {

				$url = "/verify/payment/paytm/response-success?key=".$key_payment;
			} else {
				$url = "/verify/payment/paytm/response-success-mobile?key=".$key_payment;
				
			}
		}
		
		$url = url($url);
		
		return $url;
    }
    public function PayTmResponseSuccess(Request $request){

    	$transaction = PaytmWallet::with('receive');
    	$session_key = \Session::get('payment_key'); 
        $inputInfo = $transaction->response();
        $request_number = $request['key'];

        $verification_requests = VerificationRequests::select('student_name','user_id')->where('request_number',$request['key'])->first();
        
        $user_id = Auth::guard('webuser')->user()->id;
        
       	return view('verify.payment.paytm_success',compact('inputInfo','session_key','verification_requests','request_number','user_id'));
    }

    public function PayTmResponseSuccessQR(Request $request){

    	$transaction = PaytmWallet::with('receive');
    	$session_key = \Session::get('payment_key'); 
        $inputInfo = $transaction->response();
         $request_number = $request['key'];

        $verification_requests = VerificationRequests::select('student_name','user_id')->where('request_number',$request['key'])->first();
        //print_r($verification_requests);
        //exit;
        $user_id= Auth::guard('webuser')->user()->user_id;
        $email_id= Auth::guard('webuser')->user()->email_id;
        $mobile_no= Auth::guard('webuser')->user()->mobile_no;
       	return view('verify.payment.paytm_success_qr',compact('inputInfo','session_key','verification_requests','request_number','email_id','mobile_no','user_id'));

    }
    public function PayTmResponseSuccessMobile(Request $request){

    	$transaction = PaytmWallet::with('receive');
    	$session_key = \Session::get('payment_key'); 
        $inputInfo = $transaction->response();
    	$request_number = $request['key'];
    	
    	$verification_requests = VerificationRequests::select('student_name','user_id')->where('request_number',$request_number)->first();
        $student_name = $verification_requests['student_name'];
        $user_id = $verification_requests['user_id'];

    	return view('verify.payment.paytm_success_mobile',compact('inputInfo','session_key','verification_requests','request_number','student_name','user_id'));
    }
    public function PayTmResponseSuccessMobileQR(Request $request){

    	$transaction = PaytmWallet::with('receive');
    	$session_key = \Session::get('payment_key'); 
        $inputInfo = $transaction->response();
    	$request_number = $request['key'];

    	$scanning_requests = ScanningRequests::where('request_number',$request_number)->first();
    	$student_name = $scanning_requests['student_name'];
    	$user_id = $scanning_requests['user_id'];

    	return view('verify.payment.paytm_success_mobile_qr',compact('inputInfo','session_key','scanning_requests','request_number','student_name','user_id'));

    }
    public function sanitizeVar($data) {
		$data = trim($data);
		$data = stripslashes($data);
		$data = htmlspecialchars($data);
		return $data;
	}
	public function uploadSingleFile($fieldname, $maxsize, $uploadpath, $extensions = false, $ref_name = false) {
		
		$upload_field_name = $_FILES[$fieldname]['name'];
		if (empty($upload_field_name) || $upload_field_name == 'NULL') {
			return array('file' => $_FILES[$fieldname]["name"], 'status' => false, 'msg' => 'Please upload a file');
		}
		
		$file_extension = strtolower(pathinfo($upload_field_name, PATHINFO_EXTENSION));

		if ($extensions !== false && is_array($extensions)) {
			if (!in_array($file_extension, $extensions)) {
				return array('file' => $_FILES[$fieldname]["name"], 'status' => false, 'msg' => 'Please upload valid file ');
			}
		}
		$file_size = @filesize($_FILES[$fieldname]["tmp_name"]);
		if ($file_size > $maxsize) {
			return array('file' => $_FILES[$fieldname]["name"], 'status' => false, 'msg' => 'File Exceeds maximum limit');
		}
		if (isset($upload_field_name)) {
			if ($_FILES[$fieldname]["error"] > 0) {
				return array('file' => $_FILES[$fieldname]["name"], 'status' => false, 'msg' => 'Error: ' . $_FILES[$fieldname]['error']);
			}
		}
		if ($ref_name == false) {
			
			$file_name_without_ext = $this->FileNameWithoutExt($upload_field_name);
			$file_name = time() . '_' . $this->RenameUploadFile($file_name_without_ext) . "." . $file_extension;
		} else {
			$file_name = str_replace(" ", "_", $ref_name) . "." . $file_extension;
		}
		if (!is_dir($uploadpath)) {
			mkdir($uploadpath, 0777, true);
		}
		if (move_uploaded_file($_FILES[$fieldname]["tmp_name"], $uploadpath . $file_name)) {
			return array('file' => $_FILES[$fieldname]["name"], 'status' => true, 'msg' => 'File Uploaded Successfully!', 'filename' => $file_name);
		} else {
			return array('file' => $_FILES[$fieldname]["name"], 'status' => false, 'msg' => 'Sorry unable to upload your file, Please try after some time.');
		}
	}

	public function FileNameWithoutExt($filename) {
		return substr($filename, 0, (strlen($filename)) - (strlen(strrchr($filename, '.'))));
	}

	public function RenameUploadFile($data) {
		$search = array("'", " ", "(", ")", ".", "&", "-", "\"", "\\", "?", ":", "/");
		$replace = array("", "_", "", "", "", "", "", "", "", "", "", "");
		$new_data = str_replace($search, $replace, $data);
		return strtolower($new_data);
	}
	public function deleteDirectory($dirPath) {
		if (is_dir($dirPath)) {
			$objects = scandir($dirPath);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (filetype($dirPath . DIRECTORY_SEPARATOR . $object) == "dir") {
						deleteDirectory($dirPath . DIRECTORY_SEPARATOR . $object);
					} else {
						unlink($dirPath . DIRECTORY_SEPARATOR . $object);
					}
				}
			}
			reset($objects);
			rmdir($dirPath);
		}
	}
	public function AddTransactions(Request $request){

		$domain = \Request::getHost();
	    $subdomain = explode('.', $domain);
		$action = $request['action'];

		switch ($request['action']) {
			case 'create':
				
				if (isset($request['trans_id_ref']) && isset($request['trans_id_gateway']) && isset($request['payment_mode']) && isset($request['amount']) && isset($request['additional']) && isset($request['user_id']) && isset($request['student_key'])) {

					$trans_id_ref = $this->sanitizeVar($_POST['trans_id_ref']);
					$trans_id_gateway = $this->sanitizeVar($_POST['trans_id_gateway']);
					$payment_mode = $this->sanitizeVar($_POST['payment_mode']);
					$amount = $this->sanitizeVar($_POST['amount']);
					$additional = $this->sanitizeVar($_POST['additional']);
					$user_id = $this->sanitizeVar($_POST['user_id']);
					$student_key = $this->sanitizeVar($_POST['student_key']);
					$trans_status = $this->sanitizeVar($_POST['trans_status']);
					$transaction_date = date('Y-m-d H:i:s');

					if(empty($user_id)){
						//print_r(Auth::guard('webuser')->user());
						 $user_id= Auth::guard('webuser')->user()->user_id;
						//exit;
					}

					$arr = explode('_', $trans_id_ref);
					if (isset($arr[1])) {
						if ($arr[1] == 'PT') {
							$pgid = 1;
						}
						if ($arr[1] == 'PU') {
							$pgid = 2;
						}
						if ($arr[1] == 'ST') {
							$pgid = 5;
						}
					}

					$transactions = new Transactions;
					$transactions->pay_gateway_id = $pgid;
					$transactions->trans_id_ref = $trans_id_ref;
					$transactions->trans_id_gateway = $trans_id_gateway;
					$transactions->payment_mode = $payment_mode;
					$transactions->amount = $amount;
					$transactions->additional = $additional;
					$transactions->user_id = $user_id;
					$transactions->student_key = $student_key;
					$transactions->created_at = $transaction_date;
					$transactions->trans_status = $trans_status;
					$transactions->save();

					$message = array('service' => 'Transaction', 'message' => 'Transaction inserted successfully', 'status' => true, 'trans_status' => $trans_status);

					if ($trans_status == '1') {
						

						$verification_requests = VerificationRequests::where('request_number',$student_key)->first();

						$scanning_requests = ScanningRequests::where('request_number',$student_key)->first();
						
						if (!empty($verification_requests)) {

							

							VerificationRequests::where('request_number',$student_key)->update(['payment_status'=>'Paid']);

							$array = explode('_', $trans_id_ref);

							if ($array[1] == 'PU') {
								$gate = 'PAYU MONEY';
							} else if ($array[1] == 'PT') {
								$gate = 'PAYTM';
							} else if ($array[1] == 'ST') {
								$gate = 'SOLUTECH';
							}

							if ($array[1] == 'IAP') {
								$gate = 'Apple In-app purchase';
							}
							

							$users = User::where('id',$user_id)->first();

							$params["name"] = $users['fullname'];
							$params["email_id"] = $users['email_id'];
							$params["amount"] = $request["amount"];
							$params["app"] = $gate;
							$params["mobile"] = $users['mobile_no'];
							$params["trans_id"] = $trans_id_ref;
							$params["gateway_id"] = $trans_id_gateway;
							$params["mode"] = $payment_mode;
							$params["date"] = $transaction_date;
							$params["gateway"] = $gate;
							$params["key"] = $student_key;

							
							$requestData = DB::select( DB::raw("SELECT vr.*,b.branch_name_long,d.degree_name FROM verification_requests as vr
										INNER JOIN branch_master as b ON b.id=vr.branch
										INNER JOIN degree_master as d ON d.id=vr.degree
										where vr.request_number='" . $student_key . "'") );
							$requestData = json_decode(json_encode($requestData),true);
							if (!empty($requestData)) {

							
								$requestData = $requestData[0];
								$params["student_institute"] = $requestData['institute'];
								$params["student_name"] = $requestData['student_name'];
								$params["student_degree"] = $requestData['degree_name'];
								$params["student_branch"] = $requestData['branch_name_long'];
								$params["student_reg_no"] = $requestData['registration_no'];
								$params["passout_year"] = $requestData['passout_year'];
								$params["name_of_recruiter"] = $requestData['name_of_recruiter'];
								$params["offer_letter"] = (!empty($requestData['offer_letter'])) ? '<a href="' .Config::get('constant.local_base_path').$subdomain[0].'/'.$requestData['offer_letter'] . '" target="_blank">Link</a>' : 'Not Uploded';
								$params["date_time_registraion"] = $requestData['created_date_time'];

								
								$requestDocuments = VerificationDocuments::where('request_id',$requestData['id'])->get()->toArray();
								$grade_card_files = '';
								$provisional_degree_files = '';
								$original_degree_files = '';
								$marksheet_files = '';
								$gradeFileCnt = 1;
								$provisionalDegreeCnt = 1;
								$originalDegreeCnt = 1;
								$marksheetCnt = 1;
								$gradeFileAmnt = 0;
								$provisionalDegreeAmnt = 0;
								$originalDegreeAmnt = 0;
								$marksheetAmnt = 0;

								if (!empty($requestDocuments)) {

									foreach ($requestDocuments as $readData) {
										switch ($readData['document_type']) {

										case 'Grade Card':
											$grade_card_files .= '<a href="' . Config::get('constant.local_base_path').$subdomain[0].'/' . $readData['document_path'] . '" target="_blank">File' . $gradeFileCnt . '</a>&nbsp;&nbsp;';
											$gradeFileCnt++;
											$gradeFileAmnt = $gradeFileAmnt + $readData['document_price'];
											break;
										case 'Provisional Degree':
											$provisional_degree_files .= '<a href="' . Config::get('constant.local_base_path').$subdomain[0].'/' . $readData['document_path'] . '" target="_blank">File' . $provisionalDegreeCnt . '</a>&nbsp;&nbsp;';
											$provisionalDegreeCnt++;
											$provisionalDegreeAmnt = $provisionalDegreeAmnt + $readData['document_price'];
											break;
										case 'Leaving Certificate':
											$original_degree_files .= '<a href="' . Config::get('constant.local_base_path').$subdomain[0].'/' . $readData['document_path'] . '" target="_blank">File' . $originalDegreeCnt . '</a>&nbsp;&nbsp;';
											$originalDegreeCnt++;
											$originalDegreeAmnt = $originalDegreeAmnt + $readData['document_price'];
											break;
										case 'Marksheet':
											$marksheet_files .= '<a href="' . Config::get('constant.local_base_path').$subdomain[0].'/' . $readData['document_path'] . '" target="_blank">File' . $marksheetCnt . '</a>&nbsp;&nbsp;';
											$marksheetCnt++;
											$marksheetAmnt = $marksheetAmnt + $readData['document_price'];
											break;

										default:
											$file = 'Not Found';
											break;
										}
									}
								}
								if (!empty($grade_card_files)) {
									$params["grade_card_files"] = $grade_card_files;
								} else {
									$params["grade_card_files"] = 'Not Uploaded';
								}
								$params["grade_card_amount"] = $gradeFileAmnt;

								if (!empty($provisional_degree_files)) {
									$params["provisional_degree_files"] = $provisional_degree_files;
								} else {
									$params["provisional_degree_files"] = 'Not Uploaded';
								}
								$params["provisional_degree_amount"] = $provisionalDegreeAmnt;

								if (!empty($original_degree_files)) {
									$params["original_degree_files"] = $original_degree_files;
								} else {
									$params["original_degree_files"] = 'Not Uploaded';
								}
								$params["original_degree_amount"] = $originalDegreeAmnt;

								if (!empty($marksheet_files)) {
									$params["marksheet_files"] = $marksheet_files;
								} else {
									$params["marksheet_files"] = 'Not Uploaded';
								}
								$params["marksheet_amount"] = $marksheetAmnt;
							}

							$mail_view = 'mail.verify.transaction';
							$mail_subject = '#' . $params['key'] . ' Candidate education details verification';
			                $user_email = $params['email_id'];
			                
			                
							$this->dispatch(new SendMailJob($mail_view,$user_email,$mail_subject,$params));


						}else if(!empty($scanning_requests)){

							

							ScanningRequests::where('request_number',$student_key)->update(['payment_status'=>'Paid']);

							
							$dataPdf = DB::select( DB::raw("SELECT st.certificate_filename FROM scanned_documents as sd
											INNER JOIN student_table as st ON sd.document_key=st.key
										where sd.request_id='" . $scanning_requests['id'] . "'") );
							$dataPdf = json_decode(json_encode($dataPdf),true);

							$message = array('service' => 'Transaction', 'message' => 'success', 'status' => true, 'showPdf' => true, 'dataPdf' => $dataPdf);

							$array = explode('_', $trans_id_ref);
							if ($array[1] == 'PU') {
								$gate = 'PAYU MONEY';
							} else if ($array[1] == 'PT') {
								$gate = 'PAYTM';
							} else if ($array[1] == 'ST') {
								$gate = 'SOLUTECH';
							}

							if ($array[1] == 'IAP') {
								$gate = 'Apple In-app purchase';
							}
							$users = User::where('id',$user_id)->first();

							$params["name"] = $users['fullname'];
							$params["email_id"] = $users['email_id'];
							$params["amount"] = $request["amount"];
							$params["app"] = $gate;
							$params["mobile"] = $users['mobile_no'];
							$params["trans_id"] = $trans_id_ref;
							$params["gateway_id"] = $trans_id_gateway;
							$params["mode"] = $payment_mode;
							$params["date"] = $transaction_date;
							$params["gateway"] = $gate;
							$params["key"] = $student_key;

							$mail_view = 'mail.verify.success_scan';
							$mail_subject = '#' . $params['key'] . ' Verification request fees payment received successfully ';
			                $user_email = $params['email_id'];

							$this->dispatch(new SendMailJob($mail_view,$user_email,$mail_subject,$params));
							
						}

					}

				}
				break;
			
			default:
				# code...
				break;
		}
		echo json_encode($message);
	}
}

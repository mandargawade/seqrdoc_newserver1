<?php

namespace App\Http\Controllers\api\verify;

use App\models\raisoni\DegreeMaster;
use App\models\User as Users;
use App\models\Site;
use App\models\raisoni\BranchMaster;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\User;
use App\Mail\LoginMail;
use App\Mail\ResetPassword;
use Mail;
use Auth;
use DB;
use Illuminate\Support\Facades\Hash;
use App\Utility\ApiSecurityLayer;

class RegistrationController extends Controller
{
    //
    public function delete(Request $request)
    {
        if($request->user_id)
        {
            $user_id = $request->user_id;
            $res=Users::where('id',$user_id)->delete();
            if($res)
            {
                $message = array('status' => 200, 'message' => 'success');
            } else{
                $message = array('status' => 500, 'message' => 'User not found');
            }
        } else {
             $message = array('status' => 500, 'message' => 'Request not found');
        }

        if ($message['status'] == 200) {
            $status = 'success';
        } else {
            $status = 'failed';
        }
        return $message;
    }

    public function registration(Request $request)
    {
        $data = $request->post();

        if(ApiSecurityLayer::checkAuthorization())
        {

            if ($request->type) {

                switch ($request->type) {

                case 'register':
                    if(!empty($request->registration_type) || $request->registration_type == 0)
                    {
                        $registration_type = $request->registration_type;
                        $device_type = 'mobile';
                        if($registration_type == 0)
                        {

                            $student_f_name = (empty($request->student_f_name)) ? null : $request->student_f_name;

                            $student_l_name = (empty($request->student_l_name)) ? null : $request->student_l_name;
                            $student_institute = (empty($request->student_institute)) ? null : $request->student_institute;
                            $student_degree = (empty($request->student_degree)) ? null : $request->student_degree;
                            $student_branch = (empty($request->student_branch)) ? null : $request->student_branch;
                            $passout_year = (empty($request->passout_year)) ? null : $request->passout_year;
                            $student_reg_no = (empty($request->student_reg_no)) ? null : $request->student_reg_no;
                            $student_mob_no = (empty($request->student_mob_no)) ? null : $request->student_mob_no;
                            $student_email = (empty($request->student_email)) ? null : $request->student_email;

                            if (!empty($student_f_name) && !empty($student_l_name) && !empty($student_degree) && !empty($student_branch) && !empty($passout_year) && !empty($student_reg_no) && !empty($student_mob_no) && !empty($student_email)) 
                            {
                                $userData = Users::where('mobile_no',$student_mob_no)
                                ->orWhere('email_id',$student_email)
                                ->orWhere('registration_no',$student_reg_no)
                                ->where('publish', '=', 1)
                                ->first(['mobile_no','email_id']);
                                if (!$userData) 
                                {

                                    $token = md5($student_reg_no . $student_mob_no); //token for email
                                    $OTP = $this->generateOTP(6);

                                    $currentDateTime = date('Y-m-d H:i:s');
                                    $verify_by = 1;
                                    $is_verified = 0;
                                    $student_email = strtolower($student_email);
                                    $username = strtolower($student_email);
                                    $password = $this->genRandomStr(8);
                                    $pwd = Hash::make($password);

                                    //for get site id
                                    $domain = \Request::getHost();
                                    $subdomain = explode('.', $domain);
                                    $site_id = Site::select('site_id')->where('site_url',$domain)->first();
                                    $site_id = $site_id['site_id'];
                    
                                    $user = new Users();
                                    $user->username = $username;
                                    $user->fullname= $student_f_name;
                                    $user->l_name= $student_l_name;
                                    $user->institute= $student_institute;
                                    $user->degree= $student_degree;
                                    $user->branch= $student_branch;
                                    $user->passout_year = $passout_year;
                                    $user->registration_no= $student_reg_no;
                                    $user->mobile_no= $student_mob_no;
                                    $user->email_id= $student_email;
                                    $user->token= $token;
                                    $user->OTP= $OTP;
                                    $user->device_type= $device_type;
                                    $user->user_type= $registration_type;
                                    $user->verify_by= $verify_by;
                                    $user->is_verified= $is_verified;
                                    $user->created_at= $currentDateTime;
                                    $user->password = $pwd;
                                    $user->site_id = $site_id;
                                    $user->save();
                                    $id = $user->id;
                                    
                                    if ($id)
                                    {
                                        try{
                                            $this->sendSMS($student_mob_no, "Your OTP for SeQR App Verification is " . $OTP." - Team SSSL");
                                            $message = array('status' => 200, 'message' => 'Success.', 'otp' => $OTP);

                                        }catch (exception $e) {
                                            $message = array('status' => 500, 'message' => $e->getMessage());
                                        }
                                    }else{
                                        return response()->json(['status' => false,'message' => 'Some error occured. Please try again.','code' => 404]);
                                    }
                                }else{
                                    if ($userData['mobile_no'] == $student_mob_no) {
                                        $message = array('status' => 409, 'message' => 'User already exist with same mobile no.');
                                    } else if ($userData['email_id'] == $student_email) {
                                        $message = array('status' => 409, 'message' => 'User already exist with same email.');
                                    } else {
                                        $message = array('status' => 409, 'message' => 'User already exist with same registration number.');
                                    }
                                }
                            } else{
                                $message = array('status' => 400, 'message' => 'Required parameters not found.');
                            }
                        }
                        elseif($registration_type == 1)
                        {
                            $employer_name = (empty($request->employer_name)) ? null : $request->employer_name;

                            $employer_reg_no = (empty($request->employer_reg_no)) ? null : $request->employer_reg_no;
                            $employer_working_sector = (empty($request->employer_working_sector)) ? null : $request->employer_working_sector;
                            $employer_address = (empty($request->employer_address)) ? null : $request->employer_address;
                            $employer_mob_no = (empty($request->employer_mob_no)) ? null : $request->employer_mob_no;
                            $employer_email = (empty($request->employer_email)) ? null : $request->employer_email;
                            
                            if (!empty($employer_name) && !empty($employer_reg_no) && !empty($employer_working_sector) && !empty($employer_mob_no) && !empty($employer_email)) {

                                $userData = Users::where('mobile_no',$employer_mob_no)
                                ->orWhere('email_id',$employer_email)
                                ->orWhere('registration_no',$employer_reg_no)
                                ->where('publish', '=', 1)
                                ->first(['mobile_no','email_id']);


                                if (!$userData) {

                                    $token = md5($employer_reg_no . $employer_mob_no); //token for email
                                    $OTP = $this->generateOTP(6);
                                    $currentDateTime = date('Y-m-d H:i:s');
                                    $verify_by = 1;
                                    $is_verified = 0;
                                    $employer_email = strtolower($employer_email);
                                    $username = strtolower($employer_email);
                                    $password = $this->genRandomStr(8);
                                    $pwd = Hash::make($password);

                                    //get site id
                                    $domain = \Request::getHost();
                                    $subdomain = explode('.', $domain);
                                    $site_id = Site::select('site_id')->where('site_url',$domain)->first();
                                    $site_id = $site_id['site_id'];

                                    //save data to database
                                    $user = new Users();
                                    $user->username = $username;
                                    $user->fullname= $employer_name;
                                    $user->registration_no= $employer_reg_no;
                                    $user->working_sector= $employer_working_sector;
                                    $user->address= $employer_address;
                                    $user->mobile_no= $employer_mob_no;
                                    $user->email_id= $employer_email;
                                    $user->token= $token;
                                    $user->OTP= $OTP;
                                    $user->device_type= $device_type;
                                    $user->user_type= $registration_type;
                                    $user->verify_by= $verify_by;
                                    $user->created_at= $currentDateTime;
                                    $user->is_verified= $is_verified;
                                    $user->password = $pwd;
                                    $user->site_id = $site_id;
                                    $user->save();
                                    $id = $user->id;

                                    if ($id)
                                    {
                                        try{
                                            $this->sendSMS($employer_mob_no, "Your OTP for SeQR App Verification is " . $OTP." - Team SSSL");
                                            $message = array('status' => 200, 'message' => 'success', 'otp' => $OTP);
                                        }catch (Exception $ex) {
                                            return response()->json(['status' => false,'message' => $ex->getMessage()]);
                                        }
                                        
                                    }else{
                                        $message = array('status' => 500, 'message' => 'Some error occured. Please try again.');
                                    }
                                }else{
                                    if ($userData['mobile_no'] == $employer_mob_no) {
                                        $message = array('status' => 409, 'message' => 'User already exist with same mobile no.');
                                    } else if ($userData['email_id'] == $employer_email) {
                                        $message = array('status' => 409, 'message' => 'User already exist with same email.');
                                    } else {
                                        $message = array('status' => 409, 'message' => 'User already exist with same registration number.');
                                    }
                                }
                            }else{
                                $message = array('status' => 400, 'message' => 'Required parameters not found.');
                            }
                        }
                        elseif($registration_type == 2)
                        {
                            $agency_name = (empty($request->agency_name)) ? null : $request->agency_name;

                            $agency_reg_no = (empty($request->agency_reg_no)) ? null : $request->agency_reg_no;
                            $agency_working_sector = (empty($request->agency_working_sector)) ? null : $request->agency_working_sector;
                            $agency_address = (empty($request->agency_address)) ? null : $request->agency_address;
                            $agency_mob_no = (empty($request->agency_mob_no)) ? null : $request->agency_mob_no;
                            $agency_email = (empty($request->agency_email)) ? null : $request->agency_email;
                            if (!empty($agency_name) && !empty($agency_reg_no) && !empty($agency_working_sector)  && !empty($agency_mob_no) && !empty($agency_email)) {

                                $userData = Users::where('mobile_no',$agency_mob_no)
                                ->orWhere('email_id',$agency_email)
                                ->orWhere('registration_no',$agency_reg_no)
                                ->where('publish', '=', 1)
                                ->first(['mobile_no','email_id']);

                                if (!$userData) {
                                    
                                    $token = md5($agency_reg_no . $agency_mob_no); //token for email
                                    $OTP = $this->generateOTP(6);
                                    $currentDateTime = date('Y-m-d H:i:s');
                                    $verify_by = 1;
                                    $is_verified = 0;
                                    $agency_email = strtolower($agency_email);
                                    $username = strtolower($agency_email);
                                    $password = $this->genRandomStr(8);
                                    $pwd = Hash::make($password);

                                    //for get site id

                                    $domain = \Request::getHost();
                                    $subdomain = explode('.', $domain);
                                    $site_id = Site::select('site_id')->where('site_url',$domain)->first();
                                    $site_id = $site_id['site_id'];

                                    //save data to database
                                    $user = new Users();
                                    $user->username = $username;
                                    $user->fullname= $agency_name;
                                    $user->registration_no= $agency_reg_no;
                                    $user->working_sector= $agency_working_sector;
                                    $user->address= $agency_address;
                                    $user->mobile_no= $agency_mob_no;
                                    $user->email_id= $agency_email;
                                    $user->token= $token;
                                    $user->OTP= $OTP;
                                    $user->device_type= $device_type;
                                    $user->user_type= $registration_type;
                                    $user->verify_by= $verify_by;
                                    $user->is_verified= $is_verified;
                                    $user->password = $pwd;
                                    $user->created_at= $currentDateTime;
                                    $user->site_id = $site_id;
                                    $user->save();
                                    $id = $user->id;

                                    if ($id)
                                    {
                                        
                                        try{
                                            $this->sendSMS($agency_mob_no, "Your OTP for SeQR App Verification is " . $OTP." - Team SSSL");
                                            $message = array('status' => 200, 'message' => 'success', 'otp' => $OTP);
                                        }catch (Exception $ex) {
                                            $message = array('status' => 500, 'message' => $e->getMessage());
                                        }
                                    }else{
                                        return response()->json(['status' => false,'message' => 'Some error occured. Please try again.','code' => 404]);
                                    }
                                }else{
                                    if ($userData['mobile_no'] == $agency_mob_no) {
                                        $message = array('status' => 409, 'message' => 'User already exist with same mobile no.');
                                    } else if ($userData['email_id'] == $agency_email) {
                                        $message = array('status' => 409, 'message' => 'User already exist with same email.');
                                    } else {
                                        $message = array('status' => 409, 'message' => 'User already exist with same registration number.');
                                    }
                                }
                            }else{
                                $message = array('status' => 400, 'message' => 'Required parameters not found.');
                            }
                        }
                        else {
                            $message = array('status' => 404, 'message' => 'Request not found.');
                        }
                    }else{
                        $message = array('status' => 400, 'message' => 'Registration type not selected.');
                    }

                    return $message;
                    break;

                case 'verification':
                    
                    $mobile_no = $request->mobile_no;
                    $otp = $request->otp;

                    if (!empty($mobile_no) && !empty($otp)) {
                    $userData = Users::select('id','fullname','mobile_no','email_id','OTP','is_verified')->where('mobile_no',$mobile_no)->where('publish',1)->first();
                    
                        if ($userData && $userData['is_verified'] == 0) {

                            if ($userData['OTP'] == $otp) {
                            $OTP = $this->generateOTP(6);
                            $password = $this->genRandomStr(8);
                            $pwd = Hash::make($password);

                            $result = Users::where('id', $userData['id'])->update([
                                    'is_verified' => 1,
                                    'status' => 1,
                                    'otp' => $OTP,
                                    'password' => $pwd
                                    ]);
                                if($result)
                                {
                                   $mail_data = Users::select('fullname','email_id','mobile_no')
                                        ->where('id',$userData['id'])
                                        ->first();

                                    $data = ['fullname'=>$mail_data['fullname'],'email_id' => $mail_data['email_id'],$password];
                                    try{

                                        Mail::to($mail_data['email_id'])->send(new LoginMail($data));

                                        $message = array('status' => 200, 'message' => 'You have successfully registered and verified by OTP.');

                                    }catch (Exception $ex) {
                                        $message = array('status' => 500, 'message' => $e->getMessage());
                                    }
                                }
                            } else {
                                $message = array('status' => 422, 'message' => 'You have entered wrong otp.');
                            }
                        } else if ($userData && $userData['is_verified'] == 1) {
                            $message = array('status' => 422, 'message' => 'This user is already verified.');
                        } else {
                            $message = array('status' => 400, 'message' => 'User not found with this mobile number.');
                        } 
                    } else {
                        $message = array('status' => 400, 'message' => 'Required fields missing.');
                    }
                    return $message; 
                    break;

                case 'resendOtp':
                    $mobile_no = $request->mobile_no;
                    if (!empty($mobile_no)) {
                        $userData = Users::select('id','fullname','mobile_no','email_id','OTP','is_verified')->where('mobile_no',$mobile_no)->where('publish',1)->first();
                        if ($userData && $userData['is_verified'] == 0) {

                            $OTP = $this->generateOTP(6);
                            $status = $this->sendSMS($mobile_no, "Your OTP for SeQR App Verification is " . $OTP." - Team SSSL");

                           try {
                                $result = Users::where('id', $userData['id'])->update(['otp' => $OTP]);
                                $message = array('status' => 200, 'message' => 'OTP has been sent to your registered mobile number.', 'otp' => $OTP);
                            } catch (exception $e) {
                                $message = array('status' => 500, 'message' => $e->getMessage());
                            }
                                
                        } else if ($userData && $userData['is_verified'] == 1) {
                            $message = array('status' => 422, 'message' => 'This user is already verified.');
                        } else {
                            $message = array('status' => 400, 'message' => 'User not found with this mobile number.');
                        } 
                    } else {
                        $message = array('status' => 400, 'message' => 'Required fields missing.');
                    }
                    return $message; 
                    break;
                    
                case 'forgotPassword':

                    $email_id = $request->email_id;
                    if (!empty($email_id)) {

                            $userData = Users::select('id','fullname','mobile_no','email_id','OTP','is_verified')->where('email_id',$email_id)->where('publish',1)->first();

                        if ($userData && $userData['is_verified'] == 1) {
                            $name = $userData['fullname'];
                            $password = $this->genRandomStr(8);
                            $pwd = Hash::make($password);
                            try {
                                $result = Users::where('id', $userData['id'])->update([
                                'password' => $pwd
                                ]);
                                $data = ['fullname'=>$name,'email_id' => $email_id,$password];
                                Mail::to($email_id)->send(new ResetPassword($data));
                                $message = array('status' => 200,'success' => true,'message' => 'Password has been sent to your registered email.');
                            } catch (exception $e) {
                                $message = array('status' => 500, 'message' => $e->getMessage());
                            }

                        } else {
                            $message = array('status' => 404, 'message' => 'User not found with this email or may be not verified user.');
                        }

                    } else {
                        $message = array('status' => 400, 'message' => 'Required fields missing.');
                    }
                    return $message;
                    break;
                default:$message = array('status' => 404, 'message' => 'Request not found.');

                }
            } else {
                $message = array('status' => 404, 'message' => 'Request not found.');
            }
        } else {
            $message = array('status' => 403, 'message' => 'Access forbidden.');
        }

        echo json_encode($message);

        $requestUrl = \Request::Url();
        $requestMethod = \Request::method();
        $requestParameter = $data;

        if ($message['status']==true) {
            
            $status = 'success';
        }
        else
        {
            $status = 'failed';
        }

        ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);

        return $message;
    }

    public function generateOTP($digits)
    {
        
        $OTP = rand(pow(10, $digits - 1), pow(10, $digits) - 1); //6 Digit OTP
        return $OTP;
    }
    public function genRandomStr($length)
    {
        $characters = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $string = '';
        for ($p = 0; $p < $length; $p++) {
            $string .= $characters[mt_rand(0, strlen($characters) - 1)];
        }
        return $string;
    }
    public function sendSMS($mobile_no, $text)
    {
        $apiKey = "A32ba2b0a6770c225411fd95ff86401c4";
        $message = urlencode($text);
        $sender_id = "SEQRDC";
        
        $url = "https://alerts.solutionsinfini.com/api/v4/?api_key=" . $apiKey . "&method=sms&message=" . $message . "&to=" . $mobile_no . "&sender=" . $sender_id . "";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $result = curl_exec($ch);
        curl_close($ch);

        $data = (json_decode($result, true));
        if ($data['status'] == "OK") {
            return 1;
        } else {
            return 0;
        }
    }
}

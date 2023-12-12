<?php

namespace App\Http\Controllers\verify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\raisoni\VerificationRequests;
use App\models\raisoni\VerificationDocuments;
use App\models\raisoni\DegreeMaster;
use App\models\User as Users;
use App\models\Site;
use App\models\SessionManager;
use App\models\raisoni\BranchMaster;
use App\Mail\LoginMail;
use App\Mail\ResetPassword;
use App\models\SiteDocuments;
use Mail;
use Auth;
use DB,Hash;

class LoginController extends Controller
{
    //view login page
    public function index(Request $request){

        return view('verify.auth');
    }
    
    //login
    public function login(Request $request)
    {
        if (!empty($request->username) && !empty($request->password)) {
           
            $username = (empty($request->username)) ? null : $request->username;
            $password = (empty($request->password)) ? null : $request->password;
            $userData = Users::where('username',$username)
                        ->where('publish', 1)
                        ->first();          
            $credential1=[

                'username'=>$username,
                'password'=>$password,
                'publish'=>1,
                'site_id'=>$userData['site_id'],
            ];
            
            
            if(!empty($userData)) {
                if (Auth::guard('webuser')->attempt($credential1)) {

                    $user_id=Auth::guard('webuser')->user()->id;

                    $result = Auth::guard('webuser')->user();
                    $session_manager = new SessionManager();
                    $user_id = Auth::guard('webuser')->user()->id;
                    $session_id = \Hash::make(rand(1,1000));
                    $session_manager->user_id = $user_id;
                    $session_manager->session_id = $session_id;
                    $session_manager->login_time = date('Y-m-d H:i:s');
                    $session_manager->is_logged = 1;
                    $session_manager->device_type = 'verify';
                    $session_manager->ip = \Request::ip();
                    $session_manager->site_id=$userData['site_id'];
                    $session_manager->save();

                    $request->session()->put('session_id',$session_manager->id);

                    return response()->json(['status' => true,'message' => 'Logged in Successfully','code' => 200]);
                }else{
                    return response()->json(['status' => false,'message' => 'You have entered wrong password.']);
                } 
            }else{
                return response()->json(['status' => false,'message' => 'User not found!']);
            }
        }else {
            return response()->json(['status' => false,'message' => 'Username or Password is missing']);
        }
    }

    //view dashboard
    public function Dashboard()
    {
        $user_id=Auth::guard('webuser')->user()->id;
        
        return view('verify.home');
    }

    //get degree name
    public function getDegreeName(Request $request)
    {

        $degree_master = DegreeMaster::select('id','degree_name')->where('is_active', 1)->get();
        $optionStr = '<option value="" disabled selected>Select Degree</option>';
        if($degree_master)
        {
            foreach($degree_master as $degree_master)
            {
                $optionStr .= '<option value="' . $degree_master['id'] . '">' . $degree_master['degree_name'] . '</option>';
            }
        }

        return response()->json(['type' => 'success', 'message' => 'success', 'data' => $optionStr]);
    }

    //get branch name
    public function getBranchName(Request $request)
    {
        $degree_id = $request->degree_id;
        $branch_master = BranchMaster::select('id','branch_name_long')->where('is_active', 1)->where('degree_id',$degree_id)->get();
        $optionStr = '<option value="" disabled selected>Select Branch</option>';
            if ($branch_master) {
                foreach ($branch_master as $readValue) {
                    $optionStr .= '<option value="' . $readValue['id'] . '">' . $readValue['branch_name_long'] . '</option>';
                }
            }
        return response()->json(['type' => 'success', 'message' => 'success', 'data' => $optionStr]);   
    }    


    //registration
    public function SignUp(Request $request)
    {
        if(!empty($request->registration_type) || $request->registration_type == 0)
        {
            $registration_type = $request->registration_type;
            $device_type = $request->device_type;
            $domain = \Request::getHost();
            $subdomain = explode('.', $domain);
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

                if (!empty($student_f_name) && !empty($student_l_name) && !empty($student_institute) && !empty($student_degree) && !empty($student_branch) && !empty($passout_year) && !empty($student_reg_no) && !empty($student_mob_no) && !empty($student_email)) 
                {
                    $userData = Users::where('mobile_no',$student_mob_no)
                    ->orWhere('email_id',$student_email)
                    ->orWhere('registration_no',$student_reg_no)
                    ->where('publish',1)
                    ->first(['mobile_no','email_id']);
                    if (!$userData) 
                    {
                        
                        $token = md5($student_reg_no . $student_mob_no); //token for email
                        $OTP = $this->generateOTP(6);

                        $currentDateTime = date('Y-m-d H:i:s');
                        $verify_by = 1;
                        $is_verified = 1;
                        $student_email = strtolower($student_email);
                        $username = strtolower($student_email);
                        $password = $this->genRandomStr(8);
                        $pwd = Hash::make($password);

                        //for get site id
                        

                        $site_id = Site::where('site_url',$domain)->value('site_id');
                      
                    
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
                        
                        $dbName = 'seqr_demo';
        
                        \DB::disconnect('mysql'); 
                        
                        \Config::set("database.connections.mysql", [
                            'driver'   => 'mysql',
                            'host'     => 'seqrdoc.com',
                            "port" => "3306",
                            'database' => $dbName,
                            'username' => 'seqrdoc_multi',
                            'password' => 'SimAYam4G2',
                            "unix_socket" => "",
                            "charset" => "utf8mb4",
                            "collation" => "utf8mb4_unicode_ci",
                            "prefix" => "",
                            "prefix_indexes" => true,
                            "strict" => true,
                            "engine" => null,
                            "options" => []
                        ]);
                        \DB::reconnect();


                        $user_count = Users::select('id')->where(["site_id"=>$site_id])->count();
                        SiteDocuments::where('site_id',$site_id)->update(['total_verifier'=>$user_count]);

                        if($subdomain[0] == 'demo')
                        {
                            $dbName = 'seqr_'.$subdomain[0];
                        }else{

                            $dbName = 'seqr_d_'.$subdomain[0];
                        }

                        \DB::disconnect('mysql');     
                        \Config::set("database.connections.mysql", [
                            'driver'   => 'mysql',
                            'host'     => 'seqrdoc.com',
                            "port" => "3306",
                            'database' => $dbName,
                            'username' => 'seqrdoc_multi',
                            'password' => 'SimAYam4G2',
                            "unix_socket" => "",
                            "charset" => "utf8mb4",
                            "collation" => "utf8mb4_unicode_ci",
                            "prefix" => "",
                            "prefix_indexes" => true,
                            "strict" => true,
                            "engine" => null,
                            "options" => []
                        ]);
                        \DB::reconnect();


                        if ($id)
                        {

                            //$this->sendSMS($student_mob_no, "Your OTP for SeQR App Verification is " . $OTP." - Team SSSL");
                            $mail_data = Users::select('fullname','email_id','mobile_no')
                                    ->where('id',$id)
                                    ->first();

                            $data = ['fullname'=>$mail_data['fullname'],'email_id' => $mail_data['email_id'],$password];
                            try{

                                Mail::to($mail_data['email_id'])->send(new LoginMail($data));

                                return response()->json(['status' => true,'message' => 'Password has been sent to your registered email.','code' => 200]);
                            }catch (Exception $ex) {
                                return response()->json(['status' => false,'message' => $ex->getMessage()]);
                            }
                            
                        }else{
                            return response()->json(['status' => false,'message' => 'Some error occured. Please try again.','code' => 404]);
                        }
                    }else{
                        if ($userData['mobile_no'] == $student_mob_no) {
                            return response()->json(['status' => 'error','message' => 'User already exist with same mobile no.']);
                        } else if ($userData['email_id'] == $student_email) {
                            return response()->json(['status' => 'error','message' => 'User already exist with same email.']);
                        } else {
                            return response()->json(['status' => 'error','message' => 'User already exist with same registration number.']);
                        }
                    }
                }else{
                    return response()->json(['status' => 'error','message' => 'Required parameters not found.']);
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
                
                if (!empty($employer_name) && !empty($employer_reg_no) && !empty($employer_working_sector) && !empty($employer_address) && !empty($employer_mob_no) && !empty($employer_email)) {

                    $userData = Users::where('mobile_no',$employer_mob_no)
                    ->orWhere('email_id',$employer_email)
                    ->orWhere('registration_no',$employer_reg_no)
                    ->where('publish', 1)
                    ->first(['mobile_no','email_id']);


                    if (!$userData) {

                        $token = md5($employer_reg_no . $employer_mob_no); //token for email
                        $OTP = $this->generateOTP(6);
                        $currentDateTime = date('Y-m-d H:i:s');
                        $verify_by = 1;
                        $is_verified = 1;
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

                        $dbName = 'seqr_demo';
        
                        \DB::disconnect('mysql'); 
                        
                        \Config::set("database.connections.mysql", [
                            'driver'   => 'mysql',
                            'host'     => 'seqrdoc.com',
                            "port" => "3306",
                            'database' => $dbName,
                            'username' => 'seqrdoc_multi',
                            'password' => 'SimAYam4G2',
                            "unix_socket" => "",
                            "charset" => "utf8mb4",
                            "collation" => "utf8mb4_unicode_ci",
                            "prefix" => "",
                            "prefix_indexes" => true,
                            "strict" => true,
                            "engine" => null,
                            "options" => []
                        ]);
                        \DB::reconnect();


                        $user_count = Users::select('id')->where(["site_id"=>$site_id])->count();
                        SiteDocuments::where('site_id',$site_id)->update(['total_verifier'=>$user_count]);

                        if($subdomain[0] == 'demo')
                        {
                            $dbName = 'seqr_'.$subdomain[0];
                        }else{

                            $dbName = 'seqr_d_'.$subdomain[0];
                        }

                        \DB::disconnect('mysql');     
                        \Config::set("database.connections.mysql", [
                            'driver'   => 'mysql',
                            'host'     => 'seqrdoc.com',
                            "port" => "3306",
                            'database' => $dbName,
                            'username' => 'seqrdoc_multi',
                            'password' => 'SimAYam4G2',
                            "unix_socket" => "",
                            "charset" => "utf8mb4",
                            "collation" => "utf8mb4_unicode_ci",
                            "prefix" => "",
                            "prefix_indexes" => true,
                            "strict" => true,
                            "engine" => null,
                            "options" => []
                        ]);
                        \DB::reconnect();
                        if ($id)
                        {
                            //$this->sendSMS($employer_mob_no, "Your OTP for SeQR App Verification is " . $OTP." - Team SSSL");
                            $mail_data = Users::select('fullname','email_id','mobile_no')
                                    ->where('id',$id)
                                    ->first();
                            $data = ['fullname'=>$mail_data['fullname'],'email_id' => $mail_data['email_id'],$password];

                            try{
                                Mail::to($mail_data['email_id'])->send(new LoginMail($data));
                                
                                return response()->json(['status' => true,'message' => 'Password has been sent to your registered email.','code' => 200]);
                                

                            }catch (Exception $ex) {
                                return response()->json(['status' => false,'message' => $ex->getMessage()]);
                            }
                            
                        }else{
                            return response()->json(['status' => false,'message' => 'Some error occured. Please try again.','code' => 404]);
                        }
                    }else{
                        if ($userData['mobile_no'] == $employer_mob_no) {
                            return response()->json(['status' => 'error','message' => 'User already exist with same mobile no.']);
                        } else if ($userData['email_id'] == $employer_email) {
                            return response()->json(['status' => 'error','message' => 'User already exist with same email.']);
                        } else {
                            return response()->json(['status' => 'error','message' => 'User already exist with same registration number.']);
                        }
                    }
                }else{
                    return response()->json(['status' => 'error','message' => 'Required parameters not found.']);
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
                if (!empty($agency_name) && !empty($agency_reg_no) && !empty($agency_working_sector) && !empty($agency_address) && !empty($agency_mob_no) && !empty($agency_email)) {

                    $userData = Users::where('mobile_no',$agency_mob_no)
                    ->orWhere('email_id',$agency_email)
                    ->orWhere('registration_no',$agency_reg_no)
                    ->where('publish', 1)
                    ->first(['mobile_no','email_id']);

                    if (!$userData) {
                        
                        $token = md5($agency_reg_no . $agency_mob_no); //token for email
                        $OTP = $this->generateOTP(6);
                        $currentDateTime = date('Y-m-d H:i:s');
                        $verify_by = 1;
                        $is_verified = 1;
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

                        $dbName = 'seqr_demo';
        
                        \DB::disconnect('mysql'); 
                        
                        \Config::set("database.connections.mysql", [
                            'driver'   => 'mysql',
                            'host'     => 'seqrdoc.com',
                            "port" => "3306",
                            'database' => $dbName,
                            'username' => 'seqrdoc_multi',
                            'password' => 'SimAYam4G2',
                            "unix_socket" => "",
                            "charset" => "utf8mb4",
                            "collation" => "utf8mb4_unicode_ci",
                            "prefix" => "",
                            "prefix_indexes" => true,
                            "strict" => true,
                            "engine" => null,
                            "options" => []
                        ]);
                        \DB::reconnect();


                        $user_count = Users::select('id')->where(["site_id"=>$site_id])->count();
                        SiteDocuments::where('site_id',$site_id)->update(['total_verifier'=>$user_count]);

                        if($subdomain[0] == 'demo')
                        {
                            $dbName = 'seqr_'.$subdomain[0];
                        }else{

                            $dbName = 'seqr_d_'.$subdomain[0];
                        }

                        \DB::disconnect('mysql');     
                        \Config::set("database.connections.mysql", [
                            'driver'   => 'mysql',
                            'host'     => 'seqrdoc.com',
                            "port" => "3306",
                            'database' => $dbName,
                            'username' => 'seqrdoc_multi',
                            'password' => 'SimAYam4G2',
                            "unix_socket" => "",
                            "charset" => "utf8mb4",
                            "collation" => "utf8mb4_unicode_ci",
                            "prefix" => "",
                            "prefix_indexes" => true,
                            "strict" => true,
                            "engine" => null,
                            "options" => []
                        ]);
                        \DB::reconnect();

                        if ($id)
                        {
                            //$this->sendSMS($agency_mob_no, "Your OTP for SeQR App Verification is " . $OTP." - Team SSSL");
                            $mail_data = Users::select('fullname','email_id','mobile_no')
                                    ->where('id',$id)
                                    ->first();

                            $data = ['fullname'=>$mail_data['fullname'],'email_id' => $mail_data['email_id'],$password];

                            try{
                                Mail::to($mail_data['email_id'])->send(new LoginMail($data));

                                return response()->json(['status' => true,'message' => 'Password has been sent to your registered email.','code'=> 200,'verification_method' => $verify_by,'mobile_no' => $agency_mob_no]);

                            }catch (Exception $ex) {
                                return response()->json(['status' => false,'message' => $ex->getMessage()]);
                            }
                        }else{
                            return response()->json(['status' => false,'message' => 'Some error occured. Please try again.','code' => 404]);
                        }
                    }else{
                        if ($userData['mobile_no'] == $agency_mob_no) {
                            return response()->json(['status' => 'error','message' => 'User already exist with same mobile no.']);
                        } else if ($userData['email_id'] == $agency_email) {
                            return response()->json(['status' => 'error','message' => 'User already exist with same email.']);
                        } else {
                            return response()->json(['status' => 'error','message' => 'User already exist with same registration number.']);
                        }
                    }
                }else{
                    return response()->json(['status' => 'error','message' => 'Required parameters not found.']);
                }
            }
            else {
                return response()->json(['status' => 'error','message' => 'Request not found.']);
            }
        }else{
            return response()->json(['status' => 'error','message' => 'Registration type not selected.']);
        }
    }

    //forgot password 
    public function ForgotPassword(Request $request)
    {
        $email_id = (empty($request->forgot_pwd_email)) ? null : $request->forgot_pwd_email;
        if (!empty($email_id)) {
            $userData = Users::select('id','fullname','mobile_no','email_id','OTP','is_verified')
            ->where('email_id',$email_id)
            ->where('publish', 1)
            ->first();
            
            if ($userData && $userData['is_verified'] == 1) 
            {
                $name = $userData['fullname'];
                $password = $this->genRandomStr(8);
                $pwd = Hash::make($password);
                DB::table('user_table')
                ->where('id',$userData['id'])
                ->update(['password' => $pwd]);

                $data = ['fullname'=>$name,'email_id' => $email_id,$password];
                try {
                        Mail::to($email_id)->send(new ResetPassword($data));
                        return response()->json(['status' => true,'message' => 'Password has been sent to your registered email.']);
                    
                } catch (Exception $ex) {
                    return response()->json(['status' => false,'message' => 'Something went wrong.']);
                }
            } else{
                return response()->json(['status' => false,'message' => 'User not found with this email address or may be not verified user.']);
            }
        }
    }

    //genratde otp after registration
    public function generateOTP($digits)
    {
        
        $OTP = rand(pow(10, $digits - 1), pow(10, $digits) - 1); //6 Digit OTP
        return $OTP;
    }

    //genrate random password
    public function genRandomStr($length)
    {
        $characters = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $string = '';
        for ($p = 0; $p < $length; $p++) {
            $string .= $characters[mt_rand(0, strlen($characters) - 1)];
        }
        return $string;
    }

    //send otp in sms
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




    public function pendingPayments(Request $request){

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
            $user_id=Auth::guard('webuser')->user()->id;
            $where_str .= " and (payment_status='Pending')";
            $where_str .= " and (user_id=".$user_id.")";
           
            DB::statement(DB::raw('set @rownum=0'));   
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'id','request_number','student_name','no_of_documents','created_date_time','total_amount'];
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

            $backgroundTemplateMaster = $backgroundTemplateMaster->get();
            
             $response['iTotalDisplayRecords'] = $backgroundTemplateMaster_count;
             $response['iTotalRecords'] = $backgroundTemplateMaster_count;

            $response['sEcho'] = intval($request->get('sEcho'));

            $response['aaData'] = $backgroundTemplateMaster;

            return $response;
        }
    	return view('verify.pending_payment');
    	
    }
    public function pendingPaymentsInfo(Request $request){

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
    public function pendingPaymentsRemove(Request $request){
    	$request_id = (isset($request['request_id'])) ? $request['request_id'] : '';
    	
		if (!empty($request_id)) {

			$result = VerificationRequests::where('id',$request_id)->count();
			
			if($result > 0){

				$errorMessage = false;

				VerificationRequests::where('id',$request_id)->delete();

				VerificationDocuments::where('request_id',$request_id)->delete();

				$request_number = VerificationRequests::where('id',$request_id)->value('request_number');

				
				$domain =$_SERVER['HTTP_HOST'];
				$subdomain = explode('.', $domain);
				$dirPath = public_path() . '/'.$subdomain[0].'/uploads/' . $request_number;
				$this->deleteDirectory($dirPath);
				$message = array('type' => 'success', 'message' => 'Request deleted successfully.');
			}else{

				$message = array('type' => 'error', 'message' => 'Something went wrong.');
			}

		} else {
			$message = array('type' => 'error', 'message' => 'Required fields not found.');
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
    public function logout(){

        $session_id = session()->get('session_id');
        
        $user_id = Auth::guard('webuser')->user()->id;
        $logout_time = date('Y-m-d H:i:s');
    
        $query=SessionManager::where(['user_id'=>$user_id,'id'=>$session_id])->update(['logout_time'=>$logout_time,'is_logged'=>0]);  
        session()->forget('session_id');
        Auth::guard('webuser')->logout();

        return response()->json(['type' => 'success']);
        
    }
    function deleteDirectory($dirPath) {
	
		if (is_dir($dirPath)) {
			$objects = scandir($dirPath);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (filetype($dirPath . DIRECTORY_SEPARATOR . $object) == "dir") {
						$this->deleteDirectory($dirPath . DIRECTORY_SEPARATOR . $object);
					} else {
						unlink($dirPath . DIRECTORY_SEPARATOR . $object);
					}
				}
			}
			reset($objects);
			rmdir($dirPath);
		}
	}
}

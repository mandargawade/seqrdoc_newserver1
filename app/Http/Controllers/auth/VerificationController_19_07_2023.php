<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\VerifiesEmails;
use DB;
use App\models\demo\Site as Demosite;
use Auth;
use Helper;
use App\Http\Requests\WebUserVerificationRequest;
use App\Models\User;
use App\Jobs\SendMailJob;
class VerificationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Email Verification Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling email verification for any
    | user that recently registered with the application. Emails may also
    | be re-sent if the user didn't receive the original email message.
    |
    */

    use VerifiesEmails;

    /**
     * Where to redirect users after verification.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function showVerify($token)
    {


        $site_id=Helper::GetSiteId($_SERVER['SERVER_NAME']);
      $site_url = explode(".",$_SERVER['SERVER_NAME']);
      $sitename = ucfirst($site_url[0]);
  
      $site_data = Demosite::select('apple_app_url','android_app_url')->where('site_id',$site_id)->first();

      if(!empty($token)){
        

         $check_verify = \DB::table('user_table')->select('email_id','is_verified')->where('token',$token)->first();//email

         if($check_verify&&$check_verify->is_verified == 1){
          $isVerified=1;
         }else{
          $isVerified=0;
         }
         $email=$check_verify->email_id;
      }else{
        $email="";
        $isVerified=0;
      }
      /*print_r($check_verify);
      exit;*/
        return view('auth.verify',compact('site_data','sitename','email','isVerified'));
    }
    //during register if select verify by mail then mail verification
    public function verifyUser($token){
        if(!empty($token)){
            $check_verify = \DB::table('user_table')->select('is_verified')->where('token',$token)->first();
          if($check_verify){
            if($check_verify->is_verified == 1){
                $status = 2;//already verified
            }
            else{
                $status = 1;//verify successfully
                \DB::table('user_table')->where('token',$token)->update(['is_verified'=>'1']);
            }
          }else{
             $status = 0;
          }
        }
        else{
            $status = 0;//token is invalid
        }
        return view('auth.verified',compact('status'));
    }

    public function resendVerificationLink(WebUserVerificationRequest $request){


            $userData = User::select('username','token','is_verified')->where('email_id',$request['email_id'])->first();
         
            if($userData){

              if($userData['is_verified']!=1){
                //sending mail
                $mail_view = 'mail.index';
                $user_email = $request['email_id'];
                $mail_subject = 'Activate your account for SeQR Mobile App';
                $user_data = ['name'=>$userData['username'],'token'=>$userData['token']];

                $this->dispatch(new SendMailJob($mail_view,$user_email,$mail_subject,$user_data));
                return response()->json(array(
                'status' => 200,
                'message'=>"Verification link sent successfully."
            )); 
              }else{
                 return response()->json(array(
                'status' => 201,
                'message'=>"You are verified user. Please login using username & password."
            )); 
              }
            }else{
              return response()->json(array(
                'status' => 405,
                'message'=>"Something went wrong."
            )); 
            }
           
            
    }

    public function checkVerificationStatus(WebUserVerificationRequest $request){


            $userData = User::select('username','token','is_verified')->where('email_id',$request['email_id'])->first();
         
            if($userData){

              if($userData['is_verified']==1){
                return response()->json(array(
                    'status' => 200,
                    'message'=>"You are verified user. Please login using username & password."
                )); 
              }else{
                return response()->json(array(
                    'status' => 405,
                    'message'=>"You are not verified user."
                )); 
              }
            }else{
              return response()->json(array(
                'status' => 405,
                'message'=>"Something went wrong."
            )); 
            }
           
            
    }
}

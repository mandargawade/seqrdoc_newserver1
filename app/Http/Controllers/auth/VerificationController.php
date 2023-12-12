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

use App\Models\PasswordReset;
use Illuminate\Http\Request;
use Validator;
use Str;

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


    public function authVerificationLink(Request $request){
      // ajax validation 
      $validation_rules = [
          'email_id' => 'required'
      ];
      $validation = Validator::make($request->all(), $validation_rules);
      if ($validation->fails()) {
          return json_encode([
              'errors' => $validation->errors()->getMessages(),
              'code' => 422,
          ]);
      }
      $userData = User::select('fullname','username','token','is_verified')->where('email_id',$request['email_id'])->first();
      
      if($userData){
          $token = Str::random(64);
          $PasswordReset = new PasswordReset();
          $PasswordReset->email = $request->email_id;
          $PasswordReset->token = $token;
          $PasswordReset->created_at =date('Y-m-d H:i:s');
          $PasswordReset->save();
          // DB::table('password_resets')->insert([
          //   'email' => $request->email_id,
          //   'token' => $request->_token,
          //   'created_at' => date('Y-m-d H:i:s')
          // ]);

          //Get the token just created above
          $tokenData = PasswordReset::where('email', $request->email_id)->orderBy('created_at', 'DESC')->first();

          //sending mail
          $mail_view = 'mail.auth_index';
          $user_email = $request['email_id'];
          $mail_subject = 'SeQR Doc App: Reset your password';
          // $mail_subject = ucfirst($userData['fullname']).', this email will help you reset your password';
          $user_data = ['name'=>$userData['fullname'],'token'=>$tokenData->token];

          $this->dispatch(new SendMailJob($mail_view,$user_email,$mail_subject,$user_data));
          return response()->json(array(
            'status' => 200,
            'message'=>"Verification link sent successfully."
          )); 
      }else{
        return response()->json(array(
          'status' => 405,
          'message'=>"Something went wrong. user not exist"
        )); 
      }   
    }

    public function authUserPasswordreset($token) {
      $tokenData = PasswordReset::where('token', $token)->orderBy('created_at', 'DESC')->first();
      if($tokenData) {
        //echo $tokenData->created_at;
        $addExpiryTime = Date("Y-m-d H:i:s", strtotime("30 minutes", strtotime($tokenData->created_at)));
        
        if($addExpiryTime > Date("Y-m-d H:i:s") ){
          return view('auth.auth_password_reset',[
            'token' => $token,
            'email' => $tokenData->email,
          ]);
        } else {
          //Delete the token
          PasswordReset::where('email', $user->email_id)->delete();
          return view('auth.password_reset_expiry',[
            'token' => $token,
            'email' => $tokenData->email,
          ]);        
        }
        
        
      } else {

        return view('auth.password_reset_expiry',[
          'token' => $token,
          'email' => $tokenData->email,
        ]);
        
      }
      
    }


    public function passwordResetUpdate(Request $request) {
      
      // ajax validation 
      $validation_rules = [
        'email_id' => 'required|email|exists:user_table,email_id',
        'password'=>'required|min:6|max:32',
        'confirm_password' => 'required_with:password|same:password',
        'token' => 'required'
      ];
      $validation = Validator::make($request->all(), $validation_rules);
      if ($validation->fails()) {
        return json_encode([
          'errors' => $validation->errors()->getMessages(),
          'code' => 422,
        ]);
      }
      
      $password = $request->password;
      // Validate the token
      $tokenData = PasswordReset::where('token', $request->token)->first();

      //dd(\DB::connection()->getPDO());
      if($tokenData){
          $user = User::where('email_id', $tokenData->email)->first();
          if($user) {
            //Hash and update the new password
            $user->password = \Hash::make($password);
            $user->update(); //or $user->save();

            //Delete the token
            PasswordReset::where('email', $user->email_id)->delete();
            // PasswordReset::where('email', $user->email_id)->delete();

            // //sending mail
            // $mail_view = 'mail.auth_password_success';
            // $user_email = $request['email_id'];
            // $mail_subject = 'Update password for SeQR App';
            // $user_data = ['name'=>$user['fullname'],'username'=>$user['username'],'password'=>$password];

            //Mail::to($email_id)->send(new ResetPassword($data));
            // $this->dispatch(new SendMailJob($mail_view,$user_email,$mail_subject,$user_data));
            
            return response()->json(array(
              'status' => 200,
              'message'=>"Password change successfully."
            )); 
          } else {
            return response()->json(array(
              'status' => 405,
              'message'=>"Something went wrong.Email not found"
            ));
          }
      } else {
        return response()->json(array(
          'status' => 405,
          'message'=>"Something went wrong.token is invalid"
        )); 
      }
      //if (!$tokenData) return view('auth.passwords.email');


      echo "success";

    }
    
}

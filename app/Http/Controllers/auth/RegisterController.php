<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 13/11/2019
 *   Use   : Register new Webuser
 *
**/
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebUserRegisterRequest;
use App\Jobs\SendMailJob;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\models\Site;
use URL;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\User
     */
     public function userRegister(WebUserRegisterRequest $request){

            if(isset($request->device_type)){
                $device_type = $request->device_type; //web/mobile
            }
            else{
                $device_type = 'mobile';
              }
             $request['verify_by']=2; 
            $generate_token = Hash::make($request->username.$request->mobile_no);
            $token = str_replace('/','',$generate_token);
            $OTP = $this->generateOTP();

            $domain = \Request::getHost();
            $subdomain = explode('.', $domain);

            $site_id = Site::select('site_id')->where('site_url',$domain)->first();
            

            $webuser = User::create([
                'fullname' => $request['fullname'],
                'username' => $request['username'],
                'password' => Hash::make($request['password']),
                'email_id' => $request['email_id'],
                'email_id' => $request['email_id'],                
                'mobile_no' => $request['mobile_no'],
                'verify_by' => $request['verify_by'],
                'status' => '1',
                'device_type' => $device_type,
                'token' => $token,
                'OTP' => $OTP,
                'site_id'=>$site_id['site_id'],
            ]);
            User::where('id',$webuser['id'])->update(['site_id'=>$site_id['site_id']]);
            
            if($request['verify_by'] == 2){
                //sending mail
                $mail_view = 'mail.index';
                $user_email = $request['email_id'];
                
                $mail_subject = 'Activate your account for SeQR Mobile App';
                $user_data = ['name'=>$request['username'],'token'=>$token];

                $this->dispatch(new SendMailJob($mail_view,$user_email,$mail_subject,$user_data));
            }
           
            return response()->json(array(
                'status' => true,
                'link' =>  URL::route('webapp.verify',$request['email_id']),
            )); 
    }
     public function generateOTP(){
        $digits = 5;
        $OTP = rand(pow(10, $digits-1), pow(10, $digits)-1); //5 Digit OTP
        return $OTP;
    }
}

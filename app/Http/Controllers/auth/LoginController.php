<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 13/11/2019
 *   Use   : check specific login detail
 *
**/
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebUserRequest;
use App\models\SessionManager;
use App\models\User;
use App\models\demo\Site as Demosite;
use Auth;
use Helper;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use App\Jobs\SendMailJob;
use URL;
class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */
    
    use AuthenticatesUsers;
    
/*    public function __construct()
    {
      $this->middleware('WebUser:webuser')->except('web.logout');
    }*/
    public function showWebUserLogin()
    {
      
      $site_id=Helper::GetSiteId($_SERVER['SERVER_NAME']);
      $site_url = explode(".",$_SERVER['SERVER_NAME']);
      if($site_id == 237)
     {
          $sitename = ucfirst("IIT Jammu");
     }else{
          $sitename = ucfirst($site_url[0]);
     }
      
  
      $site_data = Demosite::select('apple_app_url','android_app_url')->where('site_id',$site_id)->first();
      
      return view('auth.userlogin',compact('site_data','sitename'));
    }
    public function webuserLogin(WebUserRequest $request)
    {
       
     $site_id=Helper::GetSiteId($_SERVER['SERVER_NAME']);
     $site_url = explode(".",$_SERVER['SERVER_NAME']);
     if($site_id == 237)
     {
          $sitename = ucfirst("IIT Jammu");
     }else{
          $sitename = ucfirst($site_url[0]);
     }
     
      
      $credential1=[

        'username'=>$request->username,
        'password'=>$request->password,
        
        'publish'=>1,
        'site_id'=>$site_id,
      ];
      

      
  
     if($site_id!=null)
     {
        $userData = User::select('*')->where('username',$request->username)->first();
        
        if(!$userData){
          return response()->json(['success'=>false,'msg'=>'User not found with this username.']); 
        }else if($userData&&$userData['is_verified']!=1){

           if($userData['verify_by'] == 2){
                //sending mail
                $mail_view = 'mail.index';
                $user_email = $userData['email_id'];
                
                $mail_subject = 'Activate your account for SeQR Mobile App';
                $user_data = ['name'=>$userData['username'],'token'=>$userData['token']];

                $this->dispatch(new SendMailJob($mail_view,$user_email,$mail_subject,$user_data));
              }
               return response()->json(['success'=>200,'msg'=>'Your verification is pending. Please check your email for verification link.',
                'link' =>  URL::route('webapp.verify',$userData['email_id'])]); 

        }else if($userData&&$userData['status']!=1){
          return response()->json(['success'=>false,'msg'=>'Your account has been deactivated! Please contact to system administrator.']);
        }else if(Auth::guard('webuser')->attempt($credential1))
        {   
            $result = Auth::guard('webuser')->user();

       

                //store user's info in session manager when login user  
                $session_manager = new SessionManager();
                
                $user_id = Auth::guard('webuser')->user()->id;
                $session_id = \Hash::make(rand(1,1000));
                $session_manager->user_id = $user_id;
                $session_manager->session_id = $session_id;
                $session_manager->login_time = date('Y-m-d H:i:s');
                $session_manager->is_logged = 1;
                $session_manager->device_type = 'webuser';
                $session_manager->ip = \Request::ip();
                $session_manager->site_id=$site_id;
                $session_manager->save();

                $auth_id=Auth::guard('webuser')->user()->id; 
                $insert_id=User::where('id',$auth_id)->update(['site_id'=>$site_id]);
                
                 // put value in session
                 $request->session()->put('session_id',$session_manager['id']);
                 $request->session()->put('site_name',$sitename);

                 return response()->json(['success'=>true]);  
          
       }
      else
       {
        return response()->json(['success'=>false,'msg'=>'These credentials do not match our records.']);  
       }
     }
     else
     {
      
       return response()->json(['success'=>'Not','msg'=>'Please Contact Service Porvider']);
     }
   }
   public function webLogout(Request $request)
    {
       // when auto logout user then store logout time and forget session value

        $session_val = $request->session()->get('session_id');
        $this->sessionLogout($session_val);
        $request->session()->forget('session_id');

        Auth::guard('webuser')->logout();

        return $this->loggedOut($request) ?: redirect()->route('webapp.index');
    }
    public function autoLogout(Request $request){
      // when auto logout user then store logout time and forget session value

      $session_val = $request->session()->get('session_id');
      
      $this->sessionLogout($session_val);

      $request->session()->forget('session_id');

      Auth::guard('webuser')->logout();
      
    }
     public function sessionLogout($session_val){
      
      // call seperate function to logout
      $session_manager = SessionManager::find($session_val);
      
      $session_manager->logout_time = date('Y-m-d H:i:s');
      $session_manager->is_logged = 0;
      
      $session_manager->save();

    }
  
}

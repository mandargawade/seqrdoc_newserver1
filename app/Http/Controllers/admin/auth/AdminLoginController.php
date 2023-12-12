<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 13/11/2019
 *   Use   : check specific login detail
 *
**/
namespace App\Http\Controllers\Admin\auth;

use App\Http\Controllers\Controller;
use App\models\Admin;
use App\models\SessionManager;
use App\models\Site;
use App\models\demo\Site as Demosite;
use Helper;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Helpers\CoreHelper;

class AdminLoginController extends Controller
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

    public function showLoginForm()
    {
      $site_id=Helper::GetSiteId($_SERVER['SERVER_NAME']);
      $site_url = explode(".",$_SERVER['SERVER_NAME']);
      $sitename = ucfirst($site_url[0]);
      //echo $sitename;
      //echo $site_id;
      //if($site_id!=null)
      //{
        $site_data = Demosite::select('apple_app_url','android_app_url')->where('site_id',$site_id)->first();
      //}

      //return view("admin.auth.login");

       return view("admin.auth.login", compact('site_data','sitename'));
    }  

   public function login(Request $request)
    {

     $this->validate($request,[
       "username" =>"required",
       "password"=>"required"
     ],
     [
        'username.required'=>'The Username Field is Required.',
        'password.required'=>'The Password Field is Required.',
     ]
     );
    /* echo \Hash::make('manoj@monad');
      exit;*/
     // dd($_SERVER['SERVER_NAME']);
     /*if($_SERVER['SERVER_NAME']=="ghribmjal.seqrdoc.com"){
      echo $_SERVER['SERVER_NAME'];
     }*/
     $site_id=Helper::GetSiteId($_SERVER['SERVER_NAME']);
   
    
     $credential=[

       "username"=>$request->username,
       "password"=>$request->password,
       'publish'=>1,
       'site_id'=>$site_id,
     ];

     if($site_id!=null)
     {
        $site_data = Site::select('start_date','end_date')->where('site_id',$site_id)->first();
        $site_url = explode(".",$_SERVER['SERVER_NAME']);
        $sitename = ucfirst($site_url[0]);

        $today = date('Y-m-d');
        $start_date = $site_data['start_date'];
        $end_date = $site_data['end_date'];
        /*echo $request->password;
        echo "<br>";
        echo  $generate_password = \Hash::make($request->password);*/ 
        if($today >= $start_date && $today <= $end_date){

          if(Auth::guard('admin')->attempt($credential))
          {

            //store user's info in session manager when login user
            $session_manager = new SessionManager();
            
            $user_id = Auth::guard('admin')->user()->id;
            $session_id = \Hash::make(rand(1,1000));
            $session_manager->user_id = $user_id;
            $session_manager->session_id = $session_id;
            $session_manager->login_time = date('Y-m-d H:i:s');
            $session_manager->is_logged = 1;
            $session_manager->device_type = 'webAdmin';
            $session_manager->ip = \Request::ip();
            $session_manager->site_id = $site_id;
            $session_manager->save();
            
            $auth_id=Auth::guard('admin')->user()->id; 
            $insert_id=Admin::where('id',$auth_id)->update(['site_id'=>$site_id]);

            // put value in session
            $request->session()->put('session_id',$session_manager['id']);
            $request->session()->put('site_name',$sitename);

            $recordToGenerate=0;
            $checkStatus = CoreHelper::checkMaxCertificateLimit($recordToGenerate);
            
            return response()->json(['success'=>true,'msg'=>'Login Successfully']);   
          }
          else
          {
            return response()->json(['success'=>false,'msg'=>'These Credentials do not match our records.']);  
          }
        }else{

          return response()->json(['success'=>false,'msg'=>'Your service is not started yet or your service is expired please contact service provider']);  
        }
          
      }
      else
      {
       return response()->json(['success'=>'error','msg'=>'Please Contact Service Porvider']);
      }
   }

   public function logout(Request $request)
   {
      // when logout user then store logout time and forget session value
      $session_val = $request->session()->get('session_id');
      
      $this->sessionLogout($session_val);
      
      $request->session()->forget('session_id');

      Auth::guard('admin')->logout();
      
      return redirect()->route('admin.login');            
  }  
    protected function guard()
    {
        return Auth::guard('admin');
    } 
  
    public function autoLogout(Request $request){
      // when auto logout user then store logout time and forget session value

      $session_val = $request->session()->get('session_id');
      
      $this->sessionLogout($session_val);

      $request->session()->forget('session_id');

      Auth::guard('admin')->logout();

      return redirect()->route('admin.login');
    }

    public function sessionLogout($session_val){
      // call seperate function to logout
      if (!empty($session_val)) {
         
      $session_manager = SessionManager::find($session_val);
      
      $session_manager->logout_time = date('Y-m-d H:i:s');
      $session_manager->is_logged = 0;
      
      $session_manager->save();
      }

    }
}

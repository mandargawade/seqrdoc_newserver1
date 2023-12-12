<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 1/12/2019
 *   Use   : update & store Mail Credential of production
 *
**/
namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SystemSettingRequest;
use App\models\SystemConfig;
use App\models\Demo\SuperAdmin as DemoSuperAdmin;
use App\models\Demo\Site as DemoSite;
use Illuminate\Http\Request;
use Auth;
class SystemSettingController extends Controller
{
    /**
     * Display a listing of the SystemSetting.
     */
    public function index(Request $request)
    {
     
      $auth_site_id=Auth::guard('admin')->user()->site_id;

      $systemConfig=SystemConfig::get()->where('site_id',$auth_site_id)->first();

      /*______SCRIPT START khushboo Choudhari_________*/
      //get value,current value from super admin
      $printing_value = DemoSuperAdmin::select('value','current_value')->where('property','print_limit')->where('site_id',$auth_site_id)->first();

      $site_data = DemoSite::select('license_key','start_date','end_date')->where('site_id',$auth_site_id)->first();
      /*______SCRIPT END khushboo Choudhari_________*/

      // dd($systemConfig);	
      return view('admin.systemSetting.index',compact('systemConfig','printing_value','site_data'));
    }
    /**
     * Store a newly created SystemSetting in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
    */
    public function store(SystemSettingRequest $request){
        $id=null;
       
         if(!empty($request['id']))
         {
           $id=$request['id'];
         }
         
         $auth_site_id=Auth::guard('admin')->user()->site_id;

         $systemConfig=SystemConfig::firstOrNew(['id'=>$id]);
         $systemConfig->fill($request->all());
         $systemConfig->site_id=$auth_site_id;
         $systemConfig->save();
         
         $MailCredentialExit=SystemConfig::select('smtp','port','sender_email','password')->where('site_id',$auth_site_id)->first();
         
         // true live Credential set or local
         if(!empty($MailCredentialExit['smtp']) && !empty($MailCredentialExit['port']) && !empty($MailCredentialExit['sender_email']) && !empty($MailCredentialExit['password']))
         {
              // Live Credential of Mail
               $liveCredentialMail=[
                     "MAIL_HOST"=>'MAIL_HOST',
                     "MAIL_HOST_value"=>$MailCredentialExit['smtp'],
                     "MAIL_PORT"=>'MAIL_PORT',
                     "MAIL_PORT_value"=>$MailCredentialExit['port'],
                     "MAIL_USERNAME"=>'MAIL_USERNAME',
                     "MAIL_USERNAME_value"=>$MailCredentialExit['sender_email'],
                     "MAIL_PASSWORD"=>'MAIL_PASSWORD',
                     "MAIL_PASSWORD_value"=>$MailCredentialExit['password'],
               ];

               $this->mailCredentialSet($liveCredentialMail);
         }
         else
         {
            // Default Credential of Mail
             $localCredentialMail=[
                   
                     "MAIL_HOST"=>'MAIL_HOST',
                     "MAIL_HOST_value"=>'smtp.mailtrap.io',
                     "MAIL_PORT"=>'MAIL_PORT',
                     "MAIL_PORT_value"=>'2525',
                     "MAIL_USERNAME"=>'MAIL_USERNAME',
                     "MAIL_USERNAME_value"=>'5cdafc1c984215',
                     "MAIL_PASSWORD"=>'MAIL_PASSWORD',
                     "MAIL_PASSWORD_value"=>'0a183a7369ba32',
               ];

            $this->mailCredentialSet($localCredentialMail);
         }
         return $systemConfig ? response()->json(['success'=>true,'sandboxing'=>$request['sandboxing']]) : "false"; 
    }
     /**
     * .env file set default or production credential of Mail.
     *
     * @param  array  $credentialMail
    */
    public function mailCredentialSet(array $credentialMail){
           
       if (!empty($credentialMail)) {
           
           $MAIL_HOST=$credentialMail['MAIL_HOST'];
           $MAIL_HOST_value=$credentialMail['MAIL_HOST_value'];
           $MAIL_PORT=$credentialMail['MAIL_PORT'];
           $MAIL_PORT_value=$credentialMail['MAIL_PORT_value'];
           $MAIL_USERNAME=$credentialMail['MAIL_USERNAME'];
           $MAIL_USERNAME_value=$credentialMail['MAIL_USERNAME_value'];
           $MAIL_PASSWORD=$credentialMail['MAIL_PASSWORD'];
           $MAIL_PASSWORD_value=$credentialMail['MAIL_PASSWORD_value'];

           $env=base_path('.env');
          // check .env exit or not
           if(file_exists($env))
           {
             // smtp replace .env file
             file_put_contents($env,str_replace($MAIL_HOST."=".env($MAIL_HOST),$MAIL_HOST."=".$MAIL_HOST_value, file_get_contents($env)));

             // port replace .env file
             file_put_contents($env,str_replace($MAIL_PORT."=".env($MAIL_PORT),$MAIL_PORT."=".$MAIL_PORT_value, file_get_contents($env)));

             // USERNAME replace .env file
             file_put_contents($env,str_replace($MAIL_USERNAME."=".env($MAIL_USERNAME),$MAIL_USERNAME."=".$MAIL_USERNAME_value, file_get_contents($env)));
             
             // PASSWORD replace .env file
             file_put_contents($env,str_replace($MAIL_PASSWORD."=".env($MAIL_PASSWORD),$MAIL_PASSWORD."=".$MAIL_PASSWORD_value, file_get_contents($env))); 
               
           }
       }

    }

    public function sandboxing(Request $request){

      $value = 1;
      if($request['value'] == 1){
        $value = 0;
      }
      $auth_site_id=Auth::guard('admin')->user()->site_id;

      SystemConfig::where('site_id',$auth_site_id)->update(['sandboxing'=>$value]);

      return response()->json(['success'=>false,'value'=>$value]);
    }

    //verification of admin
    public function varificationUpdate(Request $request){

      // dd($request->all());
      $value = 1;
      if($request['value'] == 1){
        $value = 0;
      }
      $auth_site_id=Auth::guard('admin')->user()->site_id;

      systemConfig::where('site_id',$auth_site_id)->update(['varification_sandboxing'=>$value]);

      return response()->json(['success'=>false,'value'=>$value]);
    }

    //file upload to aws or local flag set
    public function uploadFileAwsORLocal(Request $request){

    
      $value = '1';
      if((int)$request['value'] == 1){
        $value = '0';
      }
      $auth_site_id=Auth::guard('admin')->user()->site_id;
      
      systemConfig::where('site_id',$auth_site_id)->update(['file_aws_local'=>$value]);

      return response()->json(['success'=>false,'value'=>$value]);
    }
}

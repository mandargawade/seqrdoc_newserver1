<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 27/11/2019
 *   Use   : listing of Profile & Changes Password
 *
**/
namespace App\Http\Controllers\webapp;

use App\Http\Controllers\Controller;
use App\models\User;
use App\models\SessionManager;
use Illuminate\Http\Request;
use Auth;
use Hash;
class ProfileController extends Controller
{   
    /**
     * Display a listing of the Profile.
     *
     * @return view response
     */ 
    public function index()
    {
    	return view('webapp.profile.index');
    }
    /**
     * Changes Password of Webuser.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function changePassword(Request $request)
    {
       if(!empty($request['password'])) {

          $user_id=Auth::guard('webuser')->user()->id;
          $request['password']=Hash::make($request['password']);
          $change_status=User::where('id',$user_id)->update(['password'=>$request['password']]);


          $session_id =  SessionManager::select('session_id')->where('user_id',$user_id)->where('is_logged',1)->get()->toArray();
          // dd($session_id);

          foreach ($session_id as $key => $value) {
           
            $session_val = $value['session_id'];  
            $request->session()->forget('session_id');

            Auth::guard('webuser')->logout();
          }
          SessionManager::where('user_id',$user_id)->update(['is_logged'=>0,'logout_time'=>date('Y-m-d H:i:s')]);

                 

          return $change_status ? response()->json(['success'=>true]) : "false"; 
       }

    }
}

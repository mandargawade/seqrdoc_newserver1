<?php

namespace App\Http\Controllers\verify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\SessionManager;
use App\models\User;
use Hash;
use Auth;


class ProfileController extends Controller
{
    //
    public function index(){

    	return view('verify.profile');
    }

    public function changePassword(Request $request){

    	
    	if(!empty($request['password'])) {

          $user_id=Auth::guard('webuser')->user()->id;
          $request['password']=Hash::make($request['password']);
          $change_status=User::where('id',$user_id)->update(['password'=>$request['password']]);     
          
          return response()->json(['success'=>true,'text'=>'Password Updated Successfully']); 
       }
    }
}

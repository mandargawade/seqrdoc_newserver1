<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 27/11/2019
 *   Use   : listing of Profile & Changes Password
 *
**/
namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\models\Admin;
use Auth;
use Illuminate\Http\Request;
use Hash;
class ProfileController extends Controller
{
     /**
     * Display a listing of the Profile.
     */
    public function index()
    {
    	return view('admin.profile.index');
    }
    /**
     *  Changes Password for specific user 
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function changePassword(Request $request)
    {
       if(!empty($request['password'])) {

          $user_id=Auth::guard('admin')->user()->id;
          $request['password']=Hash::make($request['password']);
          $change_status=Admin::where('id',$user_id)->update(['password'=>$request['password']]);
          return $change_status ? response()->json(['success'=>true]) : "false"; 
       }

    }
}

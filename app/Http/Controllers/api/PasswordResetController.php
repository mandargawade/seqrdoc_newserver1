<?php

namespace App\Http\Controllers\api;

use App\models\raisoni\DegreeMaster;
use App\models\User as Users;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\User;
use Mail;
use Auth;
use DB;
use Illuminate\Support\Facades\Hash;

use App\models\PasswordReset;
use App\Jobs\SendMailJob;
use Validator;
use Str;


class PasswordResetController extends Controller
{
    //

    public function passwordReset(Request $request) {
        $validation_rules = [
            'email_id' => 'required|email|exists:user_table',
        ];

        $validation_messages = [
            'email_id.email' => 'Email id is invalid.',
            'email_id.exists' => 'Email id is not available.',
        ];

        $validation = Validator::make($request->all(), $validation_rules,$validation_messages);
        if ($validation->fails()) {
            return json_encode([
                'errors' => $validation->errors()->getMessages(),
                'code' => 422,
            ]);
        }

        


        $userData = User::select('username','token','is_verified')->where('email_id',$request['email_id'])->first();
        
        if($userData){

            $token = Str::random(64);
            // add record in password reset table
            $PasswordReset = new PasswordReset();
            $PasswordReset->email = $request->email_id;
            $PasswordReset->token = $token;
            $PasswordReset->created_at =date('Y-m-d H:i:s');
            $PasswordReset->save();
            
            //find record in password reset table
            // $tokenData = PasswordReset::where('email', $request->email_id)->first();

            //sending mail
            $mail_view = 'mail.auth_index';
            $user_email = $request['email_id'];
            $mail_subject = 'Verification link for SeQR Mobile App forgot password';
            $user_data = ['name'=>$userData['username'],'token'=>$token];

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
    
}

<?php

namespace App\Http\Controllers\api\verify;

use App\models\raisoni\DegreeMaster;
use App\models\User as Users;
use App\models\SessionManager;
use App\models\Site;
use App\models\raisoni\BranchMaster;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\User;
use App\Mail\LoginMail;
use App\Mail\ResetPassword;
use Mail;
use Auth;
use DB;
use Illuminate\Support\Facades\Hash;
use App\Utility\ApiSecurityLayer;

class ChangePasswordController extends Controller
{
    //
    public function ChangePassword(Request $request)
    {
        $data = $request->post();
        if(ApiSecurityLayer::checkAuthorization())
        {
            switch ($request->type) {
                case 'change_password':
                
                    $user_id = $request->user_id;
                    $current_password = $request->current_password;
                    $new_password = $request->new_password;
                    $confirm_password = $request->confirm_password;
                    if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id))
                    {
                        if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {

                            $password = Users::find($user_id);
                            $credential1=[

                                'id'=>$user_id,
                                'password'=>$current_password,
                                
                            ];
                           

                            if(Auth::guard('webuser')->attempt($credential1)){

                                if ($new_password === $confirm_password) {
                                    if (strlen($new_password) >= 8) {
                                        $result = User::where('id',$user_id)->first();
                                        
                                        if (!empty($result)) {
                                            try {

                                                $new_password=Hash::make($new_password);
                                                $change_status=User::where('id',$user_id)->update(['password'=>$new_password]);

                                                $message = array('status' => 200,'success' => true,'message' => 'Password updated successfully.');

                                            } catch (exception $e) {
                                                $message = array('status' => 500, 'message' => 'Something went wrong.');
                                            }
                                        } else {
                                            $message = array('status' => 422, 'message' => 'Current password do not matched.');
                                        }
                                    } else {
                                        $message = array('status' => 422, 'message' => 'Password should have mininmum 8 characters.');
                                    }
                                } else {
                                    $message = array('status' => 422, 'message' => 'New password & confirm password do not matched.');
                                }
                            }else{
                                $message = array('status' => 422, 'message' => 'Current password do not matched.');
                            }
                        } else {
                            $message = array('status' => 400, 'message' => 'Required fields not found.');
                        }
                    } else {
                        $message = array('status' => 403, 'message' => 'User id is missing or You dont have access to this api.');

                    }
                break;
                default:
                    $message = array('status' => 404, 'message' => 'Request not found.');
                break;
            }

        } else {
            $message = array('status' => 403, 'message' => 'Access forbidden.');
        }
        echo json_encode($message);

        $requestUrl = \Request::Url();
        $requestMethod = \Request::method();
        $requestParameter = $data;

        if ($message['status']==200) {
            
            $status = 'success';
        }
        else
        {
            $status = 'failed';
        }

        ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);

        
    }
}

<?php

namespace App\Http\Controllers\api\verify;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Utility\ApiSecurityLayer;
use App\models\User;
use App\models\raisoni\BranchMaster;
use App\models\raisoni\DegreeMaster;

class UserProfileController extends Controller
{
    //

    public function user_profile(Request $request)
    {
        $data = $request->post();

        switch ($request->type) {
        case 'get_user_profile':

            if(ApiSecurityLayer::checkAuthorization())
            {
                $user_id=$request->user_id;
                

                if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id))
                {
                   $sql = User::select('user_table.id','user_table.fullname','user_table.l_name','user_table.username','user_table.email_id','user_table.mobile_no','user_table.user_type','user_table.registration_no','user_table.working_sector','user_table.address','user_table.institute', 'degree_master.degree_name','branch_master.branch_name_long','user_table.passout_year')
                    ->leftJoin('degree_master', 'degree_master.id', '=', 'user_table.degree')
                    ->leftJoin('branch_master','branch_master.id', '=', 'user_table.branch')
                    ->where('user_table.id',$user_id)
                    ->get(); 
                    
                    $message = array('status' => 200, 'message' => 'success', "data" => $sql);
                } else {
                    $message = array('status' => 500, 'message' => 'User not found');
                }
            } else {
                $message = array('status' => 403, 'message' => 'Access forbidden.');
                }
            break;

            default:
                $message = array('status' => 404, 'message' => 'Request not found.');
            break;
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

<?php

namespace App\Http\Controllers\api\verify;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\models\raisoni\BranchMaster;
use App\models\raisoni\DegreeMaster;
use App\Utility\ApiSecurityLayer;

class DropdownController extends Controller
{
    //
    public function dropdown(Request $request)
    {   
        $data = $request->post();
        if($request->type)
        {
            switch ($request->type) {

                case 'institute':
                    try {
                        $result[] = array('value' => 'G H Raisoni College of Engineering, Nagpur');
                        $message = array('status' => 200,'success' => true, 'message' => 'success', 'data' => $result);
                    } catch (exception $e) {
                        $message = array('status' => 500, 'message' => $e->getMessage());
                    }
                    break;
                case 'degree':
                    try {
                        $result = DegreeMaster::select('id','degree_name as value')->where('is_active', 1)->get();

                        $message = array('status' => 200,'success' => true,'message' => 'success', 'data' => $result);

                    } catch (exception $e) {

                        $message = array('status' => 500, 'message' => $e->getMessage());

                    }
                    break;
                case 'branch':
                    try {
                        $degree_id = $request->degree_id;
                        $result = BranchMaster::select('id','branch_name_long as value')->where('is_active', 1)->where('degree_id',$degree_id)->get();
                        $message = array('status' => 200,'success' => true, 'message' => 'success', 'data' => $result);

                    } catch (exception $e) {
                        $message = array('status' => 500, 'message' => $e->getMessage());
                    }
                    break;
                default:
                    $message = array('status' => 405, 'message' => 'Request method not accepted.');

                    break;
            }
        } else {
            $message = array('status' => 400, 'message' => 'Required fields missing.');
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

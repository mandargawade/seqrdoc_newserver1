<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\WebUserRegisterRequest;
use App\models\Site;
use Hash;
use App\Jobs\SendMailJob;
use App\models\User;
use App\models\SessionManager;
use Illuminate\Support\Facades\Auth;
use App\Utility\ApiSecurityLayer;
use App\models\ApiTracker;
use App\models\FreedomFighterList;
use App\models\Admin;
class FFController extends Controller
{
    public function FFinfo(Request $request)
    {
        $data = $request->post();
        $ff_id=$data['ff_id'];
        $requestUrl = \Request::Url();
        $requestMethod = \Request::method();
        $requestParameter = $data;
        
        if(ApiSecurityLayer::checkAuthorization())
        {
            $rules =[
                'ff_id'   => 'required|numeric|min:11',
            ];
            $messages = [
                'ff_id.required'=>'FF ID is required.',
                'ff_id.numeric'=>'FF ID should be numeric.',
                'ff_id.min'=>'11 Digit FF ID is required.',
            ];
            $validator = \Validator::make($request->post(),$rules,$messages);
            if ($validator->fails()) {
                $message = array('success' => false,'status'=>400, 'message' => ApiSecurityLayer::getMessage($validator->errors()));
                //return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);

                if ($message['success']==true) {
                    $status = 'success';
                    return $message;
                }
                else
                {
                    $status = 'failed';
                    return $message;
                }
                //$api_tracker_id = ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);
                //$response_time = microtime(true) - LARAVEL_START;
                //ApiTracker::where('id',$api_tracker_id)->update(['response_time'=>$response_time]);
                
            }else{
                if (FreedomFighterList::where('ff_id',$ff_id)->where('active','Yes')->where('publish','1')->count() == 0) {
                    $message = array('success' => false, 'status'=>401, 'message' => 'No data found.', 'ff_id'=>$ff_id);
                    return $message;
                    exit;
                }
                $data_active = FreedomFighterList::where('ff_id',$ff_id)->where('active','Yes')->where('publish','1')->first();
                $result=array();
                //$result['fid']=$data_active->id;
                $result['ff_id']=$data_active->ff_id;
                $result['ff_name']=$data_active->ff_name;
                $result['fh_name']=$data_active->father_or_husband_name;
                $result['mother_name']=$data_active->mother_name;
                $result['is_alive']=$data_active->is_alive;
                $result['post_office']=$data_active->post_office;
                $result['post_code']=$data_active->post_code;
                $result['district']=$data_active->district;
                $result['ut_name']=$data_active->upazila_or_thana;
                $result['vw_name']=$data_active->village_or_ward;
                $result['nid']=$data_active->nid;
                $result['ghost_image_code']=$data_active->ghost_image_code;
                $result['import_status']=$data_active->import_flag;
                $result['import_date']=date_format($data_active->created_at,"Y-m-d H:i:s");
                //$result['import_by']=$this->getUser($data_active->admin_id);
                $result['idcard_generate_date']=$data_active->generated_at;
                //$result['idcard_generate_by']=$this->getUser($data_active->g_admin_id);
                $result['idcard_complete_date']=$data_active->completed_at;
                //$result['idcard_complete_by']=$this->getUser($data_active->icc_admin_id);
                $result['certificate_generate_date']=$data_active->c_generated_at;
                //$result['certificate_generate_by']=$this->getUser($data_active->c_admin_id);
                $result['certificate_complete_date']=$data_active->c_completed_at;
                //$result['certificate_complete_by']=$this->getUser($data_active->cc_admin_id);
                $result['ff_photo']=$data_active->ff_photo;
                $message = array('success'=>true, 'status'=>200, 'message'=>'success', 'records' => $result);
                return $message;
            }
        }
        else
        {
            $message = array('success'=>false, 'status'=>403, 'message' => 'Access forbidden.');
            return $message;
        } 
    }
    
    public function FFhistory(Request $request){        
        $data = $request->post();
        $ff_id=$data['ff_id'];
        $requestUrl = \Request::Url();
        $requestMethod = \Request::method();
        $requestParameter = $data;        
        //$ff_id=$request['ff_id'];
        if(ApiSecurityLayer::checkAuthorization())
        {
            $rules =[
                'ff_id'   => 'required|numeric|min:11',
            ];
            $messages = [
                'ff_id.required'=>'FF ID is required.',
                'ff_id.numeric'=>'FF ID should be numeric.',
                'ff_id.min'=>'11 Digit FF ID is required.',
            ];
            $validator = \Validator::make($request->post(),$rules,$messages);
            if ($validator->fails()) {
                $message = array('success' => false,'status'=>400, 'message' => ApiSecurityLayer::getMessage($validator->errors()));                
                if ($message['success']==true) {
                    $status = 'success';
                    return $message;
                }
                else
                {
                    $status = 'failed';
                    return $message;
                }                
            }else{        
                $datas = FreedomFighterList::where('ff_id',$ff_id) -> orderBy('id', 'asc') -> get(); 
                $result=array();
                $cnt=1;
                foreach ($datas as $data) 
                {            
                    $import_flag=$data->import_flag;
                    if($import_flag=='RE-IMPORTED'){ $initial="RE-"; }else{ $initial=""; }
                    $created_at=date('d-m-Y h:i A', strtotime($data->created_at));            
                    $created_by=Admin::where('id',$data->admin_id)->first()->fullname;            
                    /*$result['Log'.$cnt]=$import_flag; 
                    $result['created_at'.$cnt]=$created_at;
                    $result['created_by'.$cnt]=$created_by;*/            
                    //$result['Log'.$cnt]=array($import_flag,$created_at,$created_by);
                    if($data->generated_at != ''){
                        $generated_at=date('d-m-Y h:i A', strtotime($data->generated_at));
                        $generated_by=Admin::where('id',$data->g_admin_id)->first()->fullname;
                    }else{
                        $generated_at="Pending";
                        $generated_by='';
                    }    
                    /*$result['Log'.$cnt.'_ID_Status']=$initial."GENERATED";      
                    $result['generated_at'.$cnt]=$generated_at;
                    $result['generated_by'.$cnt]=$generated_by;*/
                    //$result['Log'.$cnt.'_IDCard']=array($initial."GENERATED",$generated_at,$generated_by);            
                    if($data->completed_at != ''){
                        $completed_at=date('d-m-Y h:i A', strtotime($data->completed_at));
                        $completed_by=Admin::where('id',$data->icc_admin_id)->first()->fullname;
                    }else{
                        $completed_at="Pending";
                        $completed_by='';
                    }
                    /*$result['status_id_c'.$cnt]=$initial."COMPLETED";      
                    $result['completed_at'.$cnt]=$completed_at;
                    $result['completed_by'.$cnt]=$completed_by;
                    */
                    if($data->c_generated_at != ''){
                        $c_generated_at=date('d-m-Y h:i A', strtotime($data->c_generated_at));
                        $c_generated_by=Admin::where('id',$data->c_admin_id)->first()->fullname;
                    }else{
                        $c_generated_at="Pending";
                        $c_generated_by='';
                    }
                    /*$result['status_cet_g'.$cnt]=$initial."GENERATED";      
                    $result['c_generated_at'.$cnt]=$c_generated_at;
                    $result['c_generated_by'.$cnt]=$c_generated_by;*/
                    if($data->c_completed_at != ''){
                        $c_completed_at=date('d-m-Y h:i A', strtotime($data->c_completed_at));
                        $c_completed_by=Admin::where('id',$data->cc_admin_id)->first()->fullname;
                    }else{
                        $c_completed_at="Pending";
                        $c_completed_by='';
                    }
                    /*$result['status_cet_c'.$cnt]=$initial."COMPLETED";      
                    $result['c_completed_at'.$cnt]=$c_completed_at;
                    $result['c_completed_by'.$cnt]=$c_completed_by;*/
                    $result[]=array(
                    $import_flag,$created_at,
                    "ID CARD ".$initial."GENERATED",$generated_at,
                    "ID CARD ".$initial."COMPLETED", $completed_at, 
                    "CERTIFICATE ".$initial."GENERATED", $c_generated_at, 
                    "CERTIFICATE ".$initial."COMPLETED", $c_completed_at            
                    );
                    $cnt++; 
                }
                
                if (empty($result)) {
                    $message = array('success' => false,'status'=>401, 'message' => 'No data found.', 'ff_id'=>$ff_id);
                    return $message;
                }else{
                    $message = array('success'=>true, 'status'=>200, 'message'=>'success', 'ff_id'=>$ff_id, 'ff_name'=>$data->ff_name, 'records' => $result);
                    return $message;
                }
            }
        }
        else
        {
            $message = array('success'=>false,'status'=>403, 'message' => 'Access forbidden.');
            return $message;
        }             
    }    
    
    public function getUser($id)
    {
        $name=Admin::where('id',$id)->first()->fullname;
        return $name;
    }

}

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
use App\models\ReImportLogDetail;
use App\models\FFReasonLog;
use App\models\FFMolwaRemarkLog;
use App\models\Admin;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
                    $reason_result=array();
                    $reason_data=FFReasonLog::where('record_id',$data->id) -> orderBy('id', 'asc') -> get();
					$rc=1;
					foreach ($reason_data as $rdata){
						$reason_result[] = array(							
							'Status: '. $rdata->status,
							'Reason: '. $rdata->reason,
							'Date: '. date('d-m-Y h:i A', strtotime($rdata->created_at)),							
						);
						$rc++;
					}
					 
					$import_flag=$data->import_flag;
                    if($import_flag=='RE-IMPORTED'){ 
						$initial="RE-"; 
						$reImportReason = ReImportLogDetail::where('record_unique_id',$data->record_unique_id)->get(['reason']); 
						$reason = $reImportReason[0]->reason;
					}else{ $initial=""; $reason =""; }
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
                        if($data->is_alive == 'হ্যাঁ'){
							$generated_at="Pending";
						}else{
							$generated_at="FF_IS_DEAD";
						}
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
                        if($data->is_alive == 'হ্যাঁ'){
							$completed_at="Pending";
						}else{
							$completed_at="FF_IS_DEAD";
						}
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
                    
					if(empty($reason)){
						$result[]=array(
							$import_flag,
							$created_at,
							"ID CARD ".$initial."GENERATED",$generated_at,
							"ID CARD ".$initial."COMPLETED", $completed_at, 
							"CERTIFICATE ".$initial."GENERATED", $c_generated_at, 
							"CERTIFICATE ".$initial."COMPLETED", $c_completed_at            
						);
					}else{
						$result[]=array(
							$import_flag,							
							$created_at,
							"REASON", $reason,
							"ID CARD ".$initial."GENERATED",$generated_at,
							"ID CARD ".$initial."COMPLETED", $completed_at, 
							"CERTIFICATE ".$initial."GENERATED", $c_generated_at, 
							"CERTIFICATE ".$initial."COMPLETED", $c_completed_at,								
						);
					}					
					
					
                    $cnt++; 
                }
                
                if (empty($result)) {
                    $message = array('success' => false,'status'=>401, 'message' => 'No data found.', 'ff_id'=>$ff_id);
                    return $message;
                }else{
                    $message = array('success'=>true, 'status'=>200, 'message'=>'success', 'ff_id'=>$ff_id, 'ff_name'=>$data->ff_name, 'records' => $result, 'Activation Status' => $reason_result);
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
	
	public function FFhistoryBulk(Request $request){ 
        $rules =[
            'district'   => 'required',
        ];
        $messages = [
            'district.required'=>'District is required.',
        ];
        $per_page = $request->per_page;
		if (!isset ($per_page) ) {   
            $per_page = 20;
        } else {  
            $per_page = $per_page;  
        }
        $page_number = $request ->page_number;
         // update the active page number
        if (!isset ($page_number) ) {   
            $page_number = 1;
        } else {  
            $page_number = $page_number;  
        }
        $limit = $per_page;
        $district = $request ->district;
        $upazilla = $request->upazilla;  
        $total_rows =  FreedomFighterList::where('district', 'LIKE', "%{$district}%")
                        ->orWhere('upazila_or_thana', 'LIKE', "%{$upazilla}%")
                        ->orderBy('id','asc')
                        ->count();
        $total_pages = ceil ($total_rows / $limit);     
        $initial_page = ($page_number-1) * $limit; // get the initial page number
        $validator = \Validator::make($request->post(),$rules,$messages);
        if(ApiSecurityLayer::checkAuthorization())
        {
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
                $datas = \DB::select("SELECT * FROM freedom_fighter_list  WHERE district LIKE '%{$district}%' OR upazila_or_thana  LIKE '%{$upazilla}%' ORDER BY id ASC LIMIT " . $initial_page . ',' . $limit );       
                $result=array();
                $cnt=1;
                foreach ($datas as $data) 
                {
                    $reason_result=array();
                    $reason_data=FFReasonLog::where('record_id',$data->id) -> orderBy('id', 'asc') -> get();
					$rc=1;
					foreach ($reason_data as $rdata){
						$reason_result[] = array(							
							'Status: '. $rdata->status,
							'Reason: '. $rdata->reason,
							'Date: '. date('d-m-Y h:i A', strtotime($rdata->created_at)),							
						);
						$rc++;
					}                    
					$ff_id=$data->ff_id;
                    $import_flag=$data->import_flag;
                    if($import_flag=='RE-IMPORTED'){ 
                        $initial="RE-"; 
                        $reImportReason = ReImportLogDetail::where('record_unique_id',$data->record_unique_id)->get(['reason']);
                        $reason = $reImportReason[0]->reason;
                    }else{ 
                        $initial="";
                        $reason ="";
                    }
                    $created_at=date('d-m-Y h:i A', strtotime($data->created_at));       
                    $created_by=Admin::where('id',$data->admin_id)->first()->fullname;            
                    if($data->generated_at != ''){
                        $generated_at=date('d-m-Y h:i A', strtotime($data->generated_at));
                        $generated_by=Admin::where('id',$data->g_admin_id)->first()->fullname;
                    }else{
                        $generated_at="Pending";
                        $generated_by='';
                    }             
                    if($data->completed_at != ''){
                        $completed_at=date('d-m-Y h:i A', strtotime($data->completed_at));
                        $completed_by=Admin::where('id',$data->icc_admin_id)->first()->fullname;
                    }else{
                        $completed_at="Pending";
                        $completed_by='';
                    }
                    if($data->c_generated_at != ''){
                        $c_generated_at=date('d-m-Y h:i A', strtotime($data->c_generated_at));
                        $c_generated_by=Admin::where('id',$data->c_admin_id)->first()->fullname;
                    }else{
                        $c_generated_at="Pending";
                        $c_generated_by='';
                    }
                    if($data->c_completed_at != ''){
                        $c_completed_at=date('d-m-Y h:i A', strtotime($data->c_completed_at));
                        $c_completed_by=Admin::where('id',$data->cc_admin_id)->first()->fullname;
                    }else{
                        $c_completed_at="Pending";
                        $c_completed_by='';
                    }
                    $temp = array();
                    if(empty($reason)){
                        $temp = array(
                                $import_flag,
                                $created_at,
                                "ID CARD ".$initial."GENERATED",$generated_at,
                                "ID CARD ".$initial."COMPLETED", $completed_at, 
                                "CERTIFICATE ".$initial."GENERATED", $c_generated_at, 
                                "CERTIFICATE ".$initial."COMPLETED", $c_completed_at            
                            );
                    }else{
                        $temp = array(
                            $import_flag,
                            $created_at,
							"REASON", $reason,
                            "ID CARD ".$initial."GENERATED",$generated_at,
                            "ID CARD ".$initial."COMPLETED", $completed_at, 
                            "CERTIFICATE ".$initial."GENERATED", $c_generated_at, 
                            "CERTIFICATE ".$initial."COMPLETED", $c_completed_at            
                        );
                    }
                    
                    $result[] = array(
                        'ff_id' => $data->ff_id,
                        'ff_name' => $data->ff_name,
                        //'father_or_husband_name' => $data->father_or_husband_name,
                        //'mother_name' => $data->mother_name,
                        //'is_alive' => $data->is_alive,
                        //'post_office' => $data->post_office,
                        //'post_code' => $data->post_code,
                        //'district' => $data->district,
                        //'upazila_or_thana' => $data->upazila_or_thana,
                        //'village_or_ward' => $data->village_or_ward,
                        //'nid' => $data->nid,
                        //'ghost_image_code' => $data->ghost_image_code,
                        //'ff_photo' => $data->ff_photo,
                        'status' => $temp,
						'Activation Status' => $reason_result,
                    );
                    $cnt++; 
                }
                
                if (empty($result)) {
                    $message = array('success' => false,'status'=>401, 'message' => 'No data found.');
                    return $message;
                }else{
                $message = array('success'=>true, 'status'=>200, 'message'=>'success', 'per_page'=>$per_page,'page_number'=> $page_number,'totalRecords'=>$total_rows ,'records' => $result);
                    return $message;
                }
            }
        }else{
            $message = array('success'=>false,'status'=>403, 'message' => 'Access forbidden.');
            return $message;
        }  
    }  	

	public function FFEnableDisable(Request $request){
        $validator = Validator::make($request->all(), [
            'ff_id' => 'required',
            'activation_status' => ['required', Rule::in(['enable','disable']),],
        ],[
            'ff_id.required' => 'The ff_id field is required',
            'activation_status.required' => 'The activation_status field is required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->all()
            ]);
        }
        $ff_id = $request->ff_id;
        $status = $request->activation_status;
        $new_status = ($status == 'enable') ? 'Yes' : 'No' ;
		$student_status = ($status == 'enable') ? 1 : 0 ;
        if(ApiSecurityLayer::checkAuthorization()){
            $resp = FreedomFighterList::where('ff_id',$ff_id)->where('publish','1')->wherenotnull('c_generated_at')->wherenotnull('c_completed_at')->update(['active' => $new_status]);
            if($resp != 0){				
				\DB::select(\DB::raw('UPDATE `student_table` SET status='.$student_status.' WHERE serial_no="ID_'.$ff_id.'" ORDER BY id DESC LIMIT 1'));
                \DB::select(\DB::raw('UPDATE `student_table` SET status='.$student_status.' WHERE serial_no="C_'.$ff_id.'" ORDER BY id DESC LIMIT 1'));                
				$message = array('success'=>true,'status'=>200, 'message' => 'success');
                return $message;
            }else{
                $message = array('success' => false,'status'=>401, 'message' => 'No data found.');
                return $message;
            }
        }else{
            $message = array('success'=>false,'status'=>403, 'message' => 'Access forbidden.');
            return $message;
        }
    }
	
	public function FFUpdate(Request $request){        
        if(ApiSecurityLayer::checkAuthorization()){
            $ff_id = $request->ff_id;
            if (FreedomFighterList::where('ff_id',$ff_id)->where('active','Yes')->where('publish','1')->count() == 0) {
                $message = array('success' => false, 'status'=>401, 'message' => 'FF ID not found.', 'ff_id'=>$ff_id);
                return $message;
                exit;
            }
            
            $validator = Validator::make($request->post(), [
                'ff_id' => 'required',
                'ff_name' => 'required',
                'father_or_husband_name' => 'required',
                'mother_name' => 'required',
                'is_alive' => 'required',
                'post_office' => 'required',
                'post_code' => 'required',
                'upazila_or_thana' => 'required',
                'district' => 'required',
                'village_or_ward' => 'required',
                'nid' => 'required',
                'ghost_image_code' => 'required',
                'ff_photo' => 'required',
                'Remarks' => 'required',
            ],[
                'ff_id.required' => 'The ff_id field is required',
                'ff_name.required' => 'The ff_name field is required',
                'father_or_husband_name.required' => 'The father_or_husband_name field is required',
                'mother_name.required' => 'The mother_name field is required',
                'is_alive.required' => 'The is_alive field is required',
                'post_office.required' => 'The post_office field is required',
                'post_code.required' => 'The post_code field is required',
                'upazila_or_thana.required' => 'The upazila_or_thana field is required',
                'district.required' => 'The district field is required',
                'village_or_ward.required' => 'The village_or_ward field is required',
                'nid.required' => 'The nid field is required',
                'ghost_image_code.required' => 'The ghost_image_code field is required',
                'ff_photo.required' => 'The ff_photo field is required',
                'Remarks.required' => 'The Remarks field is required',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()->all()
                ]);
            }
            $input_log=array();            
            $input_log['ff_id'] = $request->ff_id;
            $input_log['ff_name']=$request->ff_name; 
            $input_log['father_or_husband_name']=$request->father_or_husband_name;
            $input_log['mother_name']=$request->mother_name;
            $input_log['post_office']=$request->post_office;
            $input_log['post_code']=$request->post_code;
            $input_log['district']=$request->district;
            $input_log['upazila_or_thana']=$request->upazila_or_thana;
            $input_log['village_or_ward']=$request->village_or_ward;
            $input_log['nid']=$request->nid;
            $input_log['ghost_image_code']=$request->ghost_image_code;
            $input_log['ff_photo']=$request->ff_photo;
            $input_log['molwa_remark']=$request->Remarks;
            //print_r($input_log);
            $resp = FreedomFighterList::where('ff_id',$ff_id)->where('active','Yes')->where('publish','1')->update($input_log);
            if($resp != 0){	
                $datas = FreedomFighterList::where('ff_id',$ff_id) -> where('active','Yes') -> where('publish','1') -> orderBy('id', 'asc') -> first();
                $ff_record_id=$datas->id;
                $input_remarklog=array();
                $input_remarklog['record_id'] = $ff_record_id;
                $input_remarklog['ff_id'] = $ff_id;
                $input_remarklog['remark'] = $request->Remarks;
                $input_remarklog['created_at'] = date("Y-m-d H:i:s");
                FFMolwaRemarkLog::create($input_remarklog);
				$message = array('success'=>true,'status'=>200, 'message' => 'success');
                return $message;
            }else{
                $message = array('success' => false,'status'=>401, 'message' => 'No data found.');
                return $message;
            }
        }else{
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

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
            'district'   => 'required|numeric',
        ];
        $messages = [
            'district.required'=>'District is required.',
            'district.numeric'=>'District should be numeric.',
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
        $district_id = $request ->district;
        $upazilla_id = $request->upazilla;  

        $district_name=array(
        array('1','BARGUNA','বরগুনা'), array('2','BARISAL','বরিশাল'), array('3','BHOLA','ভোলা'), array('4','JHALOKATI','ঝালকাঠী'), array('5','PATUAKHALI','পটুয়াখালী'), array('6','PIROJPUR','পিরোজপুর'), array('7','BANDARBAN','বান্দরবান'), array('8','BRAHMANBARIA','ব্রাহ্মণবাড়িয়া'), array('9','CHANDPUR','চাঁদপুর'), array('10','CHITTAGONG','চট্রগ্রাম'), array('11','COMILLA','কুমিল্লা'), array('12','COX\'S BAZAR','কক্সবাজার'), array('13','FENI','ফেনী'), array('14','KHAGRACHHARI','খাগড়াছড়ি'), array('15','LAKSHMIPUR','লক্ষ্মীপুর'), array('16','NOAKHALI','নোয়াখালী'), array('17','RANGAMATI','রাঙ্গামাটি'), array('18','DHAKA','ঢাকা'), array('19','FARIDPUR','ফরিদপুর'), array('20','GAZIPUR','গাজীপুর'), array('21','GOPALGANJ','গোপালগঞ্জ'), array('22','JAMALPUR','জামালপুর'), array('23','KISHOREGONJ','কিশোরগঞ্জ'), array('24','MADARIPUR','মাদারীপুর'), array('25','MANIKGANJ','মানিকগঞ্জ'), array('26','MUNSHIGANJ','মুন্সীগঞ্জ'), array('27','MYMENSINGH','ময়মনসিংহ'), array('28','NARAYANGANJ','নারায়নগঞ্জ'), array('29','NARSINGDI','নরসিংদী'), array('30','NETRAKONA','নেত্রকোণা'), array('31','RAJBARI','রাজবাড়ী'), array('32','SHARIATPUR','শরিয়তপুর'), array('33','SHERPUR','শেরপুর'), array('34','TANGAIL','টাঙ্গাইল'), array('35','BAGERHAT','বাগেরহাট'), array('36','CHUADANGA','চুয়াডাঙ্গা'), array('37','JESSORE','যশোর'), array('38','JHENAIDAH','ঝিনাইদহ'), array('39','KHULNA','খুলনা'), array('40','KUSHTIA','কুষ্টিয়া'), array('41','MAGURA','মাগুরা'), array('42','MEHERPUR','মেহেরপুর'), array('43','NARAIL','নড়াইল'), array('44','SATKHIRA','সাতক্ষীরা'), array('45','BOGRA','বগুড়া'), array('46','JOYPURHAT','জয়পুরহাট'), array('47','NAOGAON','নওগাঁ'), array('48','NATORE','নাটোর'), array('49','CHAPAI NABABGANJ','চাঁপাই নবাবগঞ্জ'), array('50','PABNA','পাবনা'), array('51','RAJSHAHI','রাজশাহী'), array('52','SIRAJGANJ','সিরাজগঞ্জ'), array('53','DINAJPUR','দিনাজপুর'), array('54','GAIBANDHA','গাইবান্ধা'), array('55','KURIGRAM','কুড়িগ্রাম'), array('56','LALMONIRHAT','লালমনিরহাট'), array('57','NILPHAMARI','নীলফামারী'), array('58','PANCHAGARH','পঞ্চগড়'), array('59','RANGPUR','রংপুর'), array('60','THAKURGAON','ঠাকুরগাঁও'), array('61','HABIGANJ','হবিগঞ্জ'), array('62','MAULVIBAZAR','মৌলভীবাজার'), array('63','SUNAMGANJ','সুনামগঞ্জ'), array('64','SYLHET','সিলেট')
        );    
        
        $upazila_name=array(
        array('1','আলি কদম','বান্দরবান','7'), array('2','বান্দরবান ‍সদর','বান্দরবান','7'), array('3','লামা','বান্দরবান','7'), array('4','নাইক্ষ্যংছড়ি','বান্দরবান','7'), array('5','রোয়াংছড়ি','বান্দরবান','7'), array('6','রুমা','বান্দরবান','7'), array('7','থানছি','বান্দরবান','7'), array('8','আখাউড়া','ব্রাহ্মণবাড়িয়া','8'), array('9','বাঞ্ছারামপুর','ব্রাহ্মণবাড়িয়া','8'), array('10','বিজয়নগর','ব্রাহ্মণবাড়িয়া','8'), array('11','ব্রাহ্মণবাড়ীয়া সদর','ব্রাহ্মণবাড়িয়া','8'), array('12','আশুগঞ্জ','ব্রাহ্মণবাড়িয়া','8'), array('13','কসবা','ব্রাহ্মণবাড়িয়া','8'), array('14','নবীনগর','ব্রাহ্মণবাড়িয়া','8'), array('15','নাসিরনগর','ব্রাহ্মণবাড়িয়া','8'), array('16','সরাইল','ব্রাহ্মণবাড়িয়া','8'), array('17','চাঁদপুর সদর','চাঁদপুর','9'), array('18','ফরিদগঞ্জ','চাঁদপুর','9'), array('19','হাইমচর','চাঁদপুর','9'), array('20','হাজীগঞ্জ','চাঁদপুর','9'), array('21','কচুয়া','চাঁদপুর','9'), array('22','মতলব (দঃ)','চাঁদপুর','9'), array('23','মতলব (উঃ)','চাঁদপুর','9'), array('24','শাহরাস্তি','চাঁদপুর','9'), array('25','আনোয়ারা','চট্রগ্রাম','10'), array('26','বায়েজিদ বোস্তামী','চট্রগ্রাম','10'), array('27','বাঁশখালী','চট্রগ্রাম','10'), array('28','বাকালিয়া','চট্রগ্রাম','10'), array('29','বোয়ালখালী','চট্রগ্রাম','10'), array('30','চন্দনাইশ','চট্রগ্রাম','10'), array('31','চাঁদগাও','চট্রগ্রাম','10'), array('32','চট্টগ্রাম বন্দর','চট্রগ্রাম','10'), array('33','ডবলমুরিং','চট্রগ্রাম','10'), array('34','ফটিকছড়ি','চট্রগ্রাম','10'), array('35','হালিশহর','চট্রগ্রাম','10'), array('36','হাটহাজারী','চট্রগ্রাম','10'), array('37','কোতোয়ালী','চট্রগ্রাম','10'), array('38','খুলশী','চট্রগ্রাম','10'), array('39','লোহাগাড়া','চট্রগ্রাম','10'), array('40','মিরসরাই','চট্রগ্রাম','10'), array('41','পাহাড়তলী','চট্রগ্রাম','10'), array('42','পাঁচলাইশ','চট্রগ্রাম','10'), array('43','পটিয়া','চট্রগ্রাম','10'), array('44','পতেঙ্গা','চট্রগ্রাম','10'), array('45','রাঙ্গুনিয়া','চট্রগ্রাম','10'), array('46','রাউজান','চট্রগ্রাম','10'), array('47','সন্দ্বীপ','চট্রগ্রাম','10'), array('48','সাতকানিয়া','চট্রগ্রাম','10'), array('49','সীতাকুন্ড','চট্রগ্রাম','10'), array('50','বরুড়া','কুমিল্লা','11'), array('51','ব্রাহ্মণপাড়া','কুমিল্লা','11'), array('52','বুড়িচং','কুমিল্লা','11'), array('53','চান্দিনা','কুমিল্লা','11'), array('54','চৌদ্দগ্রাম','কুমিল্লা','11'), array('55','কুমিল্লা সদর দক্ষিণ','কুমিল্লা','11'), array('56','দাউদকান্দি','কুমিল্লা','11'), array('57','দেবিদ্বার','কুমিল্লা','11'), array('58','হোমনা','কুমিল্লা','11'), array('59','কুমিল্লা আদর্শ সদর','কুমিল্লা','11'), array('60','লাকসাম','কুমিল্লা','11'), array('61','মনোহরগঞ্জ','কুমিল্লা','11'), array('62','মেঘনা','কুমিল্লা','11'), array('63','মুরাদনগর','কুমিল্লা','11'), array('64','নাঙ্গলকোট','কুমিল্লা','11'), array('65','তিতাস','কুমিল্লা','11'), array('66','চকরিয়া','কক্সবাজার','12'), array('67','কক্সবাজার সদর','কক্সবাজার','12'), array('68','কুতুবদিয়া','কক্সবাজার','12'), array('69','মহেশখালী','কক্সবাজার','12'), array('70','পেকুয়া','কক্সবাজার','12'), array('71','রামু','কক্সবাজার','12'), array('72','টেকনাফ','কক্সবাজার','12'), array('73','উখিয়া','কক্সবাজার','12'), array('74','ছাগলনাইয়া','ফেনী','13'), array('75','দাগনভূঞা','ফেনী','13'), array('76','ফেনী সদর','ফেনী','13'), array('77','ফুলগাজী','ফেনী','13'), array('78','পরশুরাম','ফেনী','13'), array('79','সোনাগাজী','ফেনী','13'), array('80','দীঘিনালা','খাগড়াছড়ি','14'), array('81','খাগড়াছড়ি সদর','খাগড়াছড়ি','14'), array('82','লক্ষ্মীছড়ি','খাগড়াছড়ি','14'), array('83','মহালছড়ি','খাগড়াছড়ি','14'), array('84','মানিকছড়ি','খাগড়াছড়ি','14'), array('85','মাটিরাঙ্গা','খাগড়াছড়ি','14'), array('86','পানছড়ি','খাগড়াছড়ি','14'), array('87','রামগড়','খাগড়াছড়ি','14'), array('88','কমল নগর','লক্ষ্মীপুর','15'), array('89','লক্ষ্মীপুর সদর','লক্ষ্মীপুর','15'), array('90','রায়পুর','লক্ষ্মীপুর','15'), array('91','রামগঞ্জ','লক্ষ্মীপুর','15'), array('92','রামগতি','লক্ষ্মীপুর','15'), array('93','বেগমগঞ্জ','নোয়াখালী','16'), array('94','চাটখিল','নোয়াখালী','16'), array('95','কোম্পানীগঞ্জ','নোয়াখালী','16'), array('96','হাতিয়া','নোয়াখালী','16'), array('97','কবিরহাট','নোয়াখালী','16'), array('98','সেনবাগ','নোয়াখালী','16'), array('99','সোনাইমুড়ি','নোয়াখালী','16'), array('100','সুবর্ণচর','নোয়াখালী','16'), array('101','নোয়াখালী সদর','নোয়াখালী','16'), array('102','বাঘাইছড়ি','রাঙ্গামাটি','17'), array('103','বরকল উপজেলা','রাঙ্গামাটি','17'), array('104','কাউখালী (বেতবুনিয়া)','রাঙ্গামাটি','17'), array('105','বিলাইছড়ি উপজেলা','রাঙ্গামাটি','17'), array('106','কাপ্তাই উপজেলা','রাঙ্গামাটি','17'), array('107','জুরাছড়ি উপজেলা','রাঙ্গামাটি','17'), array('108','লংগদু উপজেলা','রাঙ্গামাটি','17'), array('109','নানিয়ারচর উপজেলা','রাঙ্গামাটি','17'), array('110','রাজস্থলী উপজেলা','রাঙ্গামাটি','17'), array('111','রাঙ্গামাটি সদর ইউপি','রাঙ্গামাটি','17'), array('128','আদাবর','ঢাকা','18'), array('129','বাড্ডা','ঢাকা','18'), array('130','বংশাল','ঢাকা','18'), array('131','বিমান বন্দর','ঢাকা','18'), array('132','বনানী','ঢাকা','18'), array('133','ক্যান্টনমেন্ট','ঢাকা','18'), array('134','চকবাজার','ঢাকা','18'), array('135','দক্ষিণ খান','ঢাকা','18'), array('136','দারুস সালাম','ঢাকা','18'), array('137','ডেমরা','ঢাকা','18'), array('138','ধামরাই','ঢাকা','18'), array('139','দোহার','ঢাকা','18'), array('140','ভাষানটেক','ঢাকা','18'), array('141','ভাটারা','ঢাকা','18'), array('142','গেন্ডারিয়া','ঢাকা','18'), array('143','গুলশান','ঢাকা','18'), array('144','যাত্রাবাড়ি','ঢাকা','18'), array('145','কাফরুল','ঢাকা','18'), array('146','কদমতলী','ঢাকা','18'), array('147','কলাবাগান','ঢাকা','18'), array('148','কামরাঙ্গীরচর','ঢাকা','18'), array('149','খিলগাঁও','ঢাকা','18'), array('150','খিলক্ষেত','ঢাকা','18'), array('151','কেরানীগঞ্জ','ঢাকা','18'), array('152','কোতোয়ালী','ঢাকা','18'), array('154','মিরপুর','ঢাকা','18'), array('155','মতিঝিল','ঢাকা','18'), array('156','মুগদা থানা','ঢাকা','18'), array('157','নবাবগঞ্জ','ঢাকা','18'), array('158','নিউমার্কেট','ঢাকা','18'), array('159','পল্লবী','ঢাকা','18'), array('160','পল্টন','ঢাকা','18'), array('161','রামপুরা','ঢাকা','18'), array('162','সবুজবাগ','ঢাকা','18'), array('163','রূপনগর','ঢাকা','18'), array('164','সাভার','ঢাকা','18'), array('165','শাহজাহানপুর','ঢাকা','18'), array('166','শাহ আলী','ঢাকা','18'), array('167','শাহবাগ','ঢাকা','18'), array('168','শ্যামপুর','ঢাকা','18'), array('169','শের-ই-বাংলা নগর','ঢাকা','18'), array('170','সুত্রাপুর','ঢাকা','18'), array('171','তেজগাঁও','ঢাকা','18'), array('172','তেজগাঁও বাণিজ্যিক এলাকা','ঢাকা','18'), array('173','তুরাগ','ঢাকা','18'), array('174','উত্তরা পশ্চিম','ঢাকা','18'), array('175','উত্তরা পূর্ব','ঢাকা','18'), array('176','উত্তর খান','ঢাকা','18'), array('177','ওয়ারী','ঢাকা','18'), array('178','আলফাডাঙ্গা','ফরিদপুর','19'), array('179','ভাঙ্গা','ফরিদপুর','19'), array('180','বোয়ালমারী','ফরিদপুর','19'), array('181','চরভদ্রাসন','ফরিদপুর','19'), array('182','ফরিদপুর সদর','ফরিদপুর','19'), array('183','মধুখালী','ফরিদপুর','19'), array('184','নগরকান্দা','ফরিদপুর','19'), array('185','সদরপুর','ফরিদপুর','19'), array('186','সালথা','ফরিদপুর','19'), array('187','গাজীপুর সদর','গাজীপুর','20'), array('188','কালিয়াকৈর','গাজীপুর','20'), array('189','কালীগঞ্জ','গাজীপুর','20'), array('190','কাপাসিয়া','গাজীপুর','20'), array('191','শ্রীপুর','গাজীপুর','20'), array('192','গোপালগঞ্জ সদর','গোপালগঞ্জ','21'), array('193','কাশিয়ানী','গোপালগঞ্জ','21'), array('194','কোটালীপাড়া','গোপালগঞ্জ','21'), array('195','মুকসুদপুর','গোপালগঞ্জ','21'), array('196','টুঙ্গিপাড়া','গোপালগঞ্জ','21'), array('197','বকশীগঞ্জ','জামালপুর','22'), array('198','দেওয়ানগঞ্জ','জামালপুর','22'), array('199','ইসলামপুর','জামালপুর','22'), array('200','জামালপুর সদর','জামালপুর','22'), array('201','মাদারগঞ্জ','জামালপুর','22'), array('202','মেলান্দহ','জামালপুর','22'), array('203','সরিষাবাড়ী উপজেলা','জামালপুর','22'), array('204','অষ্টগ্রাম','কিশোরগঞ্জ','23'), array('205','বাজিতপুর','কিশোরগঞ্জ','23'), array('206','ভৈরব','কিশোরগঞ্জ','23'), array('207','হোসেনপুর','কিশোরগঞ্জ','23'), array('208','ইটনা','কিশোরগঞ্জ','23'), array('209','করিমগঞ্জ','কিশোরগঞ্জ','23'), array('210','কটিয়াদি','কিশোরগঞ্জ','23'), array('211','কিশোরগঞ্জ সদর','কিশোরগঞ্জ','23'), array('212','কুলিয়ারচর','কিশোরগঞ্জ','23'), array('213','মিঠামইন','কিশোরগঞ্জ','23'), array('214','নিকলী','কিশোরগঞ্জ','23'), array('215','পাকুন্দিয়া','কিশোরগঞ্জ','23'), array('216','তাড়াইল','কিশোরগঞ্জ','23'), array('217','কালকিনি','মাদারীপুর','24'), array('218','মাদারীপুর সদর','মাদারীপুর','24'), array('219','রাজৈর','মাদারীপুর','24'), array('220','শিবচর','মাদারীপুর','24'), array('221','দৌলতপুর','মানিকগঞ্জ','25'), array('222','ঘিওর','মানিকগঞ্জ','25'), array('223','হরিরামপুর','মানিকগঞ্জ','25'), array('224','মানিকগঞ্জ সদর','মানিকগঞ্জ','25'), array('225','সাটুরিয়া','মানিকগঞ্জ','25'), array('226','শিবালয়','মানিকগঞ্জ','25'), array('227','সিংগাইর','মানিকগঞ্জ','25'), array('228','গজারিয়া','মুন্সীগঞ্জ','26'), array('229','লৌহজং','মুন্সীগঞ্জ','26'), array('230','মুন্সিগঞ্জ সদর','মুন্সীগঞ্জ','26'), array('231','সিরাজদিখান','মুন্সীগঞ্জ','26'), array('232','শ্রীনগর','মুন্সীগঞ্জ','26'), array('233','টঙ্গীবাড়ী','মুন্সীগঞ্জ','26'), array('234','ভালুকা','ময়মনসিংহ','27'), array('235','ধোবাউড়া','ময়মনসিংহ','27'), array('236','ফুলবাড়িয়া','ময়মনসিংহ','27'), array('237','গফরগাঁও','ময়মনসিংহ','27'), array('238','গৌরীপুর','ময়মনসিংহ','27'), array('239','হালুয়াঘাট','ময়মনসিংহ','27'), array('240','ঈশ্বরগঞ্জ','ময়মনসিংহ','27'), array('241','ময়মনসিংহ সদর','ময়মনসিংহ','27'), array('242','মুক্তাগাছা','ময়মনসিংহ','27'), array('243','নান্দাইল','ময়মনসিংহ','27'), array('244','ফুলপুর','ময়মনসিংহ','27'), array('245','তারাকান্দা','ময়মনসিংহ','27'), array('246','ত্রিশাল','ময়মনসিংহ','27'), array('247','আড়াইহাজার','নারায়নগঞ্জ','28'), array('248','সোনারগাঁও','নারায়নগঞ্জ','28'), array('249','বন্দর','নারায়নগঞ্জ','28'), array('250','নারায়ণগঞ্জ সদর','নারায়নগঞ্জ','28'), array('251','রূপগঞ্জ','নারায়নগঞ্জ','28'), array('252','বেলাবু','নরসিংদী','29'), array('253','মনোহরদী','নরসিংদী','29'), array('254','নরসিংদী সদর','নরসিংদী','29'), array('255','পলাশ','নরসিংদী','29'), array('256','রায়পুরা','নরসিংদী','29'), array('257','শিবপুর','নরসিংদী','29'), array('258','আটপাড়া','নেত্রকোণা','30'), array('259','বারহাট্টা','নেত্রকোণা','30'), array('260','দুর্গাপুর','নেত্রকোণা','30'), array('261','খালিয়াজুরী','নেত্রকোণা','30'), array('262','কলমাকান্দা','নেত্রকোণা','30'), array('263','কেন্দুয়া','নেত্রকোণা','30'), array('264','মদন','নেত্রকোণা','30'), array('265','মোহনগঞ্জ','নেত্রকোণা','30'), array('266','নেত্রকোনা সদর','নেত্রকোণা','30'), array('267','পূর্বধলা','নেত্রকোণা','30'), array('268','বালিয়াকান্দি','রাজবাড়ী','31'), array('269','গোয়ালন্দ','রাজবাড়ী','31'), array('270','কালুখালী','রাজবাড়ী','31'), array('271','পাংশা','রাজবাড়ী','31'), array('272','রাজবাড়ী সদর','রাজবাড়ী','31'), array('273','ভেদরগঞ্জ','শরিয়তপুর','32'), array('274','ডামুড্যা','শরিয়তপুর','32'), array('275','গোসাইরহাট','শরিয়তপুর','32'), array('276','নারিয়া','শরিয়তপুর','32'), array('277','শরীয়তপুর সদর','শরিয়তপুর','32'), array('278','জাজিরা','শরিয়তপুর','32'), array('279','ঝিনাইগাতী','শেরপুর','33'), array('280','নকলা','শেরপুর','33'), array('281','নালিতাবাড়ী','শেরপুর','33'), array('282','শেরপুর সদর','শেরপুর','33'), array('283','শ্রীবর্দি','শেরপুর','33'), array('284','বাসাইল','টাঙ্গাইল','34'), array('285','ভূঞাপুর','টাঙ্গাইল','34'), array('286','দেলদুয়ার','টাঙ্গাইল','34'), array('287','ধনবাড়ী','টাঙ্গাইল','34'), array('288','ঘাটাইল','টাঙ্গাইল','34'), array('289','গোপালপুর','টাঙ্গাইল','34'), array('290','কালিহাতী','টাঙ্গাইল','34'), array('291','মধুপুর','টাঙ্গাইল','34'), array('292','মির্জাপুর','টাঙ্গাইল','34'), array('293','নাগরপুর','টাঙ্গাইল','34'), array('294','সখিপুর','টাঙ্গাইল','34'), array('295','টাঙ্গাইল সদর','টাঙ্গাইল','34'), array('383','বাগেরহাট সদর','বাগেরহাট','35'), array('384','চিতলমারী','বাগেরহাট','35'), array('385','ফকিরহাট','বাগেরহাট','35'), array('386','কচুয়া','বাগেরহাট','35'), array('387','মোল্লাহাট','বাগেরহাট','35'), array('388','মংলা','বাগেরহাট','35'), array('389','মোরেলগঞ্জ','বাগেরহাট','35'), array('390','রামপাল','বাগেরহাট','35'), array('391','শরণখোলা','বাগেরহাট','35'), array('392','আলমডাঙ্গা','চুয়াডাঙ্গা','36'), array('393','চুয়াডাঙ্গা সদর','চুয়াডাঙ্গা','36'), array('394','দামুড়হুদা','চুয়াডাঙ্গা','36'), array('395','জীবননগর','চুয়াডাঙ্গা','36'), array('396','অভয়নগর','যশোর','37'), array('397','বাঘারপাড়া','যশোর','37'), array('398','চৌগাছা','যশোর','37'), array('399','ঝিকরগাছা','যশোর','37'), array('400','কেশবপুর','যশোর','37'), array('401','যশোর সদর','যশোর','37'), array('402','মনিরামপুর','যশোর','37'), array('403','শার্শা','যশোর','37'), array('404','হরিনাকুন্ডু','ঝিনাইদহ','38'), array('405','ঝিনাইদহ সদর','ঝিনাইদহ','38'), array('406','কালীগঞ্জ','ঝিনাইদহ','38'), array('407','কোটচাঁদপুর','ঝিনাইদহ','38'), array('408','মহেশপুর','ঝিনাইদহ','38'), array('409','শৈলকূপা','ঝিনাইদহ','38'), array('410','বটিয়াঘাটা','খুলনা','39'), array('411','দাকোপ','খুলনা','39'), array('412','দৌলতপুর','খুলনা','39'), array('413','ডুমুরিয়া','খুলনা','39'), array('414','দিঘলিয়া','খুলনা','39'), array('415','খালিশপুর','খুলনা','39'), array('416','খান জাহান আলী','খুলনা','39'), array('417','খুলনা সদর','খুলনা','39'), array('418','কয়রা','খুলনা','39'), array('419','পাইকগাছা','খুলনা','39'), array('420','ফুলতলা','খুলনা','39'), array('421','রূপসা','খুলনা','39'), array('422','সোনাডাঙ্গা','খুলনা','39'), array('423','তেরখাদা','খুলনা','39'), array('424','ভেড়ামারা','কুষ্টিয়া','40'), array('425','দৌলতপুর','কুষ্টিয়া','40'), array('426','খোকসা','কুষ্টিয়া','40'), array('427','কুমারখালী','কুষ্টিয়া','40'), array('428','কুষ্টিয়া সদর','কুষ্টিয়া','40'), array('429','মিরপুর','কুষ্টিয়া','40'), array('430','মাগুরা সদর','মাগুরা','41'), array('431','মোহাম্মদপুর','মাগুরা','41'), array('432','শালিখা','মাগুরা','41'), array('433','শ্রীপুর','মাগুরা','41'), array('434','গাংনী','মেহেরপুর','42'), array('435','মুজিবনগর','মেহেরপুর','42'), array('436','মেহেরপুর সদর','মেহেরপুর','42'), array('437','কালিয়া','নড়াইল','43'), array('438','লোহাগাড়া','নড়াইল','43'), array('439','নড়াইল সদর','নড়াইল','43'), array('440','আশাশুনি','সাতক্ষীরা','44'), array('441','দেবহাটা','সাতক্ষীরা','44'), array('442','কলারোয়া','সাতক্ষীরা','44'), array('443','কালীগঞ্জ','সাতক্ষীরা','44'), array('444','সাতক্ষীরা সদর','সাতক্ষীরা','44'), array('445','শ্যামনগর','সাতক্ষীরা','44'), array('446','তালা','সাতক্ষীরা','44'), array('510','আদমদীঘি','বগুড়া','45'), array('511','বগুড়া সদর','বগুড়া','45'), array('512','ধুনট','বগুড়া','45'), array('513','দুপচাঁচিয়া','বগুড়া','45'), array('514','গাবতলী','বগুড়া','45'), array('515','কাহালু','বগুড়া','45'), array('516','নন্দীগ্রাম','বগুড়া','45'), array('517','সারিয়াকান্দি','বগুড়া','45'), array('518','শাহজাহানপুর','বগুড়া','45'), array('519','শেরপুর','বগুড়া','45'), array('520','শিবগঞ্জ','বগুড়া','45'), array('521','সোনাতলা','বগুড়া','45'), array('522','আক্কেলপুর','জয়পুরহাট','46'), array('523','জয়পুরহাট সদর','জয়পুরহাট','46'), array('524','কালাই','জয়পুরহাট','46'), array('525','ক্ষেতলাল','জয়পুরহাট','46'), array('526','পাঁচবিবি','জয়পুরহাট','46'), array('527','আত্রাই','নওগাঁ','47'), array('528','বদলগাছি','নওগাঁ','47'), array('529','ধামইরহাট','নওগাঁ','47'), array('530','মান্দা','নওগাঁ','47'), array('531','মহাদেবপুর','নওগাঁ','47'), array('532','নওগাঁ সদর','নওগাঁ','47'), array('533','নিয়ামতপুর','নওগাঁ','47'), array('534','পত্নীতলা','নওগাঁ','47'), array('535','পোরশা','নওগাঁ','47'), array('536','রানীনগর','নওগাঁ','47'), array('537','সাপাহার','নওগাঁ','47'), array('538','বাগাতিপাড়া','নাটোর','48'), array('539','বড়াইগ্রাম','নাটোর','48'), array('540','গুরুদাসপুর','নাটোর','48'), array('541','লালপুর','নাটোর','48'), array('542','নলডাঙ্গা','নাটোর','48'), array('543','নাটোর সদর','নাটোর','48'), array('544','সিংড়া','নাটোর','48'), array('545','ভোলাহাট','চাঁপাই নবাবগঞ্জ','49'), array('546','গোমস্তাপুর','চাঁপাই নবাবগঞ্জ','49'), array('547','নাচোল','চাঁপাই নবাবগঞ্জ','49'), array('548','চাঁপাই নবাবগঞ্জ সদর','চাঁপাই নবাবগঞ্জ','49'), array('549','শিবগঞ্জ','চাঁপাই নবাবগঞ্জ','49'), array('550','আটঘোরিয়া','পাবনা','50'), array('551','বেড়া','পাবনা','50'), array('552','ভাঙ্গুরা','পাবনা','50'), array('553','চাটমোহর','পাবনা','50'), array('554','ফরিদপুর','পাবনা','50'), array('555','ঈশ্বরদী','পাবনা','50'), array('556','পাবনা সদর','পাবনা','50'), array('557','সাঁথিয়া','পাবনা','50'), array('558','সুজানগর','পাবনা','50'), array('559','বাঘা','রাজশাহী','51'), array('560','বাগমারা','রাজশাহী','51'), array('561','বোয়ালিয়া','রাজশাহী','51'), array('562','চারঘাট','রাজশাহী','51'), array('563','দুর্গাপুর','রাজশাহী','51'), array('564','গোদাগাড়ী','রাজশাহী','51'), array('565','মতিহার','রাজশাহী','51'), array('566','মোহনপুর','রাজশাহী','51'), array('567','পাবা','রাজশাহী','51'), array('568','পুঠিয়া','রাজশাহী','51'), array('569','রাজপাড়া','রাজশাহী','51'), array('570','শাহ মখদুম','রাজশাহী','51'), array('571','তানোর','রাজশাহী','51'), array('572','বেলকুচি','সিরাজগঞ্জ','52'), array('573','চৌহালি','সিরাজগঞ্জ','52'), array('574','কামারখন্দ','সিরাজগঞ্জ','52'), array('575','কাজীপুর','সিরাজগঞ্জ','52'), array('576','রায়গঞ্জ','সিরাজগঞ্জ','52'), array('577','শাহজাদপুর','সিরাজগঞ্জ','52'), array('578','সিরাজগঞ্জ সদর','সিরাজগঞ্জ','52'), array('579','তাড়াশ','সিরাজগঞ্জ','52'), array('580','উল্লাপাড়া','সিরাজগঞ্জ','52'), array('637','বিরামপুর','দিনাজপুর','53'), array('638','বীরগঞ্জ','দিনাজপুর','53'), array('639','বিরল','দিনাজপুর','53'), array('640','বোচাগঞ্জ','দিনাজপুর','53'), array('641','চিরিরবন্দর','দিনাজপুর','53'), array('642','ফুলবাড়ি','দিনাজপুর','53'), array('643','ঘোড়াঘাট','দিনাজপুর','53'), array('644','হাকিমপুর','দিনাজপুর','53'), array('645','কাহারোল','দিনাজপুর','53'), array('646','খানসামা','দিনাজপুর','53'), array('647','দিনাজপুর সদর','দিনাজপুর','53'), array('648','নবাবগঞ্জ','দিনাজপুর','53'), array('649','পার্বতীপুর','দিনাজপুর','53'), array('650','ফুলছড়ি','গাইবান্ধা','54'), array('651','গাইবান্ধা সদর','গাইবান্ধা','54'), array('652','গোবিন্দগঞ্জ','গাইবান্ধা','54'), array('653','পলাশবাড়ী','গাইবান্ধা','54'), array('654','সাদুল্লাপুর','গাইবান্ধা','54'), array('655','সাঘাটা','গাইবান্ধা','54'), array('656','সুন্দরগঞ্জ','গাইবান্ধা','54'), array('657','ভুরুঙ্গামারী','কুড়িগ্রাম','55'), array('658','চর রাজিবপুর','কুড়িগ্রাম','55'), array('659','চিলমারী','কুড়িগ্রাম','55'), array('660','ফুলবাড়ী','কুড়িগ্রাম','55'), array('661','কুড়িগ্রাম সদর','কুড়িগ্রাম','55'), array('662','নাগেশ্বরী','কুড়িগ্রাম','55'), array('663','রাজারহাট','কুড়িগ্রাম','55'), array('664','রৌমারী','কুড়িগ্রাম','55'), array('665','উলিপুর','কুড়িগ্রাম','55'), array('666','আদিতমারী','লালমনিরহাট','56'), array('667','হাতীবান্ধা','লালমনিরহাট','56'), array('668','কালীগঞ্জ','লালমনিরহাট','56'), array('669','লালমনিরহাট সদর','লালমনিরহাট','56'), array('670','পাটগ্রাম','লালমনিরহাট','56'), array('671','ডিমলা','নীলফামারী','57'), array('672','ডোমার উপজেলা','নীলফামারী','57'), array('673','জলঢাকা','নীলফামারী','57'), array('674','কিশোরগঞ্জ','নীলফামারী','57'), array('675','নীলফামারী সদর','নীলফামারী','57'), array('676','সৈয়দপুর উপজেলা','নীলফামারী','57'), array('677','আটোয়ারী','পঞ্চগড়','58'), array('678','বোদা','পঞ্চগড়','58'), array('679','দেবীগঞ্জ','পঞ্চগড়','58'), array('680','পঞ্চগড় সদর','পঞ্চগড়','58'), array('681','তেঁতুলিয়া','পঞ্চগড়','58'), array('682','বদরগঞ্জ','রংপুর','59'), array('683','গঙ্গাচড়া','রংপুর','59'), array('684','কাউনিয়া','রংপুর','59'), array('685','রংপুর সদর','রংপুর','59'), array('686','মিঠা পুকুর','রংপুর','59'), array('687','পীরগাছা','রংপুর','59'), array('688','পীরগঞ্জ','রংপুর','59'), array('689','তারাগঞ্জ','রংপুর','59'), array('690','বালিয়াডাঙ্গী','ঠাকুরগাঁও','60'), array('691','হরিপুর','ঠাকুরগাঁও','60'), array('692','পীরগঞ্জ','ঠাকুরগাঁও','60'), array('693','রাণীশংকৈল','ঠাকুরগাঁও','60'), array('694','ঠাকুরগাঁও সদর','ঠাকুরগাঁও','60'), array('700','আজমিরিগঞ্জ','হবিগঞ্জ','61'), array('701','বাহুবল','হবিগঞ্জ','61'), array('702','বানিয়াচং','হবিগঞ্জ','61'), array('703','চুনারুঘাট','হবিগঞ্জ','61'), array('704','হবিগঞ্জ সদর','হবিগঞ্জ','61'), array('705','লাখাই','হবিগঞ্জ','61'), array('706','মাধবপুর','হবিগঞ্জ','61'), array('707','নবীগঞ্জ','হবিগঞ্জ','61'), array('708','বড়লেখা','মৌলভীবাজার','62'), array('709','জুড়ী','মৌলভীবাজার','62'), array('710','কমলগঞ্জ','মৌলভীবাজার','62'), array('711','কুলাউড়া','মৌলভীবাজার','62'), array('712','মৌলভীবাজার সদর','মৌলভীবাজার','62'), array('713','রাজনগর','মৌলভীবাজার','62'), array('714','শ্রীমঙ্গল','মৌলভীবাজার','62'), array('715','বিশ্বম্ভরপুর','সুনামগঞ্জ','63'), array('716','ছাতক','সুনামগঞ্জ','63'), array('717','দক্ষিণ সুনামগঞ্জ','সুনামগঞ্জ','63'), array('718','দিরাই','সুনামগঞ্জ','63'), array('719','ধরমপাশা','সুনামগঞ্জ','63'), array('720','দোয়ারাবাজার','সুনামগঞ্জ','63'), array('721','জগন্নাথপুর','সুনামগঞ্জ','63'), array('722','জামালগঞ্জ','সুনামগঞ্জ','63'), array('723','শাল্লা','সুনামগঞ্জ','63'), array('724','সুনামগঞ্জ সদর','সুনামগঞ্জ','63'), array('725','তাহিরপুর','সুনামগঞ্জ','63'), array('726','বালাগঞ্জ','সিলেট','64'), array('727','বিয়ানিবাজার','সিলেট','64'), array('728','বিশ্বনাথ','সিলেট','64'), array('729','কোম্পানীগঞ্জ','সিলেট','64'), array('730','দক্ষিণ সুরমা','সিলেট','64'), array('731','ফেঞ্চুগঞ্জ','সিলেট','64'), array('732','গোলাপগঞ্জ','সিলেট','64'), array('733','গোয়াইনঘাট','সিলেট','64'), array('734','জৈন্তাপুর','সিলেট','64'), array('735','কানাইঘাট','সিলেট','64'), array('736','সিলেট সদর','সিলেট','64'), array('737','জকিগঞ্জ','সিলেট','64'), array('763','আমতলী','বরগুনা','1'), array('764','বামনা','বরগুনা','1'), array('765','বরগুনা সদর','বরগুনা','1'), array('766','বেতাগী','বরগুনা','1'), array('767','পাথরঘাটা','বরগুনা','1'), array('768','তালতলী','বরগুনা','1'), array('769','আগৈলঝাড়া','বরিশাল','2'), array('770','বাবুগঞ্জ','বরিশাল','2'), array('771','বাকেরগঞ্জ','বরিশাল','2'), array('772','বানারিপাড়া','বরিশাল','2'), array('773','গৌরনদী','বরিশাল','2'), array('774','হিজলা','বরিশাল','2'), array('775','বরিশাল সদর ( কোতোয়ালি )','বরিশাল','2'), array('776','মেহেন্দীগঞ্জ','বরিশাল','2'), array('777','মুলাদী','বরিশাল','2'), array('778','উজিরপুর','বরিশাল','2'), array('779','ভোলা সদর','ভোলা','3'), array('780','বোরহানউদ্দিন','ভোলা','3'), array('781','চরফ্যাসন','ভোলা','3'), array('782','দৌলত খান','ভোলা','3'), array('783','লালমোহন','ভোলা','3'), array('784','মনপুরা','ভোলা','3'), array('785','তজমুদ্দিন','ভোলা','3'), array('786','ঝালকাঠি সদর','ঝালকাঠী','4'), array('787','কাঁঠালিয়া','ঝালকাঠী','4'), array('788','নলছিটি','ঝালকাঠী','4'), array('789','রাজাপুর','ঝালকাঠী','4'), array('790','বাউফল','পটুয়াখালী','5'), array('791','দশমিনা','পটুয়াখালী','5'), array('792','দুমকি','পটুয়াখালী','5'), array('793','গলাচিপা','পটুয়াখালী','5'), array('794','কলাপাড়া','পটুয়াখালী','5'), array('795','মির্জাগঞ্জ','পটুয়াখালী','5'), array('796','পটুয়াখালী সদর','পটুয়াখালী','5'), array('797','রাঙ্গাবালী','পটুয়াখালী','5'), array('798','ভান্ডারিয়া','পিরোজপুর','6'), array('799','কাউখালী','পিরোজপুর','6'), array('800','মঠবাড়িয়া','পিরোজপুর','6'), array('801','নাজিরপুর','পিরোজপুর','6'), array('802','পিরোজপুর সদর','পিরোজপুর','6'), array('803','নেছারাবাদ (স্বরূপকাঠি)','পিরোজপুর','6'), array('804','জিয়ানগর','পিরোজপুর','6'), array('806','ধানমণ্ডি','ঢাকা','18'), array('807','মোহাম্মদপুর','ঢাকা','18'), array('808','রমনা','ঢাকা','18'), array('809','ওসমানী নগর','সিলেট','64'), array('810','দুর্গাপুর','ময়মনসিংহ','27'), array('811','টঙ্গী','গাজীপুর','20'), array('812','লালমাই','কুমিল্লা','11'), array('813','হাজারীবাগ','ঢাকা','18'), array('814','সদরঘাট','চট্রগ্রাম','10'), array('815','চক বাজার','চট্রগ্রাম','10'), array('816','কর্ণফুলী','চট্রগ্রাম','10'), array('817','গুইমারা','খাগড়াছড়ি','14'), array('818','লালবাগ','ঢাকা','18'), array('819','শায়েস্তাগঞ্জ','হবিগঞ্জ','61'), array('820','শাহপরান','সিলেট','64'), array('821','মোগলাবাজার','সিলেট','64'), array('822','এয়ারপোর্ট','সিলেট','64'), array('823','জালালাবাদ','সিলেট','64')
        ); 

        $district_count=count($district_name);
        for ($row = 0; $row < $district_count; $row++) {
            if($district_id == $district_name[$row][0]){  
                $district=$district_name[$row][2];
                break;
            }
        } 

        $upazila_count=count($upazila_name);
        for ($row = 0; $row < $upazila_count; $row++) {
            if($upazilla_id == $upazila_name[$row][0]){  
                $upazilla=$upazila_name[$row][1];
                break;
            }
        } 
        //echo $district."|".$upazilla; exit;
        /*
        $total_rows =  FreedomFighterList::where('district', 'LIKE', "%{$district}%")
                        ->orWhere('upazila_or_thana', 'LIKE', "%{$upazilla}%")
                        ->orderBy('id','asc')
                        ->count();
        */
        if($upazilla_id==''){
            $total_rows =  FreedomFighterList::where('district', 'LIKE', "%{$district}%")
                            ->orderBy('id','asc')
                            ->count();
        }else{
            $total_rows =  FreedomFighterList::where('district', 'LIKE', "%{$district}%")
                            ->where('upazila_or_thana', 'LIKE', "%{$upazilla}%")
                            ->orderBy('id','asc')
                            ->count();   
        }
        $total_rows = $count_qry[0]->total_count; 
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
                //$datas = \DB::select("SELECT * FROM freedom_fighter_list  WHERE district LIKE '%{$district}%' OR upazila_or_thana  LIKE '%{$upazilla}%' ORDER BY id ASC LIMIT " . $initial_page . ',' . $limit );       
                if($upazilla_id==''){
                    $datas = \DB::select("SELECT * FROM freedom_fighter_list  WHERE district LIKE '%{$district}%' ORDER BY id ASC LIMIT " . $initial_page . ',' . $limit );       
                }else{
                    $datas = \DB::select("SELECT * FROM freedom_fighter_list  WHERE district LIKE '%{$district}%' AND upazila_or_thana  LIKE '%{$upazilla}%' ORDER BY id ASC LIMIT " . $initial_page . ',' . $limit );       
                }

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

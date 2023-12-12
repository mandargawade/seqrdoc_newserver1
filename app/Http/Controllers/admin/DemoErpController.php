<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\SuperAdmin;
use DB,Event;
use App\models\DemoERPData;
use Storage;
use Auth;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use App\models\SeqrdocRequests;
class DemoErpController extends Controller
{
    
    public function index(Request $request)
    {

        if($request->ajax()){
            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                 $where_str .= " and (unique_id like \"%{$search}%\""
                 . " or student_name like \"%{$search}%\""
                 . " or mother_name like \"%{$search}%\""
                 . " or degree_type like \"%{$search}%\""
                 . " or degree_name like \"%{$search}%\""
                 . " or passing_year like \"%{$search}%\""
                 . " or cgpa like \"%{$search}%\""
                 . " or status like \"%{$search}%\""
                . ")";
            }

            if(Auth::guard('admin')->user()){
                $auth_site_id=Auth::guard('admin')->user()->site_id;
            }else{
                $auth_site_id=5;
            }                                             
            
            //for serial number
            DB::statement(DB::raw('set @rownum=0'));

            $columns = ['unique_id','student_name','mother_name','degree_type','degree_name','passing_year','cgpa','status','printable_pdf_link','id'];
            
            $categorie_columns_count = DemoERPData::select($columns)
            ->whereRaw($where_str, $where_params)
            ->count();

            $categorie_list = DemoERPData::select($columns)
            ->whereRaw($where_str, $where_params);

            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $categorie_list = $categorie_list->take($request->input('iDisplayLength'))
                ->skip($request->input('iDisplayStart'));
            }          

            if($request->input('iSortCol_0')){
                $sql_order='';
                for ( $i = 0; $i < $request->input('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->input('iSortCol_' . $i)];
                    if(false !== ($index = strpos($column, ' as '))){
                        $column = substr($column, 0, $index);
                    }
                    $categorie_list = $categorie_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            }
            $categorie_list = $categorie_list->get();

            $response['iTotalDisplayRecords'] = $categorie_columns_count;
            $response['iTotalRecords'] = $categorie_columns_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $categorie_list->toArray();

            return $response;
        }
        return view('admin.demoerp.index');
    }

    public function generate(Request $request){


        $studentData = DemoERPData::where('id',$request->id)
                                ->first();

        if($studentData){
            $domain = \Request::getHost();
            $subdomain = explode('.', $domain);
            
            $studentData=$studentData->toArray();
            $arrayHeadings= array_keys($studentData);
            $arrayHeadings=$this->deleteElement($arrayHeadings,7);
            $arrayData= array_values($studentData);
            $arrayData=$this->deleteElement($arrayData,7);
            unset($arrayHeadings[0]);
            unset($arrayData[0]);
            $arrayHeadings = array_values($arrayHeadings);
            $arrayData = array_values($arrayData);
            $arrayGenerate=array($arrayHeadings,$arrayData);
           
           
             $jsonData= json_encode($arrayGenerate);

          
                //Sending backend request
             switch ($arrayData[3]) {
                 case 'UG':
                     $template_id=647;
                     break;
                 case 'PG':
                     $template_id=648;
                     break;
                case 'GC':
                     $template_id="BMCCGC";
                     $jsonData=$studentData['json_data'];
                     break;
                case 'PC':
                     $template_id="BMCCPC";
                     $jsonData=$studentData['json_data'];
                     break;
                 default:
                     exit;
                     break;
             }
               
                $reaquestParameters=array();
                $reaquestParameters['user_id']=1;
                $reaquestParameters['generation_type']='Preview';
                $reaquestParameters['template_id']=$template_id;
                $reaquestParameters['OverWriteRepeat']=1;
                $reaquestParameters['inputSource']='JSON';
                $reaquestParameters['jsonData']=$jsonData;
              
                $call_back_url = "http://".$domain."/api/call-back-url-demo-erp";
                $reaquestParameters['call_back_url']=$call_back_url;
                
               
                $accessToken='9rXIReTTCp4GCmqAWcF0SLW10hKAVceRPxHwERpf';
                $apiKey='I:n0GP5I&ZuNzBc~Lrco;%jI+>cl!X';

                $headers = array
                    (
                    'Authorization: key=SEQRDOC',
                    'Content-Type: application/json',
                    'apikey: '.$apiKey,
                    'accesstoken: '.$accessToken,
                );
                
                $url = "http://".$domain."/api/seqrdoc-generate";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);//true
                curl_setopt($ch, CURLOPT_MAXREDIRS, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reaquestParameters));
                $result = curl_exec($ch);
            
                curl_close($ch);
 
                $updated_at = date('Y-m-d H:i:s');
                $request_id=0;
                $status="Awaiting";
                if($result){

                    $arr=explode('}{', $result);
                    $result=end($arr);
                    
                    if(!$this->startsWith($result,'{')){
                        $result='{'.$result;
                    }
                    $respData=json_decode($result);
                   
                    if($respData&&$respData->status==200){
                        $data=$respData->data;
                        $request_id=$data->request_id;
                    }else{
                        $status="Error";
                    }
                }



                DemoERPData::where('id',$request->id)->update(["updated_at"=>$updated_at,'status'=>$status,'api_data'=>$result,'request_id'=>$request_id]);

         return response()->json(['status'=>true,'message'=>'Certificate generation request sent.']);
        }else{
            return response()->json(['status'=>false,'message'=>'Something went wrong!']);
        }
        
       
             
    }
private function startsWith($string, $startString) { 
  $len = strlen($startString); 
  return (substr($string, 0, $len) === $startString); 
} 
    private function deleteElement($myArrayInit,$offsetKey){
        

    //Lets do the code....
    $n = array_keys($myArrayInit); //<---- Grab all the keys of your actual array and put in another array
    $count = array_search($offsetKey, $n); //<--- Returns the position of the offset from this array using search
    $new_arr = array_slice($myArrayInit, 0, $count + 1, true);//<--- Slice it with the 0 index as start and position+1 as the length parameter.
   return $new_arr;
    }


    public function apiCall(Request $request)
    {
        

     
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
       
     
            $rules = [
                'id' => 'required',
            ];

            $messages = [
                'id.required' => 'Please enter id.',
            ];

            $validator = \Validator::make($request->all(),$rules,$messages);

            if ($validator->fails()) {
               
                  $message = array('status' => 400, 'message' => 'Required parameters not found.');
            }else{
            
                
                 $api_type = $request['api_type'];
                 $studentData = DemoERPData::where('id',$request->id)
                                ->first();

                if($studentData){
                    $domain = \Request::getHost();
                    $subdomain = explode('.', $domain);
                    
                    $studentData=$studentData->toArray();

             
                    
                //Sending backend request
             switch ($studentData['degree_type']) {
                 case 'UG':
                     $template_id=647;
                     break;
                 case 'PG':
                     $template_id=648;
                     break;
                case 'GC':
                     $template_id='\"BMCCGC\"';
                     break;
                case 'PC':
                     $template_id='\"BMCCPC\"';
                     break;
                 default:
                     exit;
                     break;
             }
                     $request_id=$studentData['request_id'];
                    if($api_type =="generate_api"){
                        $apiData=DB::select(DB::raw('SELECT * FROM `seqr_demo`.`api_tracker` WHERE request_parameters LIKE "%\"template_id\":'.$template_id.'%" ORDER BY created DESC LIMIT 1'));
                    }else{
                        $apiData=DB::select(DB::raw('SELECT * FROM `seqr_demo`.`api_tracker` WHERE request_parameters LIKE "%\"request_id\":'.$request_id.'%" AND request_url LIKE "%call-back-url-demo-erp%" ORDER BY created DESC LIMIT 1'));
                    }

                    
                    if($apiData){
                        $apiData=(array)$apiData[0];
                        $apiData['header_parameters']=str_replace("I:n0GP5I&ZuNzBc~Lrco;%jI+>cl!X","XYZ",$apiData['header_parameters']);
                        $apiData['header_parameters']=str_replace('accesstoken":"','accesstoken":"XAsd3!',$apiData['header_parameters']);
                        $apiData['request_parameters']=str_replace('user_id":1','user_id":"ABC"',$apiData['request_parameters']);
                        $apiData['response_date_time']=$apiData['created'];
                        $apiData['response_time']=round($apiData['response_time'],2);
                        $apiData['created']= date("Y-m-d H:i:s", strtotime($apiData['created']) - $apiData['response_time']);

                        
                    $message = array('status'=>200, 'message' => 'success','apiData'=>$apiData);
                    }else{
                       $message = array('status'=>422, 'message' => 'API Data not found.');
                    }
            
                }else{
                    $message = array('status'=>422, 'message' => 'API Data not found.');
                }
                
               
        } 
        
        return $message;
    }

    
}

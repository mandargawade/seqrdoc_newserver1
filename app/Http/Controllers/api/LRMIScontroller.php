<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\WebUserRegisterRequest;
use App\models\Site;
use Hash;
use App\Jobs\SendMailJob;
use Session,TCPDF,TCPDF_FONTS,DB;
use App\models\User;
use App\models\SessionManager;
use Illuminate\Support\Facades\Auth;
use App\Utility\ApiSecurityLayer;
use App\models\ApiTracker;
use App\models\StudentTable;
use App\models\Admin;
class LRMIScontroller extends Controller
{
    public function AddInfo(Request $request)
    {
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain);   
		$data = $request->post();
		//print_r($data['serial_no']); exit;
        /*$data = '{
		"serial_no":"ABC123",
		"verify_file_name":"1.pdf",
		"encrypted_string": "2231FF31FC37637EC38198FB8EB71092"
		}';*/
		
        $serial_no=trim($data['serial_no']);
		$verify_file_name=trim($data['verify_file_name']);
		$encryptedString=trim($data['encrypted_string']);
		
        if(ApiSecurityLayer::checkAuthorization())
        {
            $rules =[
                'serial_no'   => 'required',
                'verify_file_name'   => 'required',
                'encrypted_string'   => 'required'
            ];
            $messages = [
                'serial_no.required'=>'Serial number is required.',
				'verify_file_name.required'=>'Verify file name is required.',
				'encrypted_string.required'=>'Encrypted string is required.'
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
				if(StudentTable::where(['serial_no' => $serial_no])->count() > 0){ 
					DB::select(DB::raw("UPDATE `student_table` SET status='0' WHERE serial_no='".$serial_no."'"));
				}				
				DB::beginTransaction();
				try {					
					$auth_site_id=290;
					$template_id=101;
					$sts = '1';
					$datetime  = date("Y-m-d H:i:s");
					$urlRelativeFilePath = 'qr/'.$encryptedString.'.png';	
					StudentTable::create(['serial_no'=>$serial_no,'certificate_filename'=>$verify_file_name,'template_id'=>$template_id,'key'=>$encryptedString,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>1,'updated_at'=>$datetime,'updated_by'=>1,'status'=>$sts,'site_id'=>$auth_site_id,'template_type'=>3]);
					DB::commit();
					return response()->json(['success'=>true, 'status'=>200, 'message'=>'Record Inserted']); 
				} catch (Exception $e) {
					//print "Something went wrong.<br />";
					//echo 'Exception Message: '. $e->getMessage();
					DB::rollback();
					return response()->json(['success'=>false, 'message'=>$e->getMessage()]);
					exit;
				}
            }
        }
        else
        {
            $message = array('success'=>false, 'status'=>403, 'message' => 'Access forbidden.');
            return $message;
        } 
    }
    


}

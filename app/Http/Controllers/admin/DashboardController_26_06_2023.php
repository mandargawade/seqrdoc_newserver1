<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\Admin;
use App\models\InstituteMaster;
use App\models\TemplateMaster;
use App\models\User;
use App\models\ScannedHistory;
use App\models\Transactions;
use App\models\StudentTable;
use Auth;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;

use QrCode;
use App\Helpers\CoreHelper;
class DashboardController extends Controller
{
    public function create(CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    {
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        // dd('jo');
        $auth_site_id=Auth::guard('admin')->user()->site_id;
    	//Count Active Admin User
    	$this->args['active_admin_user'] = Admin::where('publish',1)
    											->where('status',1)
                                                ->where('site_id',$auth_site_id)
    											->count();
  		
		//Count Inactive Admin User
		$this->args['inactive_admin_user'] = Admin::where('publish',1)
    												->where('status',0)
                                                    ->where('site_id',$auth_site_id)
    												->count();    				
  		//Count Active Institute User
    	$this->args['active_institute_user'] = InstituteMaster::where('publish',1)
    															->where('status',1)
                                                                ->where('site_id',$auth_site_id)
    															->count();
		//Count Inactive Institute User
		$this->args['inactive_institute_user'] = InstituteMaster::where('publish',1)
    																->where('status',0)
                                                                    ->where('site_id',$auth_site_id)
    																->count();    	
    	//Count Active Template
    	$this->args['active_template'] = TemplateMaster::where('status',1)
                                                ->where('site_id',$auth_site_id)
    											->count();
  		
		//Count Inactive Template
		$this->args['inactive_template'] = TemplateMaster::where('status',0)
                                           ->where('site_id',$auth_site_id)
                                           ->count();    

    	//Count Active User
    	$this->args['active_user'] = User::where('publish',1)
                                               ->where('site_id',$auth_site_id)
    											->where('status',1)
    											->count();
		//Count Inactive User
		$this->args['inactive_user'] = User::where('publish',1)
                                                ->where('site_id',$auth_site_id)
    											->where('status',0)
    											->count();  
        //Count Active  Certificates
        $this->args['active_certificates'] = StudentTable::where('status','1')
                                                ->where('site_id',$auth_site_id)
                                                ->count();

        $this->args['active_certificates_short'] =CoreHelper::thousandsCurrencyFormat($this->args['active_certificates']);
        
        //Count Inactive Certificates
        $this->args['inactive_certificates'] = StudentTable::where('status',"0")
                                                    ->where('site_id',$auth_site_id)
                                                    ->count();  
        $this->args['inactive_certificates_short'] =CoreHelper::thousandsCurrencyFormat($this->args['inactive_certificates']);
        
    	// start scaning count history for android

    	$this->args['active_total_android_scanned'] = ScannedHistory::join('user_table','user_table.id','scanned_history.scan_by')
    											->where('scan_result',1)
                                                ->where('scanned_history.site_id',$auth_site_id) 
    											->where('scanned_history.device_type','android')->count();
        // dd($this->args['active_total_android_scanned']);
    	$this->args['active_unique_android_scanned'] = ScannedHistory::join('user_table','scanned_history.scan_by','user_table.id')
    											->where('scan_result',1)
                                                 ->where('scanned_history.site_id',$auth_site_id) 
    											->where('scanned_history.device_type','android')->distinct('scanned_data')->count();
    	
    	$this->args['inactive_total_android_scanned'] = ScannedHistory::select('*')
    											->join('user_table','scanned_history.scan_by','user_table.id')
    											->where('scan_result',0)
                                                 ->where('scanned_history.site_id',$auth_site_id) 
    											->where('scanned_history.device_type','android')->count();

    	$this->args['inactive_unique_android_scanned'] = ScannedHistory::join('user_table','scanned_history.scan_by','user_table.id')
    											->where('scan_result',0)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','android')->distinct('scanned_data')->count();
    	
    	// institute android
    	$this->args['institute_active_total_android_scanned'] = ScannedHistory::select('*')
    											->join('institute_table','scanned_history.scan_by','institute_table.institute_username')
    											->where('scan_result',1)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','android')->count();

    	$this->args['institute_active_unique_android_scanned'] = ScannedHistory::join('institute_table','scanned_history.scan_by','institute_table.institute_username')
    											->where('scan_result',1)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','android')->distinct('scanned_data')->count();
    	
    	$this->args['institute_inactive_total_android_scanned'] = ScannedHistory::select('*')
    											->join('institute_table','scanned_history.scan_by','institute_table.institute_username')
    											->where('scan_result',0)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','android')->count();

    	$this->args['institute_inactive_unique_android_scanned'] = ScannedHistory::join('institute_table','scanned_history.scan_by','institute_table.institute_username')
    											->where('scan_result',0)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','android')->distinct('scanned_data')->count();
    	

   		// start scaning count history for ios

    	$this->args['active_total_ios_scanned'] = ScannedHistory::select('*')
    											->join('user_table','scanned_history.scan_by','user_table.id')
    											->where('scan_result',1)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','ios')->count();

    	$this->args['active_unique_ios_scanned'] = ScannedHistory::join('user_table','scanned_history.scan_by','user_table.id')
    											->where('scan_result',1)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','ios')->distinct('scanned_data')->count();
    	
    	$this->args['inactive_total_ios_scanned'] = ScannedHistory::select('*')
    											->join('user_table','scanned_history.scan_by','user_table.id')
    											->where('scan_result',0)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','ios')->count();

    	$this->args['inactive_unique_ios_scanned'] = ScannedHistory::join('user_table','scanned_history.scan_by','user_table.id')
    											->where('scan_result',0)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','ios')->distinct('scanned_data')->count();
    	
    	// institute ios
    	$this->args['institute_active_total_ios_scanned'] = ScannedHistory::select('*')
    											->join('institute_table','scanned_history.scan_by','institute_table.institute_username')
    											->where('scan_result',1)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','ios')->count();

    	$this->args['institute_active_unique_ios_scanned'] = ScannedHistory::join('institute_table','scanned_history.scan_by','institute_table.institute_username')
    											->where('scan_result',1)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','ios')->distinct('scanned_data')->count();
    	
    	$this->args['institute_inactive_total_ios_scanned'] = ScannedHistory::select('*')
    											->join('institute_table','scanned_history.scan_by','institute_table.institute_username')
    											->where('scan_result',0)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','ios')->count();

    	$this->args['institute_inactive_unique_ios_scanned'] = ScannedHistory::join('institute_table','scanned_history.scan_by','institute_table.institute_username')
    											->where('scan_result',0)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','ios')->distinct('scanned_data')->count();

    	// start scaning count history for WebApp

    	$this->args['active_total_webapp_scanned'] = ScannedHistory::select('*')
    											->join('user_table','scanned_history.scan_by','user_table.id')
    											->where('scan_result',1)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','WebApp')->count();

    	$this->args['active_unique_webapp_scanned'] = ScannedHistory::join('user_table','scanned_history.scan_by','user_table.id')
    											->where('scan_result',1)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','WebApp')->distinct('scanned_data')->count();
    	
    	$this->args['inactive_total_webapp_scanned'] = ScannedHistory::select('*')
    											->join('user_table','scanned_history.scan_by','user_table.id')
    											->where('scan_result',0)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','WebApp')->count();

    	$this->args['inactive_unique_webapp_scanned'] = ScannedHistory::join('user_table','scanned_history.scan_by','user_table.id')
    											->where('scan_result',0)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','WebApp')->distinct('scanned_data')->count();
    	
    	// institute webapp
    	$this->args['institute_active_total_webapp_scanned'] = ScannedHistory::select('*')
    											->join('institute_table','scanned_history.scan_by','institute_table.institute_username')
    											->where('scan_result',1)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','WebApp')->count();

    	$this->args['institute_active_unique_webapp_scanned'] = ScannedHistory::join('institute_table','scanned_history.scan_by','institute_table.institute_username')
    											->where('scan_result',1)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','WebApp')->distinct('scanned_data')->count();
    	
    	$this->args['institute_inactive_total_webapp_scanned'] = ScannedHistory::select('*')
    											->join('institute_table','scanned_history.scan_by','institute_table.institute_username')
    											->where('scan_result',0)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','WebApp')->count();

    	$this->args['institute_inactive_unique_webapp_scanned'] = ScannedHistory::join('institute_table','scanned_history.scan_by','institute_table.institute_username')
    											->where('scan_result',0)
                                                ->where('scanned_history.site_id',$auth_site_id)
    											->where('scanned_history.device_type','WebApp')->distinct('scanned_data')->count();

    	// Grand Total (End Total)

    	$this->args['active_grandtotal_total_scanned'] = $this->args['active_total_android_scanned'] +	$this->args['active_total_ios_scanned'] + $this->args['active_total_webapp_scanned'];

    	$this->args['active_grandtotal_unique_scannd'] = $this->args['active_unique_android_scanned'] + $this->args['active_unique_ios_scanned'] + $this->args['active_unique_webapp_scanned'];

    	$this->args['inactive_grandtotal_total_scanned'] = $this->args['inactive_total_android_scanned'] +	$this->args['inactive_total_ios_scanned'] + $this->args['inactive_total_webapp_scanned'];

    	$this->args['inactive_grandtotal_unique_scanned'] = $this->args['inactive_unique_android_scanned'] + $this->args['inactive_unique_ios_scanned'] + $this->args['inactive_unique_webapp_scanned'];

    	$this->args['institute_active_grandtotal_total_scanned'] = $this->args['institute_active_total_android_scanned'] +	$this->args['institute_active_total_ios_scanned'] + $this->args['institute_active_total_webapp_scanned'];
    	
    	$this->args['institute_active_grandtotal_unique_scanned'] = $this->args['institute_active_unique_android_scanned'] + $this->args['institute_active_unique_ios_scanned'] + $this->args['institute_active_unique_webapp_scanned'];

    	$this->args['institute_inactive_grandtotal_total_scanned'] = $this->args['institute_inactive_total_android_scanned'] +	$this->args['institute_inactive_total_ios_scanned'] + $this->args['institute_inactive_total_webapp_scanned'];

    	$this->args['institute_inactive_grandtotal_unique_scanned'] = $this->args['institute_inactive_unique_android_scanned'] + $this->args['institute_inactive_unique_ios_scanned'] + $this->args['institute_inactive_unique_webapp_scanned'];

    	// Transaction 

    	$this->args['transaction'] = Transactions::select('transactions.trans_id_ref','amount','user_table.fullname','student_table.student_name','transactions.created_at')
                ->leftjoin('user_table','transactions.user_id','user_table.id')
    			->leftjoin('student_table','transactions.student_key','student_table.key')
				->where('transactions.publish',1)
                ->where('transactions.site_id',$auth_site_id)
				->orderBy('transactions.created_at','desc')
				->take(5)
				->get()
				->toArray();
                // last 3 scans

        $columns = ['scanned_history.device_type','scanned_history.date_time','scanned_history.scanned_data','user_table.username','student_table.path','student_table.key'];
    	$this->args['scan_data'] = ScannedHistory::select($columns)
    												->join('user_table','scanned_history.scan_by','user_table.id')
                                                    ->join('student_table','scanned_history.scanned_data','student_table.key')
    												->where('scanned_history.scan_result','1')
                                                    ->where('scanned_history.site_id',$auth_site_id)
    												->orderBy('scanned_history.date_time','desc')
    												->take(3)
    												->get()->toArray(); 
                
            $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
                if($this->args['scan_data']){

                    foreach ($this->args['scan_data'] as $readData) {
                        $key=$readData['key'];


                    $local_server_qr_path = public_path().'/'.$subdomain[0].'/backend/canvas/images/qr/'.$key.'.png';
                
                   if(!is_file($filePath) || !file_exists($local_server_qr_path)){                
                        $temp_path = public_path().'/backend/qr/'.$key.'.png';

                        QrCode::format('png')
                                ->size(200)
                                ->generate($key,$temp_path);

                        
                        if($get_file_aws_local_flag->file_aws_local == '1'){      
                            $aws_qr_path = '/'.$subdomain[0].'/backend/canvas/images/qr/'.$key.'.png';

                            $aws_qr = \Storage::disk('s3')->put($aws_qr_path, file_get_contents($temp_path), 'public');
                            
                            $get_qr_image = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/images/qr/'.$key.'.png';
                        }
                        else{

                            //$local_server_qr_path = public_path().'/'.$subdomain[0].'/backend/canvas/images/qr/'.$key.'.png';
                           /* $localQrDir= public_path().'/'.$subdomain[0].'/backend/canvas/images/qr';

                            if(!is_dir($localQrDir)){

                                mkdir($localQrDir, 0777);
                            }*/
                            

                           /* echo $local_server_qr_path;
                            exit;*/
                            
                            copy($temp_path,$local_server_qr_path);
                            
                            $get_qr_image = 'http://'.$subdomain[0].'.seqrdoc.com/'.$subdomain[0].'/backend/canvas/images/qr/'.$key.'.png';

                        }

                        @unlink($temp_path);

                   }
                }
        }
        $this->args['get_file_aws_local_flag'] = $get_file_aws_local_flag;

        
    	return view('admin.dashboard.create',$this->args);
    }
}

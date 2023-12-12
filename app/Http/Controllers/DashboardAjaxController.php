<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\models\Admin;
use App\models\InstituteMaster;
use App\models\TemplateMaster;
use App\models\User;
use App\models\ScannedHistory;
use App\models\Transactions;
use App\models\StudentTable;
use Auth;
use QrCode;
use App\Helpers\CoreHelper;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;

class DashboardAjaxController extends Controller
{
    

    // AdminCount count
    public function AdminCount() {
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        //Count Active Admin User
        $active_admin_user = Admin::where('publish',1)
                            ->where('status',1)
                            ->where('site_id',$auth_site_id)
                            ->count();

        //Count Inactive Admin User
        $inactive_admin_user = Admin::where('publish',1)
                            ->where('status',0)
                            ->where('site_id',$auth_site_id)
                            ->count();
        return response()->json([
            'status' => 200,
            'active_admin_user'=> $active_admin_user,
            'inactive_admin_user'=> $inactive_admin_user
        ]);
    }

    // instituteUserCount count
    public function instituteUserCount() {
        $auth_site_id=Auth::guard('admin')->user()->site_id;
       //Count Active Institute User
        $active_institute_user = InstituteMaster::where('publish',1)
                            ->where('status',1)
                            ->where('site_id',$auth_site_id)
                            ->count();
        //Count Inactive Institute User
        $inactive_institute_user = InstituteMaster::where('publish',1)
                            ->where('status',0)
                            ->where('site_id',$auth_site_id)
                            ->count();

        return response()->json([
            'status' => 200,
            'active_institute_user'=> $active_institute_user,
            'inactive_institute_user'=> $inactive_institute_user
        ]);
    }


    // templateCount count
    public function templateCount() {
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        //Count Active Template
        $active_template = TemplateMaster::where('status',1)
                            ->where('site_id',$auth_site_id)
                            ->count();
        
        //Count Inactive Template
        $inactive_template = TemplateMaster::where('status',0)
                           ->where('site_id',$auth_site_id)
                           ->count();


        return response()->json([
            'status' => 200,
            'active_template'=> $active_template,
            'inactive_template'=> $inactive_template
        ]);
    }


    // user count
    public function userCount() {
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        
        //Count Active User
        $active_user = User::where('publish',1)
                        ->where('site_id',$auth_site_id)
                        ->where('status',1)
                        ->count();
        //Count Inactive User
        $inactive_user = User::where('publish',1)
                        ->where('site_id',$auth_site_id)
                        ->where('status',0)
                        ->count();


        return response()->json([
            'status' => 200,
            'active_user'=> $active_user,
            'inactive_user'=> $inactive_user
        ]);
    }


    // certificate count
    public function certificateCount() {
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        
        //Count Active  Certificates
        $active_certificates = StudentTable::where('status','1')
                            ->where('site_id',$auth_site_id)
                            ->count();

        $active_certificates_short = CoreHelper::thousandsCurrencyFormat($active_certificates);
        
        //Count Inactive Certificates
        $inactive_certificates = StudentTable::where('status',"0")
                            ->where('site_id',$auth_site_id)
                            ->count();  
        
        $inactive_certificates_short=CoreHelper::thousandsCurrencyFormat($inactive_certificates);

        return response()->json([
            'status' => 200,
            'active_certificates'=> $active_certificates,
            'active_certificates_short'=> $active_certificates_short,
            'inactive_certificates'=> $inactive_certificates,
            'inactive_certificates_short'=> $inactive_certificates_short
        ]);
    }


    // android app count
    public function appAndroidCount() {
        $auth_site_id=Auth::guard('admin')->user()->site_id;

        // start scaning count history for android
        $active_total_android_scanned = ScannedHistory::join('user_table','user_table.id','scanned_history.scan_by')->where('scan_result',1)
                            ->where('scanned_history.site_id',$auth_site_id) 
                            ->where('scanned_history.device_type','android')->count();
        
        $active_unique_android_scanned = ScannedHistory::join('user_table','scanned_history.scan_by','user_table.id')->where('scan_result',1)
                            ->where('scanned_history.site_id',$auth_site_id) 
                            ->where('scanned_history.device_type','android')->distinct('scanned_data')->count();

        $inactive_total_android_scanned = ScannedHistory::select('*')
                            ->join('user_table','scanned_history.scan_by','user_table.id')
                            ->where('scan_result',0)
                            ->where('scanned_history.site_id',$auth_site_id) 
                            ->where('scanned_history.device_type','android')->count();

        $inactive_unique_android_scanned = ScannedHistory::join('user_table','scanned_history.scan_by','user_table.id')
                            ->where('scan_result',0)
                            ->where('scanned_history.site_id',$auth_site_id)
                            ->where('scanned_history.device_type','android')->distinct('scanned_data')->count();


        // institute android
        $institute_active_total_android_scanned = ScannedHistory::select('*')
                            ->join('institute_table','scanned_history.scan_by','institute_table.institute_username')
                            ->where('scan_result',1)
                            ->where('scanned_history.site_id',$auth_site_id)
                            ->where('scanned_history.device_type','android')->count();

        $institute_active_unique_android_scanned = ScannedHistory::join('institute_table','scanned_history.scan_by','institute_table.institute_username')
                            ->where('scan_result',1)
                            ->where('scanned_history.site_id',$auth_site_id)
                            ->where('scanned_history.device_type','android')->distinct('scanned_data')->count();
        
        $institute_inactive_total_android_scanned = ScannedHistory::select('*')
                            ->join('institute_table','scanned_history.scan_by','institute_table.institute_username')
                            ->where('scan_result',0)
                            ->where('scanned_history.site_id',$auth_site_id)
                            ->where('scanned_history.device_type','android')->count();

        $institute_inactive_unique_android_scanned = ScannedHistory::join('institute_table','scanned_history.scan_by','institute_table.institute_username')
                            ->where('scan_result',0)
                            ->where('scanned_history.site_id',$auth_site_id)
                            ->where('scanned_history.device_type','android')->distinct('scanned_data')->count();


        return response()->json([
            // 'status' => 200,
            'active_unique_android_scanned'=> $active_unique_android_scanned,
            'active_total_android_scanned'=> $active_total_android_scanned,
            'inactive_unique_android_scanned'=> $inactive_unique_android_scanned,
            'inactive_total_android_scanned'=> $inactive_total_android_scanned,

            'institute_active_total_android_scanned'=> $institute_active_total_android_scanned,
            'institute_active_unique_android_scanned'=> $institute_active_unique_android_scanned,
            'institute_inactive_total_android_scanned'=> $institute_inactive_total_android_scanned,
            'institute_inactive_unique_android_scanned'=> $institute_inactive_unique_android_scanned,
        ],200);

    }


    // ios app count
    public function appIosCount() {
        $auth_site_id=Auth::guard('admin')->user()->site_id;


        // start scaning count history for ios
        $active_total_ios_scanned = ScannedHistory::select('*')
                            ->join('user_table','scanned_history.scan_by','user_table.id')
                            ->where('scan_result',1)
                            ->where('scanned_history.site_id',$auth_site_id)
                            ->where('scanned_history.device_type','ios')->count();

        $active_unique_ios_scanned = ScannedHistory::join('user_table','scanned_history.scan_by','user_table.id')
                            ->where('scan_result',1)
                            ->where('scanned_history.site_id',$auth_site_id)
                            ->where('scanned_history.device_type','ios')->distinct('scanned_data')->count();
        
        $inactive_total_ios_scanned = ScannedHistory::select('*')
                            ->join('user_table','scanned_history.scan_by','user_table.id')
                            ->where('scan_result',0)
                            ->where('scanned_history.site_id',$auth_site_id)
                            ->where('scanned_history.device_type','ios')->count();

        $inactive_unique_ios_scanned = ScannedHistory::join('user_table','scanned_history.scan_by','user_table.id')
                            ->where('scan_result',0)
                            ->where('scanned_history.site_id',$auth_site_id)
                            ->where('scanned_history.device_type','ios')->distinct('scanned_data')->count();
        
        // institute ios
        $institute_active_total_ios_scanned = ScannedHistory::select('*')
                            ->join('institute_table','scanned_history.scan_by','institute_table.institute_username')
                            ->where('scan_result',1)
                            ->where('scanned_history.site_id',$auth_site_id)
                            ->where('scanned_history.device_type','ios')->count();

        $institute_active_unique_ios_scanned = ScannedHistory::join('institute_table','scanned_history.scan_by','institute_table.institute_username')
                            ->where('scan_result',1)
                            ->where('scanned_history.site_id',$auth_site_id)
                            ->where('scanned_history.device_type','ios')->distinct('scanned_data')->count();
        
        $institute_inactive_total_ios_scanned = ScannedHistory::select('*')
                            ->join('institute_table','scanned_history.scan_by','institute_table.institute_username')
                            ->where('scan_result',0)
                            ->where('scanned_history.site_id',$auth_site_id)
                            ->where('scanned_history.device_type','ios')->count();

        $institute_inactive_unique_ios_scanned = ScannedHistory::join('institute_table','scanned_history.scan_by','institute_table.institute_username')
                            ->where('scan_result',0)
                            ->where('scanned_history.site_id',$auth_site_id)
                            ->where('scanned_history.device_type','ios')->distinct('scanned_data')->count();

        return response()->json([
            // 'status' => 200,
            'active_total_ios_scanned'=> $active_total_ios_scanned,
            'active_unique_ios_scanned'=> $active_unique_ios_scanned,
            'inactive_total_ios_scanned'=> $inactive_total_ios_scanned,
            'inactive_unique_ios_scanned'=> $inactive_unique_ios_scanned,
            'institute_active_total_ios_scanned'=> $institute_active_total_ios_scanned,
            'institute_active_unique_ios_scanned'=> $institute_active_unique_ios_scanned,
            'institute_inactive_total_ios_scanned'=> $institute_inactive_total_ios_scanned,
            'institute_inactive_unique_ios_scanned'=> $institute_inactive_unique_ios_scanned,
        ],200);
    }


    // web app count
    public function appWebCount() {
        $auth_site_id=Auth::guard('admin')->user()->site_id;


        // start scaning count history for WebApp

        $active_total_webapp_scanned = ScannedHistory::select('*')
                                ->join('user_table','scanned_history.scan_by','user_table.id')
                                ->where('scan_result',1)
                                ->where('scanned_history.site_id',$auth_site_id)
                                ->where('scanned_history.device_type','WebApp')->count();

        $active_unique_webapp_scanned = ScannedHistory::join('user_table','scanned_history.scan_by','user_table.id')
                                ->where('scan_result',1)
                                ->where('scanned_history.site_id',$auth_site_id)
                                ->where('scanned_history.device_type','WebApp')->distinct('scanned_data')->count();

        $inactive_total_webapp_scanned = ScannedHistory::select('*')
                                ->join('user_table','scanned_history.scan_by','user_table.id')
                                ->where('scan_result',0)
                                ->where('scanned_history.site_id',$auth_site_id)
                                ->where('scanned_history.device_type','WebApp')->count();

        $inactive_unique_webapp_scanned = ScannedHistory::join('user_table','scanned_history.scan_by','user_table.id')
                                ->where('scan_result',0)
                                ->where('scanned_history.site_id',$auth_site_id)
                                ->where('scanned_history.device_type','WebApp')->distinct('scanned_data')->count();
        
        // institute webapp
        $institute_active_total_webapp_scanned = ScannedHistory::select('*')
                                ->join('institute_table','scanned_history.scan_by','institute_table.institute_username')
                                ->where('scan_result',1)
                                ->where('scanned_history.site_id',$auth_site_id)
                                ->where('scanned_history.device_type','WebApp')->count();

        $institute_active_unique_webapp_scanned = ScannedHistory::join('institute_table','scanned_history.scan_by','institute_table.institute_username')
                                ->where('scan_result',1)
                                ->where('scanned_history.site_id',$auth_site_id)
                                ->where('scanned_history.device_type','WebApp')->distinct('scanned_data')->count();
        
        $institute_inactive_total_webapp_scanned = ScannedHistory::select('*')
                                ->join('institute_table','scanned_history.scan_by','institute_table.institute_username')
                                ->where('scan_result',0)
                                ->where('scanned_history.site_id',$auth_site_id)
                                ->where('scanned_history.device_type','WebApp')->count();

        $institute_inactive_unique_webapp_scanned = ScannedHistory::join('institute_table','scanned_history.scan_by','institute_table.institute_username')
                                ->where('scan_result',0)
                                ->where('scanned_history.site_id',$auth_site_id)
                                ->where('scanned_history.device_type','WebApp')->distinct('scanned_data')->count();

        return response()->json([
            // 'status' => 200,
            'active_total_webapp_scanned'=> $active_total_webapp_scanned,
            'active_unique_webapp_scanned'=> $active_unique_webapp_scanned,
            'inactive_total_webapp_scanned'=> $inactive_total_webapp_scanned,
            'inactive_unique_webapp_scanned'=> $inactive_unique_webapp_scanned,
            'institute_active_total_webapp_scanned'=> $institute_active_total_webapp_scanned,
            'institute_active_unique_webapp_scanned'=> $institute_active_unique_webapp_scanned,
            'institute_inactive_total_webapp_scanned'=> $institute_inactive_total_webapp_scanned,
            'institute_inactive_unique_webapp_scanned'=> $institute_inactive_unique_webapp_scanned,
        ],200);
    }

    // app grand count
    public function appGrandCount() {
        $android = $this->appAndroidCount();
        $ios = $this->appIosCount();
        $web = $this->appWebCount();
        
        $android = $android->getData();
        $ios = $ios->getData();
        $web = $web->getData();
        
        
        // Grand Total (End Total)

        $active_grandtotal_total_scanned = $android->active_total_android_scanned +  $ios->active_total_ios_scanned + $web->active_total_webapp_scanned;

        $active_grandtotal_unique_scannd = $android->active_unique_android_scanned + $ios->active_unique_ios_scanned + $web->active_unique_webapp_scanned;

        $inactive_grandtotal_total_scanned = $android->inactive_total_android_scanned +  $ios->inactive_total_ios_scanned + $web->inactive_total_webapp_scanned;

        $inactive_grandtotal_unique_scanned = $android->inactive_unique_android_scanned + $ios->inactive_unique_ios_scanned + $web->inactive_unique_webapp_scanned;

        $institute_active_grandtotal_total_scanned = $android->institute_active_total_android_scanned +  $ios->institute_active_total_ios_scanned + $web->institute_active_total_webapp_scanned;
        
        $institute_active_grandtotal_unique_scanned = $android->institute_active_unique_android_scanned + $ios->institute_active_unique_ios_scanned + $web->institute_active_unique_webapp_scanned;

        $institute_inactive_grandtotal_total_scanned = $android->institute_inactive_total_android_scanned +  $ios->institute_inactive_total_ios_scanned + $web->institute_inactive_total_webapp_scanned;

        $institute_inactive_grandtotal_unique_scanned = $android->institute_inactive_unique_android_scanned + $ios->institute_inactive_unique_ios_scanned + $web->institute_inactive_unique_webapp_scanned;

        return response()->json([
            'active_grandtotal_total_scanned'=> $active_grandtotal_total_scanned,
            'active_grandtotal_unique_scannd'=> $active_grandtotal_unique_scannd,
            'inactive_grandtotal_total_scanned'=> $inactive_grandtotal_total_scanned,
            'inactive_grandtotal_unique_scanned'=> $inactive_grandtotal_unique_scanned,
            'institute_active_grandtotal_total_scanned'=> $institute_active_grandtotal_total_scanned,
            'institute_active_grandtotal_unique_scanned'=> $institute_active_grandtotal_unique_scanned,
            'institute_inactive_grandtotal_total_scanned'=> $institute_inactive_grandtotal_total_scanned,
            'institute_inactive_grandtotal_unique_scanned'=> $institute_inactive_grandtotal_unique_scanned,
        ],200);

    }


    public function transactionData() {
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $transaction = Transactions::select('transactions.trans_id_ref','amount','user_table.fullname','student_table.student_name','transactions.created_at')
                ->leftjoin('user_table','transactions.user_id','user_table.id')
                ->leftjoin('student_table','transactions.student_key','student_table.key')
                ->where('transactions.publish',1)
                ->where('transactions.site_id',$auth_site_id)
                ->orderBy('transactions.created_at','desc')
                ->take(5)
                ->get()
                ->toArray();

        $html = '';
        foreach($transaction as $value) {
            $html .= "<tr>
                <td>".$value['trans_id_ref']." </td>
                <td class='text-center'>".$value['amount']."</td>
                <td>".$value['fullname']."</td>
                <td>".$value['student_name']."</td>
            </tr>";
        }
        return response()->json([
            'html'=> $html,
        ],200);
        // print_r($html);

    }

    // scan data
    public function scanData(CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal) {

        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $columns = ['scanned_history.device_type','scanned_history.date_time','scanned_history.scanned_data','user_table.username','student_table.path','student_table.key'];
        $scan_data = ScannedHistory::select($columns)
                                ->join('user_table','scanned_history.scan_by','user_table.id')
                                ->join('student_table','scanned_history.scanned_data','student_table.key')
                                ->where('scanned_history.scan_result','1')
                                ->where('scanned_history.site_id',$auth_site_id)
                                ->orderBy('scanned_history.date_time','desc')
                                ->take(3)
                                ->get()->toArray();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        if($scan_data){

            foreach ($scan_data as $readData) {
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
        $get_file_aws_local_flag = $get_file_aws_local_flag;
        $html = '';
        foreach($scan_data as $value) {
            $device_type = "";
            if ($value['device_type']== 'android') {
                $device_type = '<i class="fa fa-fw fa-2x fa-android green"></i>';
            } elseif($value['device_type'] == 'ios') {
                $device_type = '<i class="fa fa-fw fa-2x fa-apple"></i>';
            } elseif($value['device_type'] == 'WebApp') {
                $device_type = '<i class="fa fa-fw fa-2x fa-desktop"></i>';
            } else {
                $device_type = "No device";
            }
            $date_time = date("d M Y h:i A", strtotime($value['date_time'])) ;
            if($get_file_aws_local_flag == '1'){
                $qr_code_image_path = \Config::get('constant.aws_canvas_upload_path').'/'.$value['path'];
            }
            else{
                $qr_code_image_path = \Config::get('constant.server_canvas_upload_path').'/'.$value['path'];
            }
            $html .=  "
            <tr>
                <td class='text-center'><img src=".$qr_code_image_path."  class='' style='width:50px;width:50px'></td>
                <td class='text-center'>".$value['username']." <br>".$device_type."<br><label style='font-size: 8px;'>".$date_time."</label></td>
            </tr>";

        }
        
        

        return response()->json([
            'html'=> $html,
        ],200);

    }
}

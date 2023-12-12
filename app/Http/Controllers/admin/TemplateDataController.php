<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\TemplateMaster;
use App\models\StudentTable;
use DB;

//list of templates
class TemplateDataController extends Controller
{
    public function index(Request $request)
    {   
        $template_data_list = TemplateMaster::select('id','template_name','status')
                                            ->groupBy('id','template_name','status')
                                            ->orderBy('updated_at','desc')
                                            ->get()
                                            ->toArray();

        $activeData = [];
        $inActiveData = [];
        $gotData = [];
        foreach ($template_data_list as $key => $value) {
            
            $template_id = $value['id'];
            
            $last_updated = StudentTable::select('updated_at')->where('template_id',$template_id)->orderBy('updated_at','desc')->get()->toArray();

            $resultActive = StudentTable::where(['status'=>'1','template_id'=>$template_id])->get()->count();

            array_push($template_data_list[$key],$resultActive);
            $resultInActive = StudentTable::where(['status'=>'0','template_id'=>$template_id])->get()->count();
            array_push($template_data_list[$key], $resultInActive);

            if(isset($last_updated[0]['updated_at'])){

                array_push($template_data_list[$key], $last_updated[0]['updated_at']);
            }else{
                
                array_push($template_data_list[$key], '');
            }
        }
        $i = 0;
        $new_arr = [];
        $template_data = [];
        foreach ($template_data_list as $key => $value) {

            $active_scanned = 0;        
            $deActive_scanned = 0;
            $active_verifier_count = 0; 
            $deactive_verifier_count = 0;   

            $value = json_decode(json_encode($value),true);
            $template_name = $value['id'];
            // get scanned data based on template name and scan_result=1(active)
            $scanned_data = DB::select("SELECT DISTINCT `key`,`scanned_history`.`scan_by` FROM student_table LEFT JOIN scanned_history ON `student_table`.`key`= `scanned_history`.`scanned_data` WHERE `student_table`.`template_id`='$template_name' AND `scanned_history`.`scan_result` = 1");
            $active_scanned_data = json_decode(json_encode($scanned_data),true);
            if(count($active_scanned_data) > 0){
                foreach ($active_scanned_data as $a_key => $a_value) {
                    $scan_by = $active_scanned_data[$a_key]['scan_by'];
                    $active_verifier_data =  DB::select("SELECT count(`id`) as c_id from user_table where id='$scan_by'");
                   
                    $active_ver_scanned_data = json_decode(json_encode($active_verifier_data),true);

                    if($active_ver_scanned_data[0]['c_id'] > 0){
                        $active_verifier_count+=1;
                    }
                }
                $active_scanned=count($active_scanned_data);
                    
            }
            // get scanned data based on template name and scan_result=0(deactive)

            $de_scanned_data = DB::select("SELECT DISTINCT `key`,`scanned_history`.`scan_by` FROM student_table LEFT JOIN scanned_history ON `student_table`.`key`= `scanned_history`.`scanned_data` WHERE `student_table`.`template_id`='$template_name' AND `scanned_history`.`scan_result` = 0");
            $deActive_scanned_data =  json_decode(json_encode($de_scanned_data),true);

            if(count($deActive_scanned_data) > 0){
                foreach ($deActive_scanned_data as $d_key => $d_value) {
                    $de_scan_by = $deActive_scanned_data[$d_key]['scan_by'];
                    $deactive_verifier_data =  DB::select("SELECT count(`id`) as count_cid from user_table where username='$de_scan_by'");
                    
                    $deactive_ver_scanned_data = json_decode(json_encode($deactive_verifier_data),true);
                    if($deactive_ver_scanned_data[0]['count_cid'] > 0){
                        $deactive_verifier_count+=1;
                    }

                }
                $deActive_scanned=count($deActive_scanned_data);
            }
           
            if($value['status'] == 1 || $value['status'] == '1'){
                $value['status'] = 'Active';
            }else{
                
                $value['status'] = 'Inactive';
            }
            
            // put the value in array with key
            $template_data[$i]['id'] = $i+1;
            $template_data[$i]['template_name'] = $value['template_name'];
            $template_data[$i]['status'] = $value['status'];
            $template_data[$i]['active_count'] = $value[0];
            $template_data[$i]['deactive_count'] = $value[1];
            $template_data[$i]['updated_on'] = $value[2];
            $template_data[$i]['active'] = $active_scanned.'('.$active_verifier_count.')';
            $template_data[$i]['deactive'] = $deActive_scanned.'('.$deactive_verifier_count.')';
            ++$i;
        }       
        $array['data']= $template_data;
        return view('admin.templateData.index',compact('template_data'));
    }
}

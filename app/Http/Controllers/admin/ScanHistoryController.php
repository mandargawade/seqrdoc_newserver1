<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 18/12/2019
 *   Use   : listing of ScanHistory & get Student's info specific key 
 *
**/
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\models\ScannedHistory;
use App\models\StudentTable;
use App\models\SbStudentTable;
use DB;
use Illuminate\Http\Request;
use Auth;
use App\models\SbScannedHistory;
use App\models\SystemConfig;
use App\models\User;
class ScanHistoryController extends Controller
{  
    /**
     * Display a listing of the ScanHistory.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
    */
    public function index(Request $request){
      $auth_site_id=Auth::guard('admin')->user()->site_id;
      $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
      $config = $systemConfig['sandboxing'];
      
       if($request->ajax()){
            
            $where_str    = "1 = ?";
            $where_params = array(1); 


            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                $table_name = 'sb_scanned_history';
            }
            else
            {
                $table_name = 'scanned_history';
            }

            if (!empty($request->input('sSearch')))
            {
                if($request->input('sSearch')=="student"){
                    $whereStatic=" or user_table.fullname !='' ";
                }else if($request->input('sSearch')=="institute admin"){
                    $whereStatic=" or user_table.fullname='' ";
                }else{
                    $whereStatic="";
                }
               
                $search     = $request->input('sSearch');
                $where_str .= " and (".$table_name.".scan_by like \"%{$search}%\""
                 . " or ".$table_name.".scanned_data like \"%{$search}%\""
                 . " or ".$table_name.".date_time like \"%{$search}%\""
                 . " or DATE_FORMAT(".$table_name.".date_time, '%d %b %Y %h:%i %p') like \"%{$search}%\""
                  . " or user_table.fullname like \"%{$search}%\"" .$whereStatic
                . ")";
            }
           
           $device_type=$request->get('device_type');
           
            if($device_type=="webapp")
            {
                $where_str.= " and (".$table_name.".device_type ='webapp' OR ".$table_name.".device_type ='WebApp')";
            }
            else if($device_type=="ios")
            {
                $where_str.=" and (".$table_name.".device_type= 'ios')";
            }
            else if($device_type=="android")
            {
                $where_str.=" and (".$table_name.".device_type= 'android')";
            } 

            $auth_site_id=Auth::guard('admin')->user()->site_id;                                                 
             //for serial number
            DB::statement(DB::raw('set @rownum=0')); 

            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
              $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'sb_scanned_history.date_time','sb_scanned_history.device_type','sb_scanned_history.scanned_data','sb_scanned_history.scan_by','sb_scanned_history.scan_result','user_table.fullname','sb_scanned_history.updated_at'];
              
              $scan_history_count = SbScannedHistory::select($columns)
              ->leftjoin('user_table','sb_scanned_history.scan_by','user_table.id')
              ->whereRaw($where_str, $where_params)
              ->where('sb_scanned_history.site_id',$auth_site_id) 
              ->count();

              $scan_history_list = SbScannedHistory::select($columns)
              ->leftjoin('user_table','sb_scanned_history.scan_by','user_table.id')
              ->where('sb_scanned_history.site_id',$auth_site_id)
              ->whereRaw($where_str, $where_params);
            }
            else
            {
              $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'scanned_history.date_time','scanned_history.device_type','scanned_history.scanned_data','scanned_history.scan_by','scanned_history.scan_result','user_table.fullname','scanned_history.updated_at','scanned_history.user_type','institute_table.username'];
              
              $scan_history_count = ScannedHistory::select($columns)
              ->leftjoin('user_table','scanned_history.scan_by','user_table.id')
              ->leftjoin('institute_table','scanned_history.scan_by','institute_table.id')
              ->whereRaw($where_str, $where_params)
              ->where('scanned_history.site_id',$auth_site_id) 
              ->count();

              $scan_history_list = ScannedHistory::select($columns)
              ->leftjoin('user_table','scanned_history.scan_by','user_table.id')
              ->leftjoin('institute_table','scanned_history.scan_by','institute_table.id')
              ->where('scanned_history.site_id',$auth_site_id)
              ->whereRaw($where_str, $where_params);
            }

            
           
            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $scan_history_list = $scan_history_list->take($request->input('iDisplayLength'))
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
                    $scan_history_list = $scan_history_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            } 
            $scan_history_list = $scan_history_list->get();
             
            $response['iTotalDisplayRecords'] = $scan_history_count;
            $response['iTotalRecords'] = $scan_history_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $scan_history_list->toArray();
            
            return $response;
        }
      return view('admin.scanHistory.index');

    }
    /**
     * get Student's info specific key 
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getData(Request $request){
      
      if(!empty($request['key']))
      {
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
           $student_data=SbStudentTable::where('key',$request['key'])->get()->first();
        }
        else
        {
          $student_data=StudentTable::where('key',$request['key'])->get()->first();
        }
           return $student_data ? $student_data : "null" ;
      }
    }


    public function updateUserIds(Request $request){
      
     //$scan_history_list = ScannedHistory::select(['scan_by']);
        $columns = ['scanned_history.id','scanned_history.scan_by'];
    $scan_history_list =  ScannedHistory::select($columns)
                                                    //->join('user_table','scanned_history.scan_by','user_table.username')
                                                   // ->join('student_table','scanned_history.scanned_data','student_table.key')
                                                   // ->where('scanned_history.scan_result','1')
                                                    ->where('scanned_history.site_id',201)
                                                    //->orderBy('scanned_history.date_time','desc')
                                                    //->take(3)
                                                    ->get()->toArray(); 


         foreach($scan_history_list as $readData ){
            //echo $readData['scan_by']."</br>";

             $student_data=User::where('username',$readData['scan_by'])->get()->first();
             if($student_data){
                //print_r($student_data->id);

              //  ScannedHistory::where('id',$readData['id'])->update(['scan_by'=>$student_data->id]);
                //exit;
             }
             
         }                                           

     //print_r($scan_history_list);
    }
}

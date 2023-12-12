<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 20/12/2019
 *   Use   : StudentSubscribed listing
 *
**/
namespace App\Http\Controllers\webapp;

use App\Http\Controllers\Controller;
use App\models\StudentTable;
use App\models\Transactions;
use DB;
use Faker\Provider\DateTime;
use Illuminate\Http\Request;
class StudentSubscribedController extends Controller
{
	  /**
     * Display a listing of the StudentSubscribed
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request){

         if($request->ajax()){
            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
            
                $search     = $request->input('sSearch');
                $where_str .= " and (transactions.created_at like \"%{$search}%\""
                 . " or student_table.student_name like \"%{$search}%\""
                . ")";
            }  
            $user_id=Auth::guard('webuser')->user()->id;
           
              //for serial number
            DB::statement(DB::raw('set @rownum=0'));   
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'student_table.path','user_table.fullname','transactions.created_at','transactions.student_key','transactions.updated_at'];

            $font_master_count = Transactions::select($columns)
                 ->join('student_table','transactions.student_key','student_table.key')
                 ->join('user_table','transactions.user_id','user_table.id')
                 ->whereRaw($where_str, $where_params)
                 ->where('transactions.trans_status',1)
                 ->where('transactions.publish',1)
                 ->where('student_table.publish',1)
                 ->where('student_table.status',1)
                 ->count();
  
            $fontMaster_list = Transactions::select($columns)
                 ->join('student_table','transactions.student_key','student_table.key')
                 ->join('user_table','transactions.user_id','user_table.id')
                 ->where('transactions.trans_status',1)
                 ->where('transactions.publish',1)
                 ->where('student_table.publish',1)
                 ->where('student_table.status',1)
                 ->whereRaw($where_str, $where_params);
                
      
            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $fontMaster_list = $fontMaster_list->take($request->input('iDisplayLength'))
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
                    $fontMaster_list = $fontMaster_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            }

            $fontMaster_list = $fontMaster_list->get();
            
            $response['iTotalDisplayRecords'] = $font_master_count;
            $response['iTotalRecords'] = $font_master_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $fontMaster_list->toArray();
            
            return $response;
        }
        // view page
    	return view('webapp.student.index');
    }
    /**
     * get Key match records.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getData(Request $request){

      if(!empty($request['key']))
      {

           $student_data=StudentTable::where('key',$request['key'])->get()->first();
           
           return $student_data ? $student_data : "null" ;
      }
    }
}

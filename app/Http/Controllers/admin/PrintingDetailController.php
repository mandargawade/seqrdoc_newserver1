<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 25/11/2019
 *   Use   : listing of Printing Detail & show information specific $id   
 *
**/
namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\models\PrintingDetail;
use Illuminate\Http\Request;
use DB;
use Auth;
use App\models\SystemConfig;
use App\models\SbPrintingDetail;

class PrintingDetailController extends Controller
{
   /**
     * Display a listing of the Printing Detail.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
    */  
    public function index(Request $request)
    {
        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

    	 if($request->ajax()){
            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
               
                $search     = $request->input('sSearch');
                $where_str .= " and (print_serial_no like \"%{$search}%\""
                ." or sr_no like \"%{$search}%\""
                ." or printer_name like \"%{$search}%\""
                ." or print_datetime like \"%{$search}%\""
                ." or username like \"%{$search}%\""
                .")";
            }  
           $status=$request->get('status');


           if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                if($status==1)
                {
                    $status=1;
                }
                else if($status==0 && $status!=null)
                {
                    $status=0;
                }
                $condition = " and (sb_printing_details.status = $status)";
            }
            else
            {   
                if($status==1)
                {
                    $status=1;
                }
                else if($status==0 && $status!=null)
                {
                    $status=0;
                }
                $condition = " and (printing_details.status = '$status')";
            }
           
            
                $where_str.= $condition;
            
            $auth_site_id=Auth::guard('admin')->user()->site_id;                                             
             //for serial number
            DB::statement(DB::raw('set @rownum=0')); 
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'print_serial_no','sr_no','print_count','printer_name','print_datetime','username','id','updated_at'];

            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){  
                $printing_detail_count = SbPrintingDetail::select($columns)
                   ->whereRaw($where_str, $where_params)
                   ->where('publish',1)
                   ->where('site_id',$auth_site_id)
                   ->count();

                $printing_detail_list = SbPrintingDetail::select($columns)
                     ->where('publish',1)
                     ->where('site_id',$auth_site_id)
                     ->whereRaw($where_str, $where_params);
             }
            else
            {
                $printing_detail_count = PrintingDetail::select($columns)
                   ->whereRaw($where_str, $where_params)
                   ->where('publish',1)
                   ->where('site_id',$auth_site_id)
                   ->count();

                $printing_detail_list = PrintingDetail::select($columns)
                     ->where('publish',1)
                     ->where('site_id',$auth_site_id)
                     ->whereRaw($where_str, $where_params);
            }
            

           
            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $printing_detail_list = $printing_detail_list->take($request->input('iDisplayLength'))
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
                    $printing_detail_list = $printing_detail_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            } 
            $printing_detail_list = $printing_detail_list->get();
             
            $response['iTotalDisplayRecords'] = $printing_detail_count;
            $response['iTotalRecords'] = $printing_detail_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $printing_detail_list->toArray();
            
            return $response;
        }
    	return view("admin.printingDetails.index");
    }
     /**
     * Show the form  the specified $id Printing Detail.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getDetail(Request $request)
    {
    	if(!empty($request['print_id']))
    	{
            $auth_site_id=Auth::guard('admin')->user()->site_id;

            $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

    		  $columns = ['print_serial_no','sr_no','print_count','printer_name','print_datetime','username','reprint','status','created_at'];
                if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){  
    		          $print_data=SbPrintingDetail::select($columns)
    		               ->where('id',$request['print_id'])
    		               ->get()
    		               ->toArray();
                }
                else
                {
                    $print_data=PrintingDetail::select($columns)
                           ->where('id',$request['print_id'])
                           ->get()
                           ->toArray();
                }

    		  $print_data=head($print_data);
    		  return  $print_data;       
    	}
    }
}

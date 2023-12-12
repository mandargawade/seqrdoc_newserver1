<?php
/**
 *
 *  Author : Mandar Gawade 
 *   Date  : 09/07/2020
 *   Use   : show and update and get information of Documents Rate Master
 *
**/
namespace App\Http\Controllers\admin\raisoni;

use App\Http\Controllers\Controller;
//use App\Http\Requests\TemplateRateMasterRequest;
use App\models\raisoni\TemplateRateMaster;
use Illuminate\Http\Request;
use DB;
use Auth;
class TemplateRateMasterController extends Controller
{
     /**
     * Display a listing of the TemplateRateMaster Detail.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
        public function index(Request $request)
    {
         if($request->ajax()){
            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
               
                $search     = $request->input('sSearch');
                $where_str .= " and (actual_template_name like \"%{$search}%\" OR scanning_fee like \"%{$search}%\"" 
                . ")";
            }

            $status=$request->get('status');

            if($status==1)
            {
                $status=1;
                $where_str.= " and (template_master.status =$status)";
            }
            else if($status==0)
            {
                $status=0;
                $where_str.=" and (template_master.status= $status)";
            }   
                                                         
              //for serial number
            $auth_site_id=Auth::guard('admin')->user()->site_id;

            DB::statement(DB::raw('set @rownum=0'));   
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'actual_template_name','scanning_fee','updated_at','id'];

            $font_master_count = TemplateRateMaster::select($columns)
                 ->whereRaw($where_str, $where_params)
                 ->count();
  
            $fontMaster_list = TemplateRateMaster::select($columns)
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
        return view('admin.raisoni.paymentGatewayConfig.index');
    }
   

    
    
    /**
     * Update the specified DocumentsRateMaster in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        
        
        $scanning_fee_update=TemplateRateMaster::where('id',$request['id'])->update(['scanning_fee'=>$request['scanning_fee']]);
      
        return response()->json(['success'=>true]);      	      
    }

   
        
  
}

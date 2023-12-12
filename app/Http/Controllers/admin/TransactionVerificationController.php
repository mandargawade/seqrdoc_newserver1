<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 3/1/2020
 *   Use   : listing of Transaction or Failed Transaction
 *
**/
namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\models\Transactions;
use Illuminate\Http\Request;
use DB;
use Auth;
class TransactionVerificationController extends Controller
{
    /**
     * listing of Transaction or Failed Transaction.
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
                $where_str .= " and ( username like \"%{$search}%\""
                . " or transactions.trans_id_gateway like \"%{$search}%\""
                . " or transactions.trans_id_ref like \"%{$search}%\""
                . " or transactions.payment_mode like \"%{$search}%\""
                . " or transactions.amount like \"%{$search}%\""
                . " or transactions.additional like \"%{$search}%\""
                . " or user_table.username like \"%{$search}%\""
                . " or transactions.created_at like \"%{$search}%\""
                . ")";
            }  
           $status=$request->get('status');
            if($status==1)
            {
                $status='1';
                $where_str.= " and (transactions.trans_status =$status and student_key LIKE '%OCV-R-%' OR student_key LIKE 'NQRCV-R-')";
            }
            else if($status==0)
            {
                $status='0';
                $where_str.=" and (transactions.trans_status= $status and  student_key LIKE '%OCV-R-%' OR student_key LIKE 'NQRCV-R-')";
            }                                               
             
            $auth_site_id=Auth::guard('admin')->user()->site_id; 
            //for serial number
            //DB::statement(DB::raw('set @rownum=0')); 
             //$columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'transactions.trans_id_ref','transactions.trans_id_gateway','transactions.payment_mode','transactions.amount','transactions.additional','user_table.username','transactions.created_at','transactions.updated_at','transactions.trans_status','transactions.student_key'];
             $columns = ['transactions.sr_no','transactions.trans_id_ref','transactions.trans_id_gateway','transactions.payment_mode','transactions.amount','transactions.additional','user_table.username','transactions.created_at','transactions.updated_at','transactions.trans_status','transactions.student_key'];

            $font_master_count = Transactions::select($columns)
            ->leftjoin('user_table','transactions.user_id','user_table.id')
            ->whereRaw($where_str, $where_params)
			//->where('transactions.site_id',$auth_site_id)
            ->count();
  
            $fontMaster_list = Transactions::select($columns)
            ->leftjoin('user_table','transactions.user_id','user_table.id')			
            ->whereRaw($where_str, $where_params)
			//->where('transactions.site_id',$auth_site_id)
			;
      
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
        return view('admin.transaction.VerificationIndex');
    }
}

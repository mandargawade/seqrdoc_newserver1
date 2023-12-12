<?php

namespace App\Http\Controllers\admin\raisoni;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\raisoni\DamagedStock;
use App\models\raisoni\DegreeMaster;
use App\models\raisoni\SessionsMaster;
use App\models\raisoni\BranchMaster;
use App\models\raisoni\SemesterMaster;
use App\models\raisoni\StationaryStock;
use App\Http\Requests\DamagedStockRequest;
use App\Exports\DamagedStockExport;
use DB,Excel;

class DamagedStockController extends Controller
{
    public function index(Request $request)
    {

		if($request->ajax()){

		    $where_str    = "1 = ?";
		    $where_params = array(1); 

		    if (!empty($request->input('sSearch')))
		    {
		        $search     = $request->input('sSearch');
		        $where_str .= " and ( serial_no like \"%{$search}%\""
		        . " or type like \"%{$search}%\""
		        . " or remark like \"%{$search}%\""
		        . " or registration_no like \"%{$search}%\""
		        . " or created_at like \"%{$search}%\""
		        . " or card_category like \"%{$search}%\""
		        . " or added_by like \"%{$search}%\""
		        . ")";
		    }  

		    
		    if (!empty($request->get('fromDate')) && !empty($request->get('toDate'))){
				$fromDate = date('Y-m-d', strtotime($request->get('fromDate')));
				$toDate = date('Y-m-d', strtotime($request->get('toDate')));
				$range_condition = " AND (DATE(created_at) >= '$fromDate' && DATE(created_at) <= '$toDate')";
           		$where_str .= $range_condition;
		     //for serial number
		    }
		    if ($request->get('card_category_filter') != 'All'){
		    	$card_category = $request->get('card_category_filter');
		    	$where_str .= " AND (card_category = '$card_category')";
		    }
		    if ($request->get('type_damaged_filter') != 'All'){
		    	$type_damaged_filter = $request->get('type_damaged_filter');
		    	$where_str .= " AND (type = '$type_damaged_filter')";
		    }
            DB::statement(DB::raw('set @rownum=0'));   
		    $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'serial_no','type','remark','registration_no','created_at','card_category','added_by','id'];

		    $damaged_stock_count = DamagedStock::select($columns)
		         ->whereRaw($where_str, $where_params)
		         ->count();


		    $damaged_stock_list = DamagedStock::select($columns)
		          ->whereRaw($where_str, $where_params);

		    if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
		        $damaged_stock_list = $damaged_stock_list->take($request->input('iDisplayLength'))
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
		            $damaged_stock_list = $damaged_stock_list->orderBy($column,$request->input('sSortDir_'.$i));   
		        }
		    } 
		    $damaged_stock_list = $damaged_stock_list->get();
		     
		    $response['iTotalDisplayRecords'] = $damaged_stock_count;
		    $response['iTotalRecords'] = $damaged_stock_count;+
		    $response['sEcho'] = intval($request->input('sEcho'));
		    $response['aaData'] = $damaged_stock_list->toArray();
		    
		    return $response;
		}
		return view('admin.raisoni.damagedstock.index');
    }

    public function getExamName(Request $request)
    {
        $exam_name = SessionsMaster::select('session_no','session_name')
								->orderBy('session_no','desc')
								->where('is_active',1)
								->get()
								->toArray();     
        return $exam_name;          
    }

    public function getDegreeName(Request $request)
    {
        $degree_name=DegreeMaster::select('id','degree_name')
								->where('is_active',1)
								->orderBy('degree_name','asc')
								->get()
								->toArray();
          
        return $degree_name;          
    }

    public function getBranchName(Request $request)
    {
        $branch_name=BranchMaster::select('id','branch_name_long')
								->where('degree_id',$request['degree_id'])
								->where('is_active',1)
								->orderBy('branch_name_long','asc')
								->get()
								->toArray();
          
        return $branch_name;          
    }

    public function getSemesterName(Request $request)
    {
        $semester_name=SemesterMaster::select('id','semester_name')
								->where('is_active',1)
								->get()
								->toArray();
          
        return $semester_name;          
    }

    public function store(DamagedStockRequest $request)
    {
        $damaged_stock_data = $request->all();
        $condition    = "1 = ?";
        $where_params = array(1);

        $card_category = $damaged_stock_data['card_category'];
        $serial_no = $damaged_stock_data['serial_no'];

        $condition = " (card_category = '$card_category')";
        $condition .= " AND (CONVERT('$serial_no', UNSIGNED) BETWEEN serial_no_from AND serial_no_to)";
        $stock_count = StationaryStock::select('id')->whereRaw($condition, $where_params)->count();
        if($stock_count > 0){
        	$damaged_stock_count = DamagedStock::select('id')->where('serial_no', $serial_no)->where('card_category', $card_category)->count();
        	if($damaged_stock_count == 0){
        		$user_data = \auth::guard('admin')->user()->id;
		        unset($damaged_stock_data['_token']);
		        $damaged_stock_data_save = new DamagedStock();
		        $damaged_stock_data['type'] = $damaged_stock_data['type_damaged'];
		        $damaged_stock_data['registration_no'] = $damaged_stock_data['reg_no'];
		        $damaged_stock_data['added_by'] = $user_data;
		        $damaged_stock_data_save->fill($damaged_stock_data);
		        $damaged_stock_data_save->save();
	        	return response()->json(['success'=>true,'message'=>'Serial number successfully marked as ' . $damaged_stock_data['type_damaged'] . '.']);
	        }
	        else{
		    	return response()->json(['success'=>false,'message'=>'This serial number is already marked as ' . $damaged_stock_data['type_damaged'] . '.']);
		    }
	    }
	    else{
	    	return response()->json(['success'=>false,'message'=>'This serial number is not found in our system.']);
	    }
    }


     public function show($id)
    {
        $damaged_stock_data = DamagedStock::select('exam_master.session_name','degree_master.degree_name','branch_master.branch_name_long','semester_master.semester_name')->leftjoin('exam_master','damaged_stationary.exam','=','exam_master.session_no')->leftjoin('degree_master','damaged_stationary.degree','=','degree_master.id')->leftjoin('branch_master','damaged_stationary.branch','=','branch_master.id')->leftjoin('semester_master','damaged_stationary.semester','=','semester_master.id')->where('damaged_stationary.id',$id)->get()->toArray();
         
        return $damaged_stock_data;
    }

    public function destroy($id)
    {
    
        $damaged_stock_data = DamagedStock::where('id',$id)->delete();
        return $damaged_stock_data ? response()->json(['success'=>true]) :'false';
    }

    public function excelreport(Request $request){
    	$data = $request->all();
    	$type_damaged_filter = $data['type_damaged_filter'];
    	$card_category = $data['card_category_filter'];
        $serial_no_from = $data['fromDate'];
        $serial_no_to = $data['toDate'];
        $sheet_name = 'Grade Card Damaged Report_'. date('Y_m_d_H_i_s').'.xls'; 
        
        return Excel::download(new DamagedStockExport($type_damaged_filter,$card_category,$serial_no_from,$serial_no_to),$sheet_name);             
    }
}

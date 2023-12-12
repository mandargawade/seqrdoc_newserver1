<?php

namespace App\Http\Controllers\admin\raisoni;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\raisoni\StationaryStock;
use App\Http\Requests\StationaryStockRequest;
use DB,Excel;
use App\Exports\StationaryStockExport;

class StationaryStockController extends Controller
{
    public function index(Request $request)
    {
		if($request->ajax()){

		    $where_str    = "1 = ?";
		    $where_params = array(1); 

		    if (!empty($request->input('sSearch')))
		    {
		        $search     = $request->input('sSearch');
		        $where_str .= " and ( card_category like \"%{$search}%\""
		        . " or academic_year like \"%{$search}%\""
		        . " or date_of_received like \"%{$search}%\""
		        . " or serial_no_from like \"%{$search}%\""
		        . " or serial_no_to like \"%{$search}%\""
		        . " or quantity like \"%{$search}%\""
		        . " or added_by like \"%{$search}%\""
		        . " or created_at like \"%{$search}%\""
		        . ")";
		    }  

		    
		    if (!empty($request->get('fromDate')) && !empty($request->get('toDate'))){
				$fromDate = date('Y-m-d', strtotime($request->get('fromDate')));
				$toDate = date('Y-m-d', strtotime($request->get('toDate')));
				$range_condition = " AND (DATE(date_of_received) >= '$fromDate' && DATE(date_of_received) <= '$toDate')";
           		$where_str .= $range_condition;
		    	
		     //for serial number
		    }
		    if ($request->get('card_category_filter') != 'All'){
		    	$card_category = $request->get('card_category_filter');
		    	$where_str .= " AND (card_category = '$card_category')";
		    }
            DB::statement(DB::raw('set @rownum=0'));   
		    $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'academic_year','date_of_received','serial_no_from','serial_no_to','quantity','card_category','added_by','created_at'];

		    $stationary_stock_count = StationaryStock::select($columns)
		         ->whereRaw($where_str, $where_params)
		         ->count();

		    $stationary_stock_list = StationaryStock::select($columns)
		          ->whereRaw($where_str, $where_params);

		    if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
		        $stationary_stock_list = $stationary_stock_list->take($request->input('iDisplayLength'))
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
		            $stationary_stock_list = $stationary_stock_list->orderBy($column,$request->input('sSortDir_'.$i));   
		        }
		    } 
		    $stationary_stock_list = $stationary_stock_list->get();
		     
		    $response['iTotalDisplayRecords'] = $stationary_stock_count;
		    $response['iTotalRecords'] = $stationary_stock_count;
		    $response['sEcho'] = intval($request->input('sEcho'));
		    $response['aaData'] = $stationary_stock_list->toArray();
		    
		    return $response;
		}
		return view('admin.raisoni.stationarystock.index');
    }


    public function store(StationaryStockRequest $request)
    {
        $stock_data = $request->all();
        $condition    = "1 = ?";
        $where_params = array(1);

        $card_category = $stock_data['card_category'];
        $serial_no_from = $stock_data['serial_no_from'];
        $serial_no_to = $stock_data['serial_no_to'];

        $condition = " (card_category = '$card_category')";
        $condition .= " AND ((CONVERT('$serial_no_from', UNSIGNED) BETWEEN serial_no_from AND serial_no_to) OR (CONVERT('$serial_no_to', UNSIGNED) BETWEEN serial_no_from AND serial_no_to))";
        $stock_count = StationaryStock::select('id')->whereRaw($condition, $where_params)->count();
        
        if($stock_count == 0){
	        unset($stock_data['stock_id']);
	        unset($stock_data['_token']);
	        $stock_data_save = new StationaryStock();
	        $stock_data['date_of_received'] = date('Y-m-d',strtotime($stock_data['date_of_received']));
	        $stock_data_save->fill($stock_data);
	        $stock_data_save->save();
	        return response()->json(['success'=>true]);
	    }
	    else{
	    	return response()->json(['success'=>false,'message'=>'Stationary with this serial number range already exists.']);
	    }
    }

    public function excelreport(Request $request){
    	$data = $request->all();
    	$card_category = $data['card_category_filter'];
        $serial_no_from = $data['fromDate'];
        $serial_no_to = $data['toDate'];
        $sheet_name = 'Grade Report_'. date('Y_m_d_H_i_s').'.xls'; 
        
        return Excel::download(new StationaryStockExport($card_category,$serial_no_from,$serial_no_to),$sheet_name);             
    }
}

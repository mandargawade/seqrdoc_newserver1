<?php

namespace App\Http\Controllers\admin\raisoni;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\raisoni\ConsumptionReport;
use App\models\raisoni\SectionMaster;
use App\models\raisoni\DamagedStock;
use App\models\raisoni\StationaryStock;
use DB;

class ConsumptionReportController extends Controller
{
    public function index(Request $request)
    {
		if($request->ajax()){

		    $where_str    = "1 = ?";
		    $where_params = array(1); 

		    if (!empty($request->input('sSearch')))
		    {
		        $search     = $request->input('sSearch');
		        $where_str .= " and ( roll_no like \"%{$search}%\""
		        . " or section like \"%{$search}%\""
		        . " or result_no like \"%{$search}%\""
		        . " or enrollment_no like \"%{$search}%\""
		        . " or registration_no like \"%{$search}%\""
		        . " or student_name like \"%{$search}%\""
		        . " or serial_no like \"%{$search}%\""
		        . ")";
		    }  

		    if (!empty($request->get('exam'))){
		    	$examination = $request->get('exam');
		    	$where_str .= " AND (examination = '$examination')";
		    }
		    if (!empty($request->get('degree'))){
		    	$programme = $request->get('degree');
		    	$where_str .= " AND (programme = '$programme')";
		    }
		    if (!empty($request->get('branch'))){
		    	$department = $request->get('branch');
		    	$where_str .= " AND (department = '$department')";
		    }
		    if (!empty($request->get('scheme'))){
		    	$scheme = $request->get('scheme');
		    	$where_str .= " AND (scheme = '$scheme')";
		    }
		    if (!empty($request->get('term'))){
		    	$term = $request->get('term');
		    	$where_str .= " AND (term = '$term')";
		    }
		    if (!empty($request->get('student_type'))){
		    	$student_type = $request->get('student_type');
		    	$where_str .= " AND (student_type = '$student_type')";
		    }
		    if (!empty($request->get('section'))){
		    	$section = $request->get('section');
		    	$where_str .= " AND (section = '$section')";
		    }
		    if (!empty($request->get('card_type'))){
		    	$card_type = $request->get('card_type');
		    	if ($card_type == "Assigned") {
					$where_str .= " AND (serial_no !='')";
				} else if ($card_type == "Non Assigned") {
					$where_str .= " AND (serial_no ='')";
				}
		    }
		    if (empty($request->get('exam')) && empty($request->get('degree')) && empty($request->get('branch')) && empty($request->get('scheme')) && empty($request->get('term')) && empty($request->get('student_type')) && empty($request->get('section')) && empty($request->get('card_type'))){
		    	$where_str .= " AND (id =0)";
		    }
            DB::statement(DB::raw('set @rownum=0'));   
		    $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'roll_no','section','result_no','enrollment_no','registration_no','student_name','serial_no','id'];

		    $consumption_report_count = ConsumptionReport::select($columns)
		         ->whereRaw($where_str, $where_params)
		         ->count();


		    $consumption_report_list = ConsumptionReport::select($columns)
		          ->whereRaw($where_str, $where_params);

		    if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
		        $consumption_report_list = $consumption_report_list->take($request->input('iDisplayLength'))
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
		            $consumption_report_list = $consumption_report_list->orderBy($column,$request->input('sSortDir_'.$i));   
		        }
		    } 
		    $consumption_report_list = $consumption_report_list->get();
		     
		    $response['iTotalDisplayRecords'] = $consumption_report_count;
		    $response['iTotalRecords'] = $consumption_report_count;+
		    $response['sEcho'] = intval($request->input('sEcho'));
		    $response['aaData'] = $consumption_report_list->toArray();
		    
		    return $response;
		}
		return view('admin.raisoni.consumptionreport.index');
    }

    public function getSectionName(Request $request)
    {
        $section_name = SectionMaster::select('id','section_name')
								->orderBy('section_name','asc')
								->where('is_active',1)
								->get()
								->toArray();     
        return $section_name;          
    }

    public function getSchemeName(Request $request)
    {
        $scheme_name = ConsumptionReport::select('scheme')
								->groupBy('scheme')
								->orderBy('scheme','asc')
								->get()
								->toArray();     
        return $scheme_name;          
    }

    public function getStudentType(Request $request)
    {
        $student_type = ConsumptionReport::select('student_type')
								->groupBy('student_type')
								->orderBy('student_type','asc')
								->get()
								->toArray();     
        return $student_type;          
    }


    public function updateSerialNo(Request $request)
    {
        $recoredIdArr = '';
        $serialNoArr = '';

        $condition    = "1 = ?";
        $where_params = array(1);


        if(isset($request['recoredId'])){
        	$recoredIdArr = $request['recoredId'];
        }
        if(isset($request['serialNo'])){
        	$serialNoArr = $request['serialNo'];
        }  

        if (!empty($recoredIdArr) && !empty($serialNoArr)) {
        	$totalRecords = count($serialNoArr);

			$check_post = array_filter($serialNoArr, function($val){
											    return trim($val) !== '';
											});
			if (count(array_unique($check_post)) == count($check_post)) {
				$error = false;
				$last_updated_by = \auth::guard('admin')->user()->id;
				$updated_date_time = date('Y-m-d H:i:s');

				for ($i = 0; $i < $totalRecords; $i++) {
					$serialNo = $serialNoArr[$i];
					$condition = " (card_category='Grade Cards')";
			        $condition .= " AND (CONVERT('$serialNo', UNSIGNED) BETWEEN serial_no_from AND serial_no_to)";
			        $stock_count = StationaryStock::select('id')->whereRaw($condition, $where_params)->count();

			        if($stock_count > 0){
			        	if (!empty($serialNoArr[$i])) {
			        		$damaged_stock_count = DamagedStock::select('id','type')->where('serial_no',$serialNoArr[$i])->count();
			        		$damaged_stock = DamagedStock::select('id','type')->where('serial_no',$serialNoArr[$i])->first();
						} else {
							$damaged_stock_count = 0;
						}

						if ($damaged_stock_count == 0) {
							if (!empty($serialNoArr[$i])) {
				        		$grade_card_count = ConsumptionReport::select('id')->where('serial_no',$serialNoArr[$i])->where('id',$recoredIdArr[$i])->count();
							} else {
								$grade_card_count = 0;
							}

							if ($damaged_stock_count == 0) {
								ConsumptionReport::where('id',$recoredIdArr[$i])->update(['serial_no'=>$serialNoArr[$i],'updated_by'=>$last_updated_by,'updated_at'=>$updated_date_time]);
							}
							else {
								$messageError = "Serial number " . $serialNoArr[$i] . " already exist";
								$error = true;
								break;
							}
						}
						else {
							$messageError = "Serial number " . $serialNoArr[$i] . " already marked as " . $damaged_stock->type;
							$error = true;
							break;
						}
			        }
			        else {
						$messageError = "This serial number not found in our stock.";
						$error = true;
					}
				}

				if (!$error) {
					$message = array('type' => 'success', 'message' => 'Serial numbers updated successfully.');
				} else {
					$message = array('type' => 'error', 'message' => $messageError);
				}
			}
			else {
				$message = array('type' => 'error', 'message' => 'You have entered duplicate serial numbers.');
			}
        }
        else {
			$message = array('type' => 'error', 'message' => 'Required fields not found.');
		}
		return response()->json($message);
    }
}

<?php

namespace App\Http\Controllers\admin\raisoni;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\raisoni\SemesterMaster;
use DB;
use App\Http\Requests\SemesterRequest;

class SemesterController extends Controller
{
    public function index(Request $request)
    {
		if($request->ajax()){

		    $where_str    = "1 = ?";
		    $where_params = array(1); 

		    if (!empty($request->input('sSearch')))
		    {
		        $search     = $request->input('sSearch');
		        $where_str .= " and ( semester_name like \"%{$search}%\""
		        . " or semester_full_name like \"%{$search}%\""
		        . " or created_at like \"%{$search}%\""
		        . " or updated_at like \"%{$search}%\""
		        . ")";
		    }  
		     //for serial number
            DB::statement(DB::raw('set @rownum=0'));   
		    $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'semester_name','semester_full_name','created_at','updated_at','id'];

		    $semester_master_count = SemesterMaster::select($columns)
		         ->where('is_active', 1)
		         ->whereRaw($where_str, $where_params)
		         ->count();

		    $semester_master_list = SemesterMaster::select($columns)
		         ->where('is_active', 1)
		          ->whereRaw($where_str, $where_params);

		    if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
		        $semester_master_list = $semester_master_list->take($request->input('iDisplayLength'))
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
		            $semester_master_list = $semester_master_list->orderBy($column,$request->input('sSortDir_'.$i));   
		        }
		    } 
		    $semester_master_list = $semester_master_list->get();
		     
		    $response['iTotalDisplayRecords'] = $semester_master_count;
		    $response['iTotalRecords'] = $semester_master_count;
		    $response['sEcho'] = intval($request->input('sEcho'));
		    $response['aaData'] = $semester_master_list->toArray();
		    
		    return $response;
		}
		return view('admin.raisoni.semester.index');
    }

    public function store(SemesterRequest $request)
    {
        $semester_data = $request->all();
        unset($semester_data['semester_id']);
        unset($semester_data['_token']);
        $semester_data['is_active'] = 1;
        $semester_data_save = new SemesterMaster();
        $semester_data_save->fill($semester_data);
        $semester_data_save->save();
        return response()->json(['success'=>true]);
    }

    public function edit($id)
    {
        $semester_data=SemesterMaster::where('id',$id)->get()->toArray();
        $semester_data=head($semester_data);
        return $semester_data;
    }

    public function update(SemesterRequest $request, $id)
    {
        $semester_data=$request->all();
        unset($semester_data['_token']);
        SemesterMaster::where('id',$semester_data['semester_id'])->update(['semester_name'=>$semester_data['semester_name'],'semester_full_name'=>$semester_data['semester_full_name']]);
        return response()->json(['success'=>true]);
    }
}

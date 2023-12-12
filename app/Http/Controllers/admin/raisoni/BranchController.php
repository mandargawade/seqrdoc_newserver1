<?php

namespace App\Http\Controllers\admin\raisoni;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\raisoni\BranchMaster;
use App\models\raisoni\DegreeMaster;
use DB;
use App\Http\Requests\BranchRequest;

class BranchController extends Controller
{
    public function index(Request $request)
    {
		if($request->ajax()){

		    $where_str    = "1 = ?";
		    $where_params = array(1); 

		    if (!empty($request->input('sSearch')))
		    {
		        $search     = $request->input('sSearch');
		        $where_str .= " and ( branch_master.branch_name_long like \"%{$search}%\""
		        . " or branch_master.branch_name_short like \"%{$search}%\""
		        . " or degree_master.degree_name like \"%{$search}%\""
		        . " or branch_master.created_at like \"%{$search}%\""
		        . " or branch_master.updated_at like \"%{$search}%\""
		        . ")";
		    }  
		     //for serial number
            DB::statement(DB::raw('set @rownum=0'));   
		    $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'branch_master.branch_name_long','branch_master.branch_name_short','degree_master.degree_name','branch_master.created_at','branch_master.updated_at','branch_master.id'];

		    $degree_master_count = BranchMaster::select($columns)
		    		->leftjoin('degree_master','branch_master.degree_id','=','degree_master.id')
		         ->where('branch_master.is_active', 1)
		         ->whereRaw($where_str, $where_params)
		         ->count();

		    $degree_master_list = BranchMaster::select($columns)
		    		->leftjoin('degree_master','branch_master.degree_id','=','degree_master.id')
		         ->where('branch_master.is_active', 1)
		          ->whereRaw($where_str, $where_params);

		    if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
		        $degree_master_list = $degree_master_list->take($request->input('iDisplayLength'))
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
		            $degree_master_list = $degree_master_list->orderBy($column,$request->input('sSortDir_'.$i));   
		        }
		    } 
		    $degree_master_list = $degree_master_list->get();
		     
		    $response['iTotalDisplayRecords'] = $degree_master_count;
		    $response['iTotalRecords'] = $degree_master_count;
		    $response['sEcho'] = intval($request->input('sEcho'));
		    $response['aaData'] = $degree_master_list->toArray();
		    
		    return $response;
		}
		return view('admin.raisoni.branch.index');
    }

    public function getDegreeName(Request $request)
    {
          $degree_name=DegreeMaster::select('id','degree_name')
									->where('is_active',1)
									->get()
									->toArray();
          
          return $degree_name;          
    }

    public function store(BranchRequest $request)
    {
        $branch_data = $request->all();
        unset($branch_data['branch_id']);
        unset($branch_data['_token']);
        $branch_data['is_active'] = 1;
        $branch_data_save = new BranchMaster();
        $branch_data_save->fill($branch_data);
       // print_r($branch_data_save);
        $branch_data_save->save();
        return response()->json(['success'=>true]);
    }

    public function edit($id)
    {
        $branch_data=BranchMaster::where('id',$id)->get()->toArray();
        $branch_data=head($branch_data);
        return $branch_data;
    }

    public function update(BranchRequest $request, $id)
    {
        $branch_data=$request->all();
        unset($branch_data['_token']);
        BranchMaster::where('id',$branch_data['branch_id'])->update(['degree_id'=>$branch_data['degree_id'],'branch_name_long'=>$branch_data['branch_name_long'],'branch_name_short'=>$branch_data['branch_name_short']]);
        return response()->json(['success'=>true]);
    }

}

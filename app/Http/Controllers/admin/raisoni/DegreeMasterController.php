<?php
/**
 *
 *  Author : Mandar Gawade
 *   Date  : 08/07/2020
 *   Use   : listing, store and update & delete Sessions Master  
 *
**/

namespace App\Http\Controllers\admin\raisoni;


use App\Http\Controllers\Controller;
use App\Http\Requests\DegreeMasterRequest;
use App\models\raisoni\DegreeMaster;
use Illuminate\Http\Request;
use DB;
use Auth;
class DegreeMasterController extends Controller
{
    /**
     * Display a listing of the Degrees.
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
                $where_str .= " and (degree_name like \"%{$search}%\""
                . ")";
            }  
                                                         
              //for serial number
            $auth_site_id=Auth::guard('admin')->user()->site_id;

            DB::statement(DB::raw('set @rownum=0'));   
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'degree_name','created_at','updated_at','id'];

            $font_master_count = DegreeMaster::select($columns)
                 ->whereRaw($where_str, $where_params)
                 ->where('is_active',1)
                 ->count();
  
            $fontMaster_list = DegreeMaster::select($columns)
                ->where('is_active',1)
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
        return view('admin.raisoni.degreemaster.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created Degree in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(DegreeMasterRequest $request)
    {
        $degree_data=$request->all();
        $degree_data['is_active']=1;
        unset($degree_data['_token']);
        unset($degree_data['degree_id']);
        $degree_data_save= new DegreeMaster();
        $degree_data_save->fill($degree_data);
        $degree_data_save->save();
        return response()->json(['success'=>true]);

    }

    /**
     * Display the specified Degree.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified Degree.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $degree_data=DegreeMaster::select('degree_name','id')
                 ->where('id',$id)
                 ->get()->toArray();
        $degree_data=head($degree_data);

        return $degree_data;
    }

    /**
     * Update the specified Degree in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(DegreeMasterRequest $request, $id)
    {
       $degree_data=$request->all();
        unset($degree_data['_token']);
        DegreeMaster::where('id',$degree_data['degree_id'])->update(['degree_name'=>$degree_data['degree_name']]);
       return response()->json(['success'=>true]);   
    }
   
}

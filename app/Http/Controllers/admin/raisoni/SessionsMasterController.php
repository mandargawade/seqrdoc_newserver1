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
use App\Http\Requests\SessionsMasterRequest;
use App\models\raisoni\SessionsMaster;
use Illuminate\Http\Request;
use DB;
use Auth;
class SessionsMasterController extends Controller
{
    /**
     * Display a listing of the Sessions.
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
                $where_str .= " and (session_name like \"%{$search}%\""
                . ")";
            }  
                                                         
              //for serial number
            $auth_site_id=Auth::guard('admin')->user()->site_id;

            DB::statement(DB::raw('set @rownum=0'));   
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'session_no','session_name','created_at','updated_at'];

            $font_master_count = SessionsMaster::select($columns)
                 ->whereRaw($where_str, $where_params)
                 ->where('is_active',1)
                 ->count();
  
            $fontMaster_list = SessionsMaster::select($columns)
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
        return view('admin.raisoni.sessionsmaster.index');
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
     * Store a newly created Session in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(SessionsMasterRequest $request)
    {
        $session_data=$request->all();
        $session_data['is_active']=1;
        unset($session_data['_token']);
        unset($session_data['session_no']);
        $session_data_save= new SessionsMaster();
        $session_data_save->fill($session_data);
        $session_data_save->save();
        return response()->json(['success'=>true]);

    }

    /**
     * Display the specified PaymentGateway.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified Session.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $session_data=SessionsMaster::select('session_name','session_no')
                 ->where('session_no',$id)
                 ->get()->toArray();
        $session_data=head($session_data);

        return $session_data;
    }

    /**
     * Update the specified Session in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(SessionsMasterRequest $request, $id)
    {
       $session_data=$request->all();
        unset($session_data['_token']);
        SessionsMaster::where('session_no',$session_data['session_no'])->update(['session_name'=>$session_data['session_name']]);
       return response()->json(['success'=>true]);   
    }
   
}

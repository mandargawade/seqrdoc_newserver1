<?php

namespace App\Http\Controllers\superadmin;

use App\Events\WebsiteDetailEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\WebsiteDetailRequest;
use App\models\WebsiteDetail;
use DB;
use Illuminate\Http\Request;
class WebsiteDetailController extends Controller
{
    /**
     * Display a listing of the resource.
     *
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
                $where_str .= " and ( website_url like \"%{$search}%\""
                . " or db_name like \"%{$search}%\""
                . " or db_host_address like \"%{$search}%\""
                . " or username like \"%{$search}%\""
                . " or password like \"%{$search}%\""
                . ")";
            }  
           $status=$request->get('status');
            if($status==1)
            {
                $status=1;
                $where_str.= " and (website_details.status =$status)";
            }
            else if($status==0)
            {
                
                $where_str.=" and (website_details.status='0')";
            }                                               
              //for serial number
            DB::statement(DB::raw('set @rownum=0'));   
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'website_url','db_name','db_host_address','username','password','id','updated_at','status'];

            $font_master_count = WebsiteDetail::select($columns)
                 ->whereRaw($where_str, $where_params)
                 ->count();
  
            $fontMaster_list = WebsiteDetail::select($columns)
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
        return view('superadmin.websitedetail.index');
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
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(WebsiteDetailRequest $request)
    {  

        $websiteData=$request->all();
        event(new WebsiteDetailEvent($websiteData));
        return response()->json(['success'=>true,'msg'=>'added successfully']);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(WebsiteDetailRequest $request, $id)
    {
       $websitedetail=$request->all();
       $websitedetail['user_id']=$id;
       event(new WebsiteDetailEvent($websitedetail));

       return response()->json(['success'=>true,'msg'=>'updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if(!empty($id))
        {
            $status=WebsiteDetail::where('id',$id)->delete();

            return $status ? response()->json(['success'=>true]) : "false";
        }
    }
}

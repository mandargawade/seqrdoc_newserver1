<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 16/11/2019
 *   Use   : listing of PaymentGateway & store and update & delete PaymentGateway  
 *
**/

namespace App\Http\Controllers\admin;

use App\Events\PaymentGatewayEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentGatewayRequest;
use App\models\PaymentGateway;
use Illuminate\Http\Request;
use App\models\paymentGatewayConfig;
use DB;
use Auth;
class PaymentGatewayController extends Controller
{
    /**
     * Display a listing of the PaymentGateway.
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
                $where_str .= " and (pg_name like \"%{$search}%\""
                . ")";
            }  
           $status=$request->get('status');
            if($status==1)
            {
                $status='1';
                $where_str.= " and (payment_gateway.status =$status)";
            }
            else if($status==0)
            {
                $status='0';
                $where_str.=" and (payment_gateway.status= $status)";
            }                                               
              //for serial number
            $auth_site_id=Auth::guard('admin')->user()->site_id;

            DB::statement(DB::raw('set @rownum=0'));   
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'pg_name','id','updated_at'];

            $font_master_count = PaymentGateway::select($columns)
                 ->whereRaw($where_str, $where_params)
                 ->where('site_id',$auth_site_id)
                 ->where('publish',1)
                 ->count();
  
            $fontMaster_list = PaymentGateway::select($columns)
                ->where('publish',1)
                ->where('site_id',$auth_site_id)
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
        return view('admin.paymentGateway.index');
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
     * Store a newly created PaymentGateway in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(PaymentGatewayRequest $request)
    {
        $pg_data=$request->all();
        event(new PaymentGatewayEvent($pg_data));
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
     * Show the form for editing the specified PaymentGateway.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $pg_data=PaymentGateway::select('pg_name','status','id')
                 ->where('id',$id)
                 ->get()->toArray();
        $pg_data=head($pg_data);

        return $pg_data;
    }

    /**
     * Update the specified PaymentGateway in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(PaymentGatewayRequest $request, $id)
    {
       $pg_data=$request->all();
      // print_r($pg_data);
       event(new PaymentGatewayEvent($pg_data));
       return response()->json(['success'=>true]);   
    }
    /**
     * Remove the specified PaymentGateway from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
       $result=PaymentGateway::where('id',$id)->delete();
       $pgc=paymentGatewayConfig::where('pg_id',$id)->delete();
       return  $result ? response()->json(['success'=>true]) :"false"; 
    }
}

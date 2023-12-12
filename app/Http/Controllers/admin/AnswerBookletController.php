<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 16/11/2019
 *   Use   : listing of Answerbooklet
 *
**/

namespace App\Http\Controllers\admin;
use App\Exports\AnswerBookletExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnswerBookletRequest;
use App\models\AnswerBookletBatches;
use App\models\AnswerBookletData;
use DB;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AnswerBookletController extends Controller
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
                $where_str .= " and ( id like \"%{$search}%\" OR prefix like \"%{$search}%\" OR booklet_size like \"%{$search}%\" OR start_serial_no like \"%{$search}%\" OR end_serial_no like \"%{$search}%\" OR quantity like \"%{$search}%\" OR created_at like \"%{$search}%\""

                . ")";
            }  
        //    $status=$request->get('status');
        //     if($status==1)
        //     {
        //         $status='1';
        //         $where_str.= " and (payment_gateway.status =$status)";
        //     }
        //     else if($status==0)
        //     {
        //         $status='0';
        //         $where_str.=" and (payment_gateway.status= $status)";
        //     }                                               
              //for serial number
           // $auth_site_id=Auth::guard('admin')->user()->site_id;

            DB::statement(DB::raw('set @rownum=0'));   
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'id','prefix','booklet_size','start_serial_no','end_serial_no','quantity','created_at'];

            $font_master_count = AnswerBookletBatches::select($columns)
                 ->whereRaw($where_str, $where_params)
                 ->count();
  
            $fontMaster_list = AnswerBookletBatches::select($columns)
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
        return view('admin.answerbooklet.index');
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
    public function store(AnswerBookletRequest $request)
    {
        $answerbookletData=$request->all();
      //  print_r($answerbookletData);
        if(!empty($answerbookletData['prefix_word'])&&!empty($answerbookletData['booklet_size'])&&!empty($answerbookletData['start_serial_no'])&&!empty($answerbookletData['quantity'])){
            
            $prefix=strtoupper($answerbookletData['prefix_word']);
            $bookletSize=$answerbookletData['booklet_size'];
            $quantity=$answerbookletData['quantity'];
            $startSerialNo=$answerbookletData['start_serial_no'];
            //Calculate quantity of single booklet // minus 2 pages means first and first back page of answerbook 
            $quantityOfSingleBooklet=(int)$bookletSize-2;

            $allBooksQuantity=(int)$quantityOfSingleBooklet * (int)$quantity;
            if($allBooksQuantity==1){
                $endSerialNo=(int)$startSerialNo;
            }else{
                //$endSerialNo=((int)$startSerialNo + (int)$allBooksQuantity) -1;
                $endSerialNo=((int)$startSerialNo + (int)$quantity) -1;
            }
            
            if((int)$startSerialNo<=(int)$endSerialNo){
            $serialNoExists = AnswerBookletBatches::select('id')->where('prefix','=',$prefix)->where('booklet_size','=',$bookletSize)->where('start_serial_no','<=',$startSerialNo)->where('end_serial_no','>=',$startSerialNo)->first();
            $serialNoEndExists = AnswerBookletBatches::select('id')->where('prefix','=',$prefix)->where('booklet_size','=',$bookletSize)->where('start_serial_no','<=',$endSerialNo)->where('end_serial_no','>=',$endSerialNo)->first();
            
            if(!empty($serialNoExists)||!empty($serialNoEndExists)){
                return response()->json(['error'=>true,'message'=>'Serial numbers are already generated.']);
            }else{

                // DB::beginTransaction();

                // try {

                $adminData = \Auth::guard('admin')->user()->toArray();
                
                $anserBookletBatch = new AnswerBookletBatches();
                $anserBookletBatch->prefix = $prefix;
                $anserBookletBatch->booklet_size = $bookletSize;
                $anserBookletBatch->start_serial_no = $startSerialNo;
                $anserBookletBatch->end_serial_no = $endSerialNo;
                $anserBookletBatch->quantity = $quantity;
                $anserBookletBatch->created_at = date('Y-m-d H:i:s');
                $anserBookletBatch->admin_id =$adminData['admin_id'];
                $anserBookletBatch->save();
                $batch_id  = $anserBookletBatch->id;
                for($z=0;$z<$quantity;$z++){
                    $numericSerialNo=(int)$startSerialNo + $z;
                    $serialNo=$prefix.'-'.$bookletSize.'-'.$numericSerialNo;
                    $answerBookletData = new AnswerBookletData();
                    $answerBookletData->batch_id = $batch_id;
                    $answerBookletData->serial_no = $numericSerialNo;
                    $answerBookletData->qr_data = $serialNo;
                    $answerBookletData->key = strtoupper(md5($serialNo.'_'.date('YmdHis')));
                    $answerBookletData->metadata1 = 'Cover Page';
                    $answerBookletData->scan_count = 0;
                    $answerBookletData->status = 1;
                    $answerBookletData->publish = 1;
                    $answerBookletData->created_at = date('Y-m-d H:i:s');
                    $answerBookletData->save();

                    for($y=1;$y<=$quantityOfSingleBooklet;$y++){
                        $serialNoInternal=$serialNo.'-'.$y;
                        $answerBookletData = new AnswerBookletData();
                        $answerBookletData->batch_id = $batch_id;
                        $answerBookletData->serial_no = $numericSerialNo.'-'.$y;
                        $answerBookletData->qr_data = $serialNoInternal;
                        $answerBookletData->key = strtoupper(md5($serialNoInternal.'_'.date('YmdHis')));
                        $answerBookletData->metadata1 = 'Internal Page';
                        $answerBookletData->scan_count = 0;
                        $answerBookletData->status = 1;
                        $answerBookletData->publish = 1;
                        $answerBookletData->created_at = date('Y-m-d H:i:s');
                        $answerBookletData->save();
                    }

                  

                }

                //     DB::commit();
                // // all good
                // } catch (\Exception $e) {
                //     DB::rollback();
                //     // something went wrong
                // }

                return response()->json(['success'=>true,'message'=>'Answerbook generated successfully.']);
            }
            }else{
                return response()->json(['error'=>true,'message'=>'Something went wrong. Please contact administrator.']);
            }
           
       
       
        }else{
            return response()->json(['error'=>true,'message'=>'Required fields are missing.']);
        }

        //return response()->json(['success'=>true]);
       

    }

    public function excelreport(Request $request){
        $sheet_name = 'AnswerBooklet_'. date('Y_m_d_H_i_s').'.xlsx'; 

        
        return Excel::download(new AnswerBookletExport(),$sheet_name);             
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
     * Remove the specified PaymentGateway from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        
    }


}

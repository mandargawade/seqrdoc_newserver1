<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 15/11/2019
 *   Use   : listing of font file & store and update & delete font file  
 *
**/
namespace App\Http\Controllers\Admin;

use App\Events\FontMasterEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\FontMasterRequest;
use App\models\FontMaster;
use Illuminate\Http\Request;
use DB;
use Auth;
class FontMasterController extends Controller
{
    /**
     * Display a listing of the Fonts.
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
                $where_str .= " and (font_name like \"%{$search}%\""
                 . " or font_filename_N like \"%{$search}%\""
                 . " or font_filename_B like \"%{$search}%\""
                 . " or font_filename_I like \"%{$search}%\""
                 . " or font_filename_BI like \"%{$search}%\""
                . ")";
            }  
           $status=$request->get('status');
           
            if($status==1)
            {
                $status=1;
                $where_str.= " and (font_master.status =$status)";
            }
            else if($status==0)
            {
                $status=0;
                $where_str.=" and (font_master.status= $status)";
            }                                               
             //for serial number
            $auth_site_id=auth::guard('admin')->user()->site_id;
            
            DB::statement(DB::raw('set @rownum=0')); 
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'font_name','font_filename_N','font_filename_B','font_filename_I','font_filename_BI','id','updated_at'];
            
            $font_master_count = FontMaster::select($columns)
               ->whereRaw($where_str, $where_params)
               ->where('site_id',$auth_site_id)
               ->where('publish',1) 
               ->count();

            $fontMaster_list = FontMaster::select($columns)
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
        $fontMasterExceptions=\Config::get('constant.fontMasterExceptions');

        return view('admin.fontMaster.index',compact('fontMasterExceptions'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        
    }

    /**
     * Store a newly created Font file in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(FontMasterRequest $request)
    {
      
       $fileValid=[
               'upload_font_N'=>$request['upload_font_N'],
               'upload_font_B'=>$request['upload_font_B'],
               'upload_font_I'=>$request['upload_font_I'],
               'upload_font_BI'=>$request['upload_font_BI'],

          ];

       if(empty($fileValid['upload_font_N']) && empty($fileValid['upload_font_B']) && empty($fileValid['upload_font_I']) && empty($fileValid['upload_font_BI']))
       {
          return response()->json(['success'=>false,'fontFile'=>'at least one Font file required']);
       } 
       $ValidMsg="Accepted file format .ttf";
       // valid extension write
       $ex=['ttf'];
    
       // check valid extension all file
       foreach ($fileValid as $key => $value) {
          if(!empty($value))
          {

             if(!in_array($value->getClientOriginalExtension(),$ex))
             {
                 $validExtension[$key]=$ValidMsg;
                 
             } 
          }
       }
       // false after return response 
       if(!empty($validExtension))
       {
          return response()->json(['success'=>false,'validEx'=>$validExtension]);
       }

       // Extension valid after run code 
       $fontMatser_data=$request->all();
       event(new FontMasterEvent($fontMatser_data));

       return response()->json(['success'=>true]);
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
     * Show the form for editing the specified Font file.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
         // database fetch data on specific id
        $data=FontMaster::find($id)->toArray();
        return $data;        
    }

    /**
     * Update the specified font file in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(FontMasterRequest $request,$id)
    { 
       $fileValid=[
               'upload_font_N'=>$request['upload_font_N'],
               'upload_font_B'=>$request['upload_font_B'],
               'upload_font_I'=>$request['upload_font_I'],
               'upload_font_BI'=>$request['upload_font_BI'],

          ];
 
       $ValidMsg="Accepted file format .ttf";
       // valid extension write
       $ex=['ttf'];
    
       // check valid extension all file
       foreach ($fileValid as $key => $value) {
          if(!empty($value))
          {

             if(!in_array($value->getClientOriginalExtension(),$ex))
             {
                 $validExtension[$key]=$ValidMsg;
                 
             } 
          }
       }
       // false after return response 
       if(!empty($validExtension))
       {
          return response()->json(['validEx'=>$validExtension]);
       } 
        
        // Extension valid after run code
       $fontMatser_data=$request->all();
       event(new FontMasterEvent($fontMatser_data));

       return response()->json(['success'=>true]);
    }

    /**
     * Remove the specified font file from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //Soft Delete
       $fontMaster=FontMaster::where('id',$id)->delete();

       return $fontMaster ? response()->json(['success'=>true]) : "false";  
    }
}

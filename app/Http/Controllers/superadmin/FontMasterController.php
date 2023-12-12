<?php
/**
 *
 *  Author : Mandar Gawade
 *   Date  : 03/01/2022
 *   Use   : listing of font file & store and update & delete font file  
 *
**/
namespace App\Http\Controllers\Superadmin;

//use App\Events\superadmin\FontMasterEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\superadmin\SuperFontMasterRequest as FontMasterRequest;
use App\Http\Requests\superadmin\AssignFontMasterRequest;
use App\models\superadmin\SuperFontMaster as FontMaster;
use Illuminate\Http\Request;
use App\Jobs\superadmin\FontMasterJob;
use DB;
use Auth;
use App\Models\Site;
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
                $where_str.= " and (super_font_master.status =$status)";
            }
            else if($status==0)
            {
                $status=0;
                $where_str.=" and (super_font_master.status= $status)";
            }                                               
             //for serial number
            $auth_site_id=auth::guard('superadmin')->user()->site_id;
            
            DB::statement(DB::raw('set @rownum=0')); 
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'font_name','font_filename_N','font_filename_B','font_filename_I','font_filename_BI','total_instances','id','updated_at'];
            
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

         $instancesList = Site::select('site_url');
         $instancesList = $instancesList->get();
          $instancesListArray=$instancesList->toArray();
        return view('superadmin.fontMaster.index',compact('instancesListArray'));
    }

    public function assignfont(AssignFontMasterRequest $request){
      //print_r($request->all());
      if(!isset($request['normalFont'])&&!isset($request['boldFont'])&&!isset($request['italicFont'])&&!isset($request['boldItalicFont'])){
          $respArr=array("status"=>false,"message"=>"Please select font style.");
         //$respArr=array("message"=>"The given data was invalid.","errors"=>array("font_style"=>array("Please select font style.")));
          /*http_response_code(422);*/
      }else{
          $errorArr=array();
          $instances=$request['dest_instance'];
          $font_name=$request['font_name_assign'];
          $font_id=$request['font_id_assign'];
          $auth_id=auth::guard('superadmin')->user()->id;
        // print_r(auth::guard('superadmin')->user());
          //exit;
          
          $superFontData=DB::table("super_font_master")->where('id',$font_id)->first();
          
          if($superFontData){
          
          foreach ($instances as  $readData) {

           $siteData=DB::table("sites")->where('site_url',$readData)->first();
           if($siteData){
           // $siteData=$siteData->toArray();
            $instArr=explode('.', $readData);

              if($instArr[0]=="demo"){
                $dbName = 'seqr_demo';
              }else{
                $dbName = 'seqr_d_'.$instArr[0];
              }
             
                
                $new_connection = 'new';
                $nc = \Illuminate\Support\Facades\Config::set('database.connections.' . $new_connection, [
                    'driver'   => 'mysql',
                    'host'     => \Config::get('constant.DB_HOST'),
                    "port" => \Config::get('constant.DB_PORT'),
                    'database' => $dbName,
                    'username' => \Config::get('constant.DB_UN'),
                    'password' => \Config::get('constant.DB_PW'),
                    /*'username' => 'developer',
                    'password' => 'developer',
                    */"unix_socket" => "",
                    "charset" => "utf8mb4",
                    "collation" => "utf8mb4_unicode_ci",
                    "prefix" => "",
                    "prefix_indexes" => true,
                    "strict" => true,
                    "engine" => null,
                    "options" => []
                ]);

                $fontData=DB::connection($new_connection)->table("font_master")->where('font_name','LIKE','%'.$font_name.'%')->first();
                if($fontData){

                  if($superFontData->font_name===$font_name){
                    $updateArr=array();
                    if(isset($request['normalFont'])){
                      $updateArr['font_filename_N']=$superFontData->font_filename_N;
                    }
                    if(isset($request['boldFont'])){
                      $updateArr['font_filename_B']=$superFontData->font_filename_B;
                    }
                    if(isset($request['italicFont'])){
                      $updateArr['font_filename_I']=$superFontData->font_filename_I;
                    }
                    if(isset($request['boldItalicFont'])){
                      $updateArr['font_filename_BI']=$superFontData->font_filename_BI;
                    }
                    $updateArr['updated_at']=date('Y-m-d H:i:s');
                    $updateArr['updated_by']=$auth_id;
                    DB::connection($new_connection)->table("font_master")->where('id',$fontData->id)->update($updateArr);
                  }else{
                   array_push($errorArr, 'Font already present with different name in '.$instArr[0]);  
                  }
                  
                }else{

                  $arrayInsert=array();
                   $arrayInsert['font_name']=$superFontData->font_name;
                   if(isset($request['normalFont'])){
                    $arrayInsert['font_filename_N']=$superFontData->font_filename_N;
                    }
                    if(isset($request['boldFont'])){
                    $arrayInsert['font_filename_B']=$superFontData->font_filename_B;
                    }
                    if(isset($request['italicFont'])){
                    $arrayInsert['font_filename_I']=$superFontData->font_filename_I;
                    }
                    if(isset($request['boldItalicFont'])){
                    $arrayInsert['font_filename_BI']=$superFontData->font_filename_BI;
                    }
                   $arrayInsert['font_filename']=$superFontData->font_filename;
                   $arrayInsert['created_at']=date('Y-m-d H:i:s');
                   $arrayInsert['created_by']=$auth_id;
                   $arrayInsert['site_id']=$siteData->site_id;
                   DB::connection($new_connection)->table("font_master")->insert($arrayInsert);

                }
              }else{
                array_push($errorArr, $instArr[0].' instance site data not found !');  
              }
          }
          if(count($errorArr)>0){
            $errorStr=implode(',', $errorArr);
            $message="Some fonts may not assigned because of data error.";
          }else{
            $errorStr='';
            $message="Font assigned successfully!";
          }

          $respArr=array('status' => true, "message"=>$message, "errorMessage"=>$errorStr);

        }else{
          $respArr=array('status' => false, "message"=>"Font data not found !");
        }
      }
     // $respArr=array('status' => false, "message"=>"Something went wrong!");
      return $respArr;
      //print_r($request);
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
       $ex=['ttf','TTF'];
    
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
       //event(new FontMasterEvent($fontMatser_data));

       $response = $this->dispatch(new FontMasterJob($fontMatser_data));
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
        $data = array_map('strval', $data);
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
       $ex=['ttf','TTF'];
    
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
       //event(new FontMasterEvent($fontMatser_data));
       $response = $this->dispatch(new FontMasterJob($fontMatser_data));
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

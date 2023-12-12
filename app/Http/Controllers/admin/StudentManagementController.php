<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 30/11/2019
 *   Use   : listing of StudentManagement & store Excel sheet data on storage
 *
**/
namespace App\Http\Controllers\Admin;

use App\Exports\StudentMangementExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentManagementRequest;
use App\Imports\StudentManagementImport;
use App\models\StudentDocument;
use App\models\StudentMaster;
use DB;
use Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Auth;
class StudentManagementController extends Controller
{
    /**
     * Display a listing of the Students.
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
                $where_str .= " and ( student_documents.doc_id like \"%{$search}%\""
                . " or student_master.enrollment_no like \"%{$search}%\""
                . " or student_master.date_of_birth like \"%{$search}%\""
                . ")";
            } 

            $auth_site_id=Auth::guard('admin')->user()->site_id;
             //for serial number
            DB::statement(DB::raw('set @rownum=0')); 
              
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'student_documents.doc_id','student_master.enrollment_no','student_master.date_of_birth','student_master.updated_at'];

            $font_master_count = StudentDocument::select($columns)
            ->join('student_master','student_documents.student_id','student_master.student_id')
            ->whereRaw($where_str, $where_params)
             ->where('student_master.site_id',$auth_site_id)
            ->count();
            
            $fontMaster_list = StudentDocument::select($columns)
             ->join('student_master','student_documents.student_id','student_master.student_id')
             ->where('student_master.site_id',$auth_site_id)
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
       return view('admin.studentManagement.index');  
    }
     /**
     * store Excel sheet data on storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function fileUpload(StudentManagementRequest $request)
    {
       if($request->hasFile('field_file'))
       {     
          
          $response= Excel::toArray(new StudentManagementImport,$request->file('field_file'));

          $Excel_data=head($response);
          $columns=array();
          foreach ($Excel_data[0] as $key => $value) {
              
             if(!empty($value))
             {
                $columns[]=$value;
             }         
          }

        if(count($columns)===3)
         {

           // unset Header in Excel
           foreach ($Excel_data as $key => $value) {
               unset($Excel_data[0]);
            }
         
          
           // check data not null
          for ($i=1; $i <count($Excel_data)+1 ; $i++) { 

              if(($Excel_data[$i][0]) !="" && ($Excel_data[$i][1]) !="" &&
                ($Excel_data[$i][2]) !="")
              {
                  $document_id[]=$Excel_data[$i][0]; 
              }
              else 
              {  
                 $line=$i+1;
                 $noLine='Data is missing on line '.$line.'';
                 return response()->json(["success"=>false,'NoLine'=>$noLine]);
              }

            }
             // check document no are unique
            if(count($document_id)===count(array_unique($document_id)))
            {
              $auth_site_id=Auth::guard('admin')->user()->site_id;
               foreach ($Excel_data as $key => $value) {
            
                  $StudentMaster= new StudentMaster();
                  $StudentMaster->enrollment_no=$value[1];
                  $StudentMaster->date_of_birth=$value[2];
                  $StudentMaster->site_id=$auth_site_id;
                  $StudentMaster->save();

                  $student_array=$StudentMaster->toArray();
                  $student_id=$student_array['id'];

                  $StudentDocument=new StudentDocument();
                  $StudentDocument->doc_id=$value[0];
                  $StudentDocument->student_id=$student_id;
                  $StudentDocument->save();
             }
             // success response
              return response()->json(['success'=>true]);
            }
            else
            {
               return response()->json(['success'=>false,'InvalidData'=>'Invalid Data. Document No Should Be Unique.']); 
            }
        }
       else
        {
         return response()->json(['success'=>false,'ExcelInvalid'=>'Invalid ExcelSheet']);
        }
      }      
    }
}

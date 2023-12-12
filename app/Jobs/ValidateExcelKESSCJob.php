<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\models\BackgroundTemplateMaster;
use Auth,DB;
use TCPDF;
use App\models\FontMaster;
use App\models\SystemConfig;
use QrCode;
use App\models\Config;
use App\models\StudentTable;
use App\models\SbStudentTable;
use App\models\PrintingDetail;
use App\models\SbPrintingDetail;
use App\models\ExcelUploadHistory;
use App\models\SbExceUploadHistory;
use App\Jobs\SendMailJob;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use Illuminate\Support\Facades\Redis;
use App\models\ThirdPartyRequests;
class ValidateExcelKESSCJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $timeout = 180000;
    protected $pdf_data;
  

    public function __construct($pdf_data)
    {
        $this->pdf_data = $pdf_data;
        
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    {
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $pdf_data = $this->pdf_data;

        $rowData1=$pdf_data['rowData1'];
        $auth_site_id=$pdf_data['auth_site_id'];
        $dropdown_template_id=$pdf_data['dropdown_template_id'];
        $rowData1[0] = array_filter($rowData1[0]);
        // dd($rowData);
        /*
        print_r($rowData1[0]);              
        exit;*/
        $columnsCount1=count($rowData1[0]);
        $photo_col=$columnsCount1-1;
            if($dropdown_template_id==1){
                if($columnsCount1==142){

                    $columnArr=array('Unique Id','Name of the Learner','Programmer','Semester','Roll No','Seat No','Month & Year of Examination','PRN/Reg No','UID No','Course Code1','Course Title1','Internal1','Practical1','Theory1','Overall Marks1','Max. Marks1','Grade1','Grade Point(G)1','Course Credits(C )1','(C X G)1','SGPA=∑CG/∑C 1','Course Code2','Course Title2','Internal2','Practical2','Theory2','Overall Marks2','Max. Marks2','Grade2','Grade Point(G)2','Course Credits(C )2','(C X G)2','SGPA=∑CG/∑C 2','Course Code3','Course Title3','Internal3','Practical3','Theory3','Overall Marks3','Max. Marks3','Grade3','Grade Point(G)3','Course Credits(C )3','(C X G)3','SGPA=∑CG/∑C 3','Course Code4','Course Title4','Internal4','Practical4','Theory4','Overall Marks4','Max. Marks4','Grade4','Grade Point(G)4','Course Credits(C )4','(C X G)4','SGPA=∑CG/∑C 4','Course Code5','Course Title5','Internal5','Practical5','Theory5','Overall Marks5','Max. Marks5','Grade5','Grade Point(G)5','Course Credits(C )5','(C X G)5','SGPA=∑CG/∑C 5','Course Code6','Course Title6','Internal6','Practical6','Theory6','Overall Marks6','Max. Marks6','Grade6','Grade Point(G)6','Course Credits(C )6','(C X G)6','SGPA=∑CG/∑C 6','Course Code7','Course Title7','Internal7','Practical7','Theory7','Overall Marks7','Max. Marks7','Grade7','Grade Point(G)7','Course Credits(C )7','(C X G)7','SGPA=∑CG/∑C 7','Total Internal','Total Practical','Total Theory','Total Overall Marks','Total Max Marks','Total Grade Point','Total Course Credit','Total (C X G)','Total SGPA=∑CG/∑C','Remark','Grade','Credit Earned','SGPA','CGPA','Overall','Credit Earned Sem I','Credit Earned Sem II','Credit Earned Sem III','Credit Earned Sem IV','Credit Earned Sem V','Credit Earned Sem VI','SGPA Sem I','SGPA Sem II','SGPA Sem III','SGPA  Sem IV','SGPA Sem V','SGPA Sem VI','Additional Course Credit Courses1','Courses credit1','Additional Course Credit Courses2','Courses credit2','Additional Course Credit Courses3','Courses credit3','Additional Course Credit Courses4','Courses credit4','Additional Course Credit Courses5','Courses credit5','Additional Course Credit Courses6','Courses credit6','Total Credit','Attendance in (Grade %)Sem I','Attendance in (Grade %)Sem II','Attendance in (Grade %)Sem III','Attendance in (Grade %)Sem IV','Attendance in (Grade %)Sem V','Attendance in (Grade %)Sem VI','Place','Date','Photo');
                    $mismatchColArr=array_diff($rowData1[0], $columnArr);
                    //print_r($mismatchColArr);
                    if(count($mismatchColArr)>0){
                        
                        return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                    }
                }else{
                    return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
                }
            }
            elseif($dropdown_template_id==2){
                if($columnsCount1==156){
                    $columnArr=array("Unique Id","Convocation Id","Name of the Learner","Programmer","Semester","Roll No","Seat No","Month & Year of Examination","PRN/Reg No","UID No","Course Code1","Course Title1","Internal1","Practical1","Theory1","Overall Marks1","Max. Marks1","Grade1","Grade Point(G)1","Course Credits(C )1","(C X G)1","SGPA=∑CG/∑C 1","Course Code2","Course Title2","Internal2","Practical2","Theory2","Overall Marks2","Max. Marks2","Grade2","Grade Point(G)2","Course Credits(C )2","(C X G)2","SGPA=∑CG/∑C 2","Course Code3","Course Title3","Internal3","Practical3","Theory3","Overall Marks3","Max. Marks3","Grade3","Grade Point(G)3","Course Credits(C )3","(C X G)3","SGPA=∑CG/∑C 3","Course Code4","Course Title4","Internal4","Practical4","Theory4","Overall Marks4","Max. Marks4","Grade4","Grade Point(G)4","Course Credits(C )4","(C X G)4","SGPA=∑CG/∑C 4","Course Code5","Course Title5","Internal5","Practical5","Theory5","Overall Marks5","Max. Marks5","Grade5","Grade Point(G)5","Course Credits(C )5","(C X G)5","SGPA=∑CG/∑C 5","Course Code6","Course Title6","Internal6","Practical6","Theory6","Overall Marks6","Max. Marks6","Grade6","Grade Point(G)6","Course Credits(C )6","(C X G)6","SGPA=∑CG/∑C 6","Course Code7","Course Title7","Internal7","Practical7","Theory7","Overall Marks7","Max. Marks7","Grade7","Grade Point(G)7","Course Credits(C )7","(C X G)7","SGPA=∑CG/∑C 7","Course Code8","Course Title8","Internal8","Practical8","Theory8","Overall Marks8","Max. Marks8","Grade8","Grade Point(G)8","Course Credits(C )8","(C X G)8","SGPA=∑CG/∑C 8","Course Code9","Course Title9","Internal9","Practical9","Theory9","Overall Marks9","Max. Marks9","Grade9","Grade Point(G)9","Course Credits(C )9","(C X G)9","SGPA=∑CG/∑C 9","Course Code10","Course Title10","Internal10","Practical10","Theory10","Overall Marks10","Max. Marks10","Grade10","Grade Point(G)10","Course Credits(C )10","(C X G)10","SGPA=∑CG/∑C 10","Total Internal","Total Practical","Total Theory","Total Overall Marks","Total Max Marks","Total Grade Point","Total Course Credit","Total (C X G)","Total SGPI=∑CG/∑C","Remark","Grade","Credit Earned","SGPI","Credit Earned Sem I","Credit Earned Sem II","Credit Earned Sem III","Credit Earned Sem IV","Grade Earned Sem I","Grade Earned Sem II","Grade Earned Sem III","Grade Earned  Sem IV","Overall CGPI","Overall Grade","Place","Date","Photo");
                    $mismatchColArr=array_diff($rowData1[0], $columnArr);
                    //print_r($mismatchColArr);
                    if(count($mismatchColArr)>0){
                        
                        return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                    }
                }else{
                    return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
                }    
            }
			elseif($dropdown_template_id==3){
                if($columnsCount1==202){
                    $columnArr=array('Unique Id','Name of the Learner','Programmer','Semester','Roll No','Seat No','Month & Year of Examination','PRN/Reg No','UID No','Course Code1','Course Title1','Internal1','Practical1','Theory1','Overall Marks1','Max. Marks1','Grade1','Grade Point(G)1','Course Credits(C )1','(C X G)1','SGPA=∑CG/∑C 1','Course Code2','Course Title2','Internal2','Practical2','Theory2','Overall Marks2','Max. Marks2','Grade2','Grade Point(G)2','Course Credits(C )2','(C X G)2','SGPA=∑CG/∑C 2','Course Code3','Course Title3','Internal3','Practical3','Theory3','Overall Marks3','Max. Marks3','Grade3','Grade Point(G)3','Course Credits(C )3','(C X G)3','SGPA=∑CG/∑C 3','Course Code4','Course Title4','Internal4','Practical4','Theory4','Overall Marks4','Max. Marks4','Grade4','Grade Point(G)4','Course Credits(C )4','(C X G)4','SGPA=∑CG/∑C 4','Course Code5','Course Title5','Internal5','Practical5','Theory5','Overall Marks5','Max. Marks5','Grade5','Grade Point(G)5','Course Credits(C )5','(C X G)5','SGPA=∑CG/∑C 5','Course Code6','Course Title6','Internal6','Practical6','Theory6','Overall Marks6','Max. Marks6','Grade6','Grade Point(G)6','Course Credits(C )6','(C X G)6','SGPA=∑CG/∑C 6','Course Code7','Course Title7','Internal7','Practical7','Theory7','Overall Marks7','Max. Marks7','Grade7','Grade Point(G)7','Course Credits(C )7','(C X G)7','SGPA=∑CG/∑C 7','Course Code8','Course Title8','Internal8','Practical8','Theory8','Overall Marks8','Max. Marks8','Grade8','Grade Point(G)8','Course Credits(C )8','(C X G)8','SGPA=∑CG/∑C 8','Course Code9','Course Title9','Internal9','Practical9','Theory9','Overall Marks9','Max. Marks9','Grade9','Grade Point(G)9','Course Credits(C )9','(C X G)9','SGPA=∑CG/∑C 9','Course Code10','Course Title10','Internal10','Practical10','Theory10','Overall Marks10','Max. Marks10','Grade10','Grade Point(G)10','Course Credits(C )10','(C X G)10','SGPA=∑CG/∑C 10','Course Code11','Course Title11','Internal11','Practical11','Theory11','Overall Marks11','Max. Marks11','Grade11','Grade Point(G)11','Course Credits(C )11','(C X G)11','SGPA=∑CG/∑C 11','Course Code12','Course Title12','Internal12','Practical12','Theory12','Overall Marks12','Max. Marks12','Grade12','Grade Point(G)12','Course Credits(C )12','(C X G)12','SGPA=∑CG/∑C 12','Total Internal','Total Practical','Total Theory','Total Overall Marks','Total Max Marks','Total Grade Point','Total Course Credit','Total (C X G)','Total SGPA=∑CG/∑C','Remark','Grade','Credit Earned','SGPA','CGPA','Overall','Credit Earned Sem I','Credit Earned Sem II','Credit Earned Sem III','Credit Earned Sem IV','Credit Earned Sem V','Credit Earned Sem VI','SGPA Sem I','SGPA Sem II','SGPA Sem III','SGPA  Sem IV','SGPA Sem V','SGPA Sem VI','Additional Course Credit Courses1','Courses credit1','Additional Course Credit Courses2','Courses credit2','Additional Course Credit Courses3','Courses credit3','Additional Course Credit Courses4','Courses credit4','Additional Course Credit Courses5','Courses credit5','Additional Course Credit Courses6','Courses credit6','Total Credit','Attendance in (Grade %)Sem I','Attendance in (Grade %)Sem II','Attendance in (Grade %)Sem III','Attendance in (Grade %)Sem IV','Attendance in (Grade %)Sem V','Attendance in (Grade %)Sem VI','Place','Date','Photo');
                    $mismatchColArr=array_diff($rowData1[0], $columnArr);
                    //print_r($mismatchColArr);
                    if(count($mismatchColArr)>0){
                        
                        return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                    }
                }else{
                    return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
                }
            }
                $ab = array_count_values($rowData1[0]);

                $duplicate_columns = '';
                foreach ($ab as $key => $value) {
                    
                    if($value > 1){

                        if($duplicate_columns != ''){

                            $duplicate_columns .= ", ".$key;
                        }else{
                            
                            $duplicate_columns .= $key;
                        }
                    }
                }

                if($duplicate_columns != ''){
                    // Excel has more than 1 column having same name. i.e. <column name>
                    $message = Array('success'=>false,'type' => 'error', 'message' => 'Excel/Json has more than 1 column having same name. i.e. : '.$duplicate_columns);
                    return json_encode($message);
                }
               
                

                unset($rowData1[0]);
                $rowData1=array_values($rowData1);
                $blobArr=array();
                $profArr=array();
                $profErrArr=array();
                /*foreach ($rowData1 as $readblob) {
                    array_push($blobArr, $readblob[23]);
                }*/
				
                $sandboxCheck = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();
                $old_rows = 0;
                $new_rows = 0;
                foreach ($rowData1 as $key1 => $value1) {

                    $serial_no=$value1[0];
                    array_push($blobArr, $serial_no);
                   // array_push($profArr, $value1[141]);

                    $profile_path = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$value1[$photo_col];
                    if (!file_exists($profile_path)) {   
                         array_push($profErrArr, $value1[$photo_col]);
                    }
                    if($sandboxCheck['sandboxing'] == 1){
                       
                        $studentTableCounts = SbStudentTable::where('serial_no',$serial_no)->where('site_id',$auth_site_id)->count();
                        
                        $studentTablePrefixCounts = SbStudentTable::where('serial_no','T-'.$serial_no)->where('site_id',$auth_site_id)->count();
                        if($studentTableCounts > 0){
                            $old_rows += 1;
                        }else if($studentTablePrefixCounts){
                            
                            $old_rows += 1;
                        }else{
                            $new_rows += 1;
                        }

                    }else{
                        $studentTableCounts = StudentTable::where('serial_no',$serial_no)->where('site_id',$auth_site_id)->count();
                        if($studentTableCounts > 0){
                            $old_rows += 1;
                        }else{
                            $new_rows += 1;
                        }
                    }   
                }

               
                $mismatchArr = array_intersect($blobArr, array_unique(array_diff_key($blobArr, array_unique($blobArr))));
                      
                    if(count($mismatchArr)>0){
                    return response()->json(['success'=>false,'type' => 'error','message' => 'Sheet1 : Unique Id contains following duplicate values : '.implode(',', $mismatchArr)]);
                    }
             /*  $mismatchProfArr = array_intersect($profArr, array_unique(array_diff_key($profArr, array_unique($profArr))));

                if(count($mismatchProfArr)>0){
                    return response()->json(['success'=>false,'type' => 'error','message' => 'Sheet1 : Photo contains following duplicate values : '.implode(',', $mismatchProfArr)]);
                    }*/
                 if(count($profErrArr)>0){
                    return response()->json(['success'=>false,'type' => 'error','message' => 'Sheet1 : Photo does not exists following values : '.implode(',', $profErrArr)]);
                    }
                      
                return response()->json(['success'=>true,'type' => 'success', 'message' => 'success','old_rows'=>$old_rows,'new_rows'=>$new_rows]);
    }

  
}

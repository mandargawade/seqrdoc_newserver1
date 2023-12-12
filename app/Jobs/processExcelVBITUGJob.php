<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;
use App\models\ExcelMergeLogs;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use Auth;

class processExcelJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $merge_excel_data;
    public function __construct($merge_excel_data)
    {
        $this->merge_excel_data = $merge_excel_data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    {
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $s3=\Storage::disk('s3');

        $merge_excel_data = $this->merge_excel_data;

        if($get_file_aws_local_flag->file_aws_local == '1'){
            $processExcel_directory = '/'.$subdomain[0].'/backend/processExcel/';
            if(!$s3->exists($processExcel_directory))
            {
                $s3->makeDirectory($processExcel_directory, 0777);  
            }
        }
        else{
            $processExcel_directory = public_path().'/'.$subdomain[0].'/backend/processExcel/';

            if(!is_dir($processExcel_directory)){
                //Directory does not exist, then create it.
                mkdir($processExcel_directory, 0777);
            }
        }

        $file_name =  $merge_excel_data['excel_data']->getClientOriginalName();
        $extension = pathinfo($file_name, PATHINFO_EXTENSION);

        $inputType = 'Xls';
        if($extension == 'xlsx' || $extension == 'XLSX'){

         $inputType = 'Xlsx';   
        }

        $file_path = public_path().'/'.$subdomain[0].'/backend/processExcel/'.$file_name;

        $merge_excel_data['excel_data']->move(public_path().'/'.$subdomain[0].'/backend/processExcel/',$file_name);
        if($get_file_aws_local_flag->file_aws_local == '1'){
            $file_path_aws = '/'.$subdomain[0].'/backend/processExcel/'.$file_name;

            $s3->put($file_path_aws,file_get_contents(public_path().'/'.$subdomain[0].'/backend/processExcel/'.$file_name));
            
        }
        $newFileName = $file_name.'_'.date('YmdHis').'.'.$extension;

        $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputType);
        $spreadsheets = $objReader->load($file_path); 
        $first_sheet=$spreadsheets->getSheet(0)->toArray(); // get first worksheet rows
        $second_sheet=$spreadsheets->getSheet(1)->toArray(); // get second worksheet rows
        unset($first_sheet[0]);  
        unset($second_sheet[0]);  
        $total_unique_records=count($first_sheet);
        $last_row=$total_unique_records+1;
        $course_count = array_count_values(array_column($second_sheet, '0'));
        $max_course_count = (max($course_count));
        $total_course_cols=$max_course_count*7;        
         
        $html_tbl="<table border='1'>";
        $html_tbl .="<tr>";
        $html_tbl .="
        <td><strong>UniqueId</strong></td>
        <td><strong>MemoNo</strong></td>
        <td><strong>StudentsName</strong></td>
        <td><strong>ParentsName</strong></td>
        <td><strong>MonthYearOfExam</strong></td>
        <td><strong>University</strong></td>
        <td><strong>Examination</strong></td>
        <td><strong>Branch</strong></td>
        <td><strong>Gender</strong></td>
        <td><strong>HallTicketNo</strong></td>        
        <td><strong>CollegeCode</strong></td>        
        <td><strong>SerialNo</strong></td>    
        ";
        $y=1;
        $n=1;
        $a=1;
        $z=$max_course_count+1;
        for ($x = 1; $x <= $max_course_count*7; $x++) {
          if($y==1){ $title="Srno";}
          if($y==2){ $title="CourseCode";}
          if($y==3){ $title="CourseName";}
          if($y==4){ $title="GradeSecuredGI";}
          if($y==5){ $title="GradePointGI";}
          if($y==6){ $title="Result";}
          if($y==7){ $title="CreditObtainedCI";}
          $html_tbl .="<td><strong>".$title.$n."</strong></td>";          
          if($y==7){$y=0;$n++;}
          $y++;
        }    
        if($max_course_count <= 12){
            $last_col_count=12-$max_course_count;
            for ($mlc = 1; $mlc <= $last_col_count*7; $mlc++) {
                if($a==1){ $title="Srno";}
                if($a==2){ $title="CourseCode";}
                if($a==3){ $title="CourseName";}
                if($a==4){ $title="GradeSecuredGI";}
                if($a==5){ $title="GradePointGI";}
                if($a==6){ $title="Result";}
                if($a==7){ $title="CreditObtainedCI";}                
                $html_tbl .="<td><strong>".$title.$z."</strong></td>";
                if($a==7){$a=0;$z++;}
                $a++;
            }
        } 
        $html_tbl .="
        <td><strong>SubjectsRegistered</strong></td>
        <td><strong>Appeared</strong></td>
        <td><strong>Passed</strong></td>
        <td><strong>TotalGradeSecured</strong></td>
        <td><strong>TotalGI</strong></td>
        <td><strong>TotalResult</strong></td>
        <td><strong>Total CI</strong></td>
        <td><strong>SGPA</strong></td>
        <td><strong>CGPA</strong></td>
        <td><strong>Place</strong></td>
        <td><strong>Date</strong></td>
        <td><strong>Image</strong></td>
        ";
        $html_tbl .="</tr>";        
        foreach ($first_sheet as $sheet_one) {
            $unique_number=$sheet_one[0];
            $memo_no=htmlspecialchars($sheet_one[1]);
            $student_name=htmlspecialchars($sheet_one[2]);
            $parent_name=htmlspecialchars($sheet_one[3]);
            $month_year_of_exam=htmlspecialchars($sheet_one[4]);
            $university=htmlspecialchars($sheet_one[5]);
            $examination=htmlspecialchars($sheet_one[6]);
            $branch=htmlspecialchars($sheet_one[7]);	
            $gender=htmlspecialchars($sheet_one[8]);
            $hall_ticket_no=htmlspecialchars($sheet_one[9]);
            $college_code=$sheet_one[10];	
            $serial_no=$sheet_one[11];	
            $subjects_registered=$sheet_one[12]; 	
            $appeared=$sheet_one[13];	
            $passed=$sheet_one[14];
            $total_grade_secured=$sheet_one[15];
            $total_gi=$sheet_one[16];
            $total_result=$sheet_one[17];
            $total_ci=$sheet_one[18];
            $sgpa=$sheet_one[19];
            $cgpa=$sheet_one[20];
            $place=htmlspecialchars($sheet_one[21]);
            $result_date=$sheet_one[22];
            //$str_date = explode ("/", $sheet_one[22]); 
            //$result_date=$str_date[0].'-'.$str_date[1].'-'.$str_date[2];
            $photo=$sheet_one[23];
            
            /* get courses by specific registration number */
            //$reg_nos = array_keys(array_combine(array_keys($second_sheet), array_column($second_sheet, '0')),$hall_ticket_no);
            $reg_nos = array_keys(array_combine(array_keys($second_sheet), array_column($second_sheet, '0')),$unique_number);
            $actual_course_cols=count($reg_nos);
            $remain_cols=$total_course_cols-($actual_course_cols*7);
            $html_tbl .="<tr>";
            $html_tbl .="
                <td>".$unique_number."</td>
                <td>".$memo_no."</td>
                <td>".$student_name."</td>
                <td>".$parent_name."</td>
                <td>".$month_year_of_exam."</td>                
                <td>".$university."</td>
                <td>".$examination."</td>
                <td>".$branch."</td>
                <td>".$gender."</td>
                <td>".$hall_ticket_no."</td>
                <td>".$college_code."</td>
                <td>".$serial_no."</td>  
                ";   
            $srno=1;
            foreach ($reg_nos as $key => $value){
                $code=htmlspecialchars($second_sheet[$value][1]);	
                $course_name=htmlspecialchars($second_sheet[$value][2]);
                $grades=htmlspecialchars($second_sheet[$value][3]);
                $grade_points=htmlspecialchars($second_sheet[$value][4]);
                $result=htmlspecialchars($second_sheet[$value][5]);
                $credits=htmlspecialchars($second_sheet[$value][6]);
                $html_tbl .="
                <td>".$srno."</td>
                <td>".$code."</td>
                <td>".$course_name."</td>
                <td>".$grades."</td>
                <td>".$grade_points."</td>
                <td>".$result."</td>
                <td>".$credits."</td>
                ";
                $srno++;
            }
            if($remain_cols > 0){
                for ($r = 1; $r <= $remain_cols; $r++) {
                    $html_tbl .="<td></td>";
                }
            } 
            if($actual_course_cols <= 12){
                $last_col_count=12-$max_course_count;
                for ($lc = 1; $lc <= $last_col_count*7; $lc++) {
                    $html_tbl .="<td></td>";
                }
            }
            $html_tbl .="
                <td>".$subjects_registered."</td>
                <td>".$appeared."</td>
                <td>".$passed."</td>
                <td>".$total_grade_secured."</td>
                <td>".$total_gi."</td>
                <td>".$total_result."</td>
                <td>".$total_ci."</td>                
                <td>".$sgpa."</td>
                <td>".$cgpa."</td>
                <td>".$place."</td>
                <td>".$result_date."</td>
                <td>".$photo."</td>
                ";
            $html_tbl .="</tr>";
        }
        $html_tbl .="</table>"; 
        
        // save $table inside temporary file that will be deleted later
        $tmpfile = tempnam(sys_get_temp_dir(), 'html');
        file_put_contents($tmpfile, $html_tbl);
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Html();
        $spreadsheet = $reader->load($tmpfile);
        $spreadsheet->setActiveSheetIndex(0);
        $SheetHighestColumn = $spreadsheet->getSheet(0)->getHighestColumn();
        // adjust auto column width
        foreach ($spreadsheet->getSheet(0)->getColumnIterator() as $column) {
           $spreadsheet->getSheet(0)->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }
        $sheet_dimension = $spreadsheet->getSheet(0)->calculateWorksheetDimension(); // Get sheet dimension
        // Apply text format to numbers
        $spreadsheet->getSheet(0)->getStyle($sheet_dimension)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        /*
        $spreadsheet->getSheet(0)
                    ->getStyle('D2:D'.$last_row)
                    ->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT); 
        
        $spreadsheet->getSheet(0)
                    ->getStyle($SheetHighestColumn.'2:'.$SheetHighestColumn.$last_row)
                    ->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        */            
        unlink($tmpfile); // delete temporary file because it isn't needed anymore          
        $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet,$inputType);
        $objWriter->save(public_path().'/'.$subdomain[0].'/backend/processExcel/'.$newFileName);

        if($get_file_aws_local_flag->file_aws_local == '1'){
            $file_path = '/'.$subdomain[0].'/backend/processExcel/'.$newFileName;
            $s3->put($file_path,file_get_contents(public_path().'/'.$subdomain[0].'/backend/processExcel/'.$newFileName));
            unlink(public_path().'/'.$subdomain[0].'/backend/processExcel/'.$newFileName);
            unlink(public_path().'/'.$subdomain[0].'/backend/processExcel/'.$file_name);
        }

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $excel_merge_logs = new ExcelMergeLogs;
        $excel_merge_logs->raw_Excel = $file_name;
        $excel_merge_logs->processed_excel = $newFileName;
        $excel_merge_logs->total_unique_records = $total_unique_records;
        $excel_merge_logs->username = \Auth::guard('admin')->user()->id;
        $excel_merge_logs->site_id=$auth_site_id;
        $excel_merge_logs->status = 'success';

        $excel_merge_logs->save();

        if($get_file_aws_local_flag->file_aws_local == '1'){
            $path = \Config::get('constant.amazone_path').$subdomain[0].'/backend/processExcel/'.$newFileName;
        }
        else{
            $path = \Config::get('constant.local_base_path').$subdomain[0].'/backend/processExcel/'.$newFileName;
        }

        $message = Array('type' => 'success', 'message' => 'file successfully uploaded','link'=>$path);
        return $message;


    }
}

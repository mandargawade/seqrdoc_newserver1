<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\TemplateMaster;
use App\models\SuperAdmin;
use Session,TCPDF,TCPDF_FONTS,Auth,DB;
use App\Http\Requests\ExcelValidationRequest;
use App\Http\Requests\MappingDatabaseRequest;
use App\Http\Requests\TemplateMapRequest;
use App\Http\Requests\TemplateMasterRequest;
use App\Imports\TemplateMapImport
;use App\Imports\TemplateMasterImport;
use App\Jobs\PDFGenerateJob;
use App\models\BackgroundTemplateMaster;
use App\Events\BarcodeImageEvent;
use App\Events\TemplateEvent;
use App\models\FontMaster;
use App\models\FieldMaster;
use App\models\User;
use App\models\StudentTable;
use App\models\SbStudentTable;
use Maatwebsite\Excel\Facades\Excel;
use App\models\SystemConfig;
use App\Jobs\PreviewPDFGenerateJob;
use App\Exports\TemplateMasterExport;
use Storage;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use App\models\Config;
use App\models\PrintingDetail;
use App\models\ExcelUploadHistory;
use App\models\SbExceUploadHistory;
use App\models\FreedomFighterList;
use App\models\ImportLogDetail;
use App\models\Admin;

use App\Helpers\CoreHelper;
use Helper;
use App\Jobs\ValidateExcelMONADJob;
use App\Jobs\PdfGenerateMONADJob;
class MOLWACertificateController extends Controller
{        
    public function index(Request $request)
    {    
        if($request->ajax()){
            $where_str    = "1 = ?";
            //$where_str    .= " AND publish = '1'";
            $where_params = array(1); 

            //for seraching the keyword in datatable
            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and ( created_at like \"%{$search}%\""
                . " or ff_id like \"%{$search}%\""
                /*. " or ff_name like \"%{$search}%\""
                . " or father_or_husband_name like \"%{$search}%\""
                . " or mother_name like \"%{$search}%\""
                . " or post_office like \"%{$search}%\""*/
                . ")";
            } 
            $auth_site_id=Auth::guard('admin')->user()->site_id;
            //for serial number
            $iDisplayStart=$request->input('iDisplayStart'); 
            DB::statement(DB::raw('set @rownum='.$iDisplayStart));
            //DB::statement(DB::raw('set @rownum=0'));

            //column that we wants to display in datatable
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'), 'id', 'created_at', 'ff_photo', 'ff_id', 'ff_name', 'father_or_husband_name', 'mother_name', 'post_office', 'post_code', 'district', 'upazila_or_thana', 'village_or_ward', 'nid', 'ghost_image_code', DB::raw('IFNULL(`generated_at`,"") AS generated_at'), DB::raw('IFNULL(`completed_at`,"") AS completed_at'), DB::raw('IFNULL(`reimported_at`,"") AS reimported_at'), DB::raw('IFNULL(`c_generated_at`,"") AS c_generated_at'), DB::raw('IFNULL(`c_completed_at`,"") AS c_completed_at'), DB::raw('IFNULL(`c_reimported_at`,"") AS c_reimported_at'),'active'];
            
            $ffl_count = FreedomFighterList::select($columns)
            ->whereRaw($where_str, $where_params)
            //->where('site_id',$auth_site_id)
            ->count();

            //get list
            $ffl_list = FreedomFighterList::select($columns)
            ->whereRaw($where_str, $where_params);
            //->where('site_id',$auth_site_id);

            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $ffl_list = $ffl_list->take($request->input('iDisplayLength'))
                ->skip($request->input('iDisplayStart'));
            }          

            //sorting the data column wise
            if($request->input('iSortCol_0')){
                $sql_order='';
                for ( $i = 0; $i < $request->input('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->input('iSortCol_' . $i)];
                    if(false !== ($index = strpos($column, ' as '))){
                        $column = substr($column, 0, $index);
                    }
                    $ffl_list = $ffl_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            }
            $ffl_list = $ffl_list->get();

            $response['iTotalDisplayRecords'] = $ffl_count;
            $response['iTotalRecords'] = $ffl_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $ffl_list->toArray();

            return $response;
        }
    	return view('admin.molwa.index');
    }

    public function uploadpage(){

      //return view('admin.monad.index');
    }

    public function pdfGenerate(){
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain);
        
        $file_path=public_path().'\\'.$subdomain[0].'\backend\processExcel\sampleVBIT.xlsx';
        $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
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
        //echo "<pre>";
        //print_r($second_sheet);
        //print_r($max_course_count);
        //echo $max_course_count;    
        
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
            $reg_nos = array_keys(array_combine(array_keys($second_sheet), array_column($second_sheet, '0')),$hall_ticket_no);
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
        //echo $html_tbl; exit;
        // save $table inside temporary file that will be deleted later
        $tmpfile = tempnam(sys_get_temp_dir(), 'html');
        //rename($tmpfile, $tmpfile .= '.html');
        file_put_contents($tmpfile, $html_tbl);
        //$objExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Html();
        //$spreadsheet = $reader->loadIntoExisting($tmpfile, $objExcel);
        $spreadsheet = $reader->load($tmpfile);
        //$spreadsheet = $reader->loadFromString($html_tbl, $spreadsheet);
        //$spreadsheet->createSheet();
        $spreadsheet->setActiveSheetIndex(0);
        $SheetHighestColumn = $spreadsheet->getSheet(0)->getHighestColumn();
        // adjust auto column width
        foreach ($spreadsheet->getSheet(0)->getColumnIterator() as $column) {
           $spreadsheet->getSheet(0)->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }        
        
        $sheet_dimension = $spreadsheet->getSheet(0)->calculateWorksheetDimension(); // Get sheet dimension
        // Apply text format to numbers
        $spreadsheet->getSheet(0)->getStyle($sheet_dimension)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        /*$spreadsheet->getSheet(0)
                    ->getStyle('D2:D'.$last_row)
                    ->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);        
        
        $spreadsheet->getSheet(0)
                    ->getStyle($SheetHighestColumn.'2:'.$SheetHighestColumn.$last_row)
                    ->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);*/ 
        unlink($tmpfile); // delete temporary file because it isn't needed anymore 
        $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $objWriter->save(public_path().'/'.$subdomain[0].'/backend/processExcel/1vbit.xlsx');        
    exit; 
    //Start ID Card 
        
        $ghostImgArr = array();
        $pdf = new \Mpdf\Mpdf(['orientation' => 'P', 'mode' => 'utf-8', 'format' => [86, 54], 'tempDir'=>storage_path('tempdir')]);
        $pdf->SetCreator('seqr'); //PDF_CREATOR
        $pdf->SetAuthor('MPDF');
        $pdf->SetTitle('Certificate');
        $pdf->SetSubject('');
        // remove default header/footer
        $pdf->setHeader(false);
        $pdf->setFooter(false);
        $pdf->SetAutoPageBreak(false, 0);  
        //$pdf->SetDisplayMode('fullpage');

              
        $filename=public_path().'\\'.$subdomain[0].'\backend\processExcel\SampleData.xlsx';
        $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $spreadsheet = $objReader->load($filename); 
        $d=$spreadsheet->getSheet(0)->toArray();
        $sheetData = $spreadsheet->getActiveSheet()->toArray();
        $z=1;
        unset($sheetData[0]);               
        foreach ($sheetData as $t) {        
            $pdf->AddPage();
            //Final-FF_Basic-Print_Variable_Data.jpg, molwa_IdCard_bg.jpg, UV_Layout.jpg
            $bg=public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\molwa_ID_BG.jpg'; 
            $pdf->Image($bg, 0, 0, 86, 54, 'jpg', '', true, false);
            //UV 
            $JOYBANGLA=public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\uv_2.png'; 
            //$pdf->Image($JOYBANGLA, 20.8, 1, 9, 3, 'png', '', true, false);
            
            $JOYBANGLA_BANDHU=public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\uv_1.png'; 
            //$pdf->Image($JOYBANGLA_BANDHU, 55.1, 1, 9, 3, 'png', '', true, false);
            
            $Picture_Sheikh_Mujibur_Rahman=public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\uv_4.png'; 
            //$pdf->Image($Picture_Sheikh_Mujibur_Rahman, 76.7, 2.5, 9.5, 7.5, 'png', '', true, false);
            
            $signature_1=public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\uv_3.png'; 
            //$pdf->Image($signature_1, 27.9, 46.3, 9, 3.5, 'png', '', true, false);
            
            $signature_2=public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\uv_3.png'; 
            //$pdf->Image($signature_2, 60.9, 46.3, 9, 3.5, 'png', '', true, false);
            
            $National_Monument=public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\uv_5.png'; 
            //$pdf->Image($National_Monument, 37.2, 41.2, 10.9, 10, 'png', '', true, false);


            //photo
            $ff_photo=public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\molwa_ff_photo.png';
            $pdf->Image($ff_photo, 3, 18, 18.7, 19.5, 'png', '', true, false);            
            //Freedom Fighter ID
            $ff_id=$t[0];
            $ff_name=$t[3];
            $fh_name=$t[4];
            $vw_name=$t[5];
            $post_office=$t[6];
            $ut_name=$t[7];
            $district=$t[8];
            $av_url=$t[10];
            $pdf->SetXY(48,16.7);
            $pdf->SetFont('verdana', '', 6);
            $pdf->MultiCell(37, 3, $ff_id, 0, 'L');
            $pdf->SetFont('nikosh', '', 9);
            //Freedom Fighter Name
            $pdf->SetXY(36.5,21.5);        
            $pdf->MultiCell(48.5, 0, $ff_name, 0, 'L');        
            //Father/Husband Name of FF
            $pdf->SetXY(35,25);        
            $pdf->MultiCell(50, 0, $fh_name, 0, 'L');    
            //Name of Village/Ward
            $pdf->SetXY(35,28.6);        
            $pdf->MultiCell(50, 0, $vw_name, 0, 'L');  
            //Name of Post Office
            $pdf->SetXY(32,32);
            $pdf->MultiCell(53, 0, $post_office, 0, 'L'); 
            //Upazila/Thana Name
            $pdf->SetXY(38.5,35.5);
            $pdf->MultiCell(46.5, 0, $ut_name, 0, 'L');
            //Name of District
            $pdf->SetXY(30,38.9);
            $pdf->MultiCell(55, 0, $district, 0, 'L');        
            
            //qr code 1   
            $dt = $z.date("_ymdHis");
            $encryptedString=strtoupper(md5($dt));
            $codeContents1 ="zVjbVPFeo2o";
            $qr_code_path1 = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
            $qrCodex = 3.1;
            $qrCodey = 40.3;
            $qrCodeWidth =11;
            $qrCodeHeight = 11;
            $ecc = 'L';
            $pixel_Size = 4;
            $frame_Size = 1;  
            \PHPQRCode\QRcode::png($codeContents1, $qr_code_path1, $ecc, $pixel_Size, $frame_Size);
            $pdf->Image($qr_code_path1, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false);
            
            //qr code    
            $dt = date("_ymdHis");
            $str=$ff_id.$dt;
            $codeContents =$encryptedString = strtoupper(md5($str));
            $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
            $qrCodex = 72;
            $qrCodey = 40;
            $qrCodeWidth =11;
            $qrCodeHeight = 11;
            $ecc = 'L';
            $pixel_Size = 4;
            $frame_Size = 1;  
            \PHPQRCode\QRcode::png($codeContents, $qr_code_path, $ecc, $pixel_Size, $frame_Size);
            $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false);        
            if($z==1){break;}
            $z+=1;         
        
        }        
        
        
        $pdf->Output();       
    //End ID Card        
        exit;  
       
        //Certificate
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain);
        $ghostImgArr = array();
        $pdf = new \Mpdf\Mpdf(['orientation' => 'P', 'mode' => 'utf-8', 'format' => [296.926, 210.058], 'tempDir'=>storage_path('tempdir')]);
        $pdf->SetCreator('seqr'); //PDF_CREATOR
        $pdf->SetAuthor('MPDF');
        $pdf->SetTitle('Certificate');
        $pdf->SetSubject('');
        // remove default header/footer
        $pdf->setHeader(false);
        $pdf->setFooter(false);
        $pdf->SetAutoPageBreak(false, 0);  
        //$arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.TTF', 'TrueTypeUnicode', '', 96);    

        $pdf->SetDisplayMode('fullpage');
        $filename=public_path().'\\'.$subdomain[0].'\backend\processExcel\SampleData.xlsx';
        $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $spreadsheet = $objReader->load($filename); 
        $d=$spreadsheet->getSheet(0)->toArray();
        $sheetData = $spreadsheet->getActiveSheet()->toArray();
        unset($sheetData[0]);
        $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
        $z=1; 
        foreach ($sheetData as $t) {
            $pdf->AddPage();
            //Bg Image
            $bg=public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\Molwa_Certificate_Bg.png';
            //$pdf->SetDefaultBodyCSS('background', "url('".$bg."')"); 
            //$pdf->SetDefaultBodyCSS('background-image-resize', 6);
            $pdf->Image($bg, 0, 0, 296.926,  210.058, 'jpg', '', true, false);
            
            //Freedom Fighter ID
            $ff_id=$t[0];
            $ff_name=$t[3];
            $fh_name=$t[4];
            $vw_name=$t[5];
            $post_office=$t[6];
            $ut_name=$t[7];
            $district=$t[8];
            $av_url=$t[10];
            
            //$pdf->SetXY(230,36);
            //$pdf->SetFont('arial', '', 11);
            //$pdf->MultiCell(0, 0, "01680004194", 0, 'L'); 
            $j = mb_strlen($ff_id);
            $char_x=231; //231.1
            $char_y=38.9; //38.7
            $digit_x=230.5;
            $digit_y=36.4; //36.6
            for ($k = 0; $k < $j; $k++) 
            {
                $digit = mb_substr($ff_id, $k, 1);
                $digit_char = $this->digitToChar($digit);            
                $pdf->SetXY($char_x,$char_y);
                $pdf->StartTransform();
                $pdf->Rotate(90);
                $pdf->SetFont('verdana', '', 4.5); //3.5
                $pdf->Cell(0, 0, $digit_char, 0, false, 'L');
                $pdf->StopTransform();
                $pdf->SetXY($digit_x,$digit_y);
                $pdf->SetFont('verdana', '', 11); //8.5
                $pdf->Cell(0, 0, $digit, 0, false, 'L');    
                $char_x +=3.9; //3
                $digit_x +=3.9; //3
            }        
            
            
            $pdf->SetFont('nikosh', '', 17);
            //Freedom Fighter Name
            //$pdf->SetXY(112,89);
            //$pdf->MultiCell(152, 7, "মাসুম সাইফুর রহমান", 0, 'C');  
            $pdf->SetXY(132,89);        
            $pdf->MultiCell(129, 7, $ff_name, 0, 'L');  
            //Father/Husband Name of FF
            $pdf->SetXY(64,100);
            $pdf->MultiCell(92, 7, $fh_name, 0, 'L');  
            //Name of Village/Ward
            $pdf->SetXY(188,100);
            $pdf->MultiCell(90, 7, $vw_name, 0, 'L');
            //Name of Post Office
            $pdf->SetXY(64,110);
            $pdf->MultiCell(90, 7, $post_office, 0, 'L');  
            //Upazila/Thana Name
            $pdf->SetXY(188,110);
            $pdf->MultiCell(90, 7, $ut_name, 0, 'L');
            //Name of District
            $pdf->SetXY(64,121);
            $pdf->MultiCell(94, 7, $district, 0, 'L');
           
            // Ghost image  7 33.426664583
            $ghost_font_size = 11;
            if($ghost_font_size == '10'){
                $ghostimageHeight = 5;
                $ghostimageWidth = 32;
            }
            else if($ghost_font_size == '11'){
                $ghostimageHeight = 7;
                $ghostimageWidth = 33.426664583;
            }else if($ghost_font_size == '12'){
                $ghostimageHeight = 8;
                $ghostimageWidth = 36.415397917;
            }
            else if($ghost_font_size == '13'){
                $ghostimageHeight = 10;
                $ghostimageWidth = 39.405983333; 
            }
            
            $nameOrg=trim($ff_id);
            $ghostImagex = 110;
            $ghostImagey = 197.5;
            $name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 11);
            //$tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
            $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
            $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostimageHeight, 'png', '', true, false);
            
            //$w12 = $this->CreateMessage($tmpDir, $name ,12,'');
            //$pdf->Image("$tmpDir/" . $name."12.png", $ghostImagex, $ghostImagey-10, $w12, 8, 'png', '', true, false);
            
            //$w10 = $this->CreateMessage($tmpDir, $name ,10,'');
            //$pdf->Image("$tmpDir/" . $name."10.png", $ghostImagex, $ghostImagey-20, $w10, 8, 'png', '', true, false);

            //qr code 1   
            $dt = $z.date("_ymdHis");
            $encryptedString=strtoupper(md5($dt));
            $codeContents1 ="zVjbVPFeo2o";
            $qr_code_path1 = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
            $qrCodex = 7;
            $qrCodey = 162.1;
            $qrCodeWidth =24;
            $qrCodeHeight = 24;
            $ecc = 'L';
            $pixel_Size = 4;
            $frame_Size = 1;  
            \PHPQRCode\QRcode::png($codeContents1, $qr_code_path1, $ecc, $pixel_Size, $frame_Size);
            $pdf->Image($qr_code_path1, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false);   

            
            //qr code 2   
            $dt = date("_ymdHis");
            $str=$ff_id.$dt;
            $codeContents =$encryptedString = strtoupper(md5($str));
            $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
            $qrCodex = 258;
            $qrCodey = 162.1;
            $qrCodeWidth =16;
            $qrCodeHeight = 16;
            $ecc = 'L';
            $pixel_Size = 4;
            $frame_Size = 1;  
            \PHPQRCode\QRcode::png($codeContents, $qr_code_path, $ecc, $pixel_Size, $frame_Size);
            $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false);   
                
            /*
            \QrCode::backgroundColor(255, 255, 0)            
                ->format('png')        
                ->size(500)    
                ->generate($codeContents, $qr_code_path);
            $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false); 
            */        
            
            /*
            //micro line name
            $microlinestr = strtoupper(str_replace(' ','','Freedom Fighter'));
            $textArray = imagettfbbox(1.4, 0, public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\arial-bold.ttf', $microlinestr);
            $strWidth = ($textArray[2] - $textArray[0]);
            $strHeight = $textArray[6] - $textArray[1] / 1.4;
            
            $latestWidth = 950;
            $wd = '';
            $last_width = 0;
            $message = array();
            for($i=1;$i<=1000;$i++){
                if($i * $strWidth > $latestWidth){
                    $wd = $i * $strWidth;
                    $last_width =$wd - $strWidth;
                    $extraWidth = $latestWidth - $last_width;
                    $stringLength = strlen($microlinestr);
                    $extraCharacter = intval($stringLength * $extraWidth / $strWidth);
                    $message[$i]  = mb_substr($microlinestr, 0,$extraCharacter);
                    break;
                }
                $message[$i] = $microlinestr.'';
            }
            $horizontal_line = array();
            foreach ($message as $key => $value) {
                $horizontal_line[] = $value;
            }        
            $string = implode(',', $horizontal_line);
            $array = str_replace(',', '', $string);   
            //$pdf->SetFont('fonts\ArialB.TTF', '', 1.2);
            //$pdf->SetTextColor(0, 0, 0);
            //$pdf->SetXY(10,31);
            //$pdf->Cell(0, 0, $array, 0, false, 'L');  
            */
            //if($z==1){break;}
            $z+=1; 
        }
        
        $pdf->Output();         
        //exit;


         
      
        //$pdf->Output('sample.pdf', 'I');  
    }

    public function digitToChar($digit){
        switch ($digit) {
          case "0":
            return "ZER";
            break;
          case "1":
            return "ONE";
            break;
          case "2":
            return "TWO";
            break;
          case "3":
            return "THR";
            break;
          case "4":
            return "FOU";
            break;
          case "5":
            return "FIV";
            break;
          case "6":
            return "SIX";
            break;
          case "7":
            return "SEV";
            break;
          case "8":
            return "EIG";
            break;
          case "9":
            return "NIN";
            break;      
          default:
            return "";
        }
    } 
    

     public function validateExcel(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        $template_id=100;
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
         //check file is uploaded or not
        if($request->hasFile('field_file')){
            //check extension
            $file_name = $request['field_file']->getClientOriginalName();
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);

            //excel file name
            $excelfile =  date("YmdHis") . "_" . $file_name;
            $target_path = public_path().'/backend/canvas/dummy_images/'.$template_id;
            $fullpath = $target_path.'/'.$excelfile;

            if(!is_dir($target_path)){
                
                            mkdir($target_path, 0777);
                        }

            if($request['field_file']->move($target_path,$excelfile)){
                //get excel file data
                
                if($ext == 'xlsx' || $ext == 'Xlsx'){
                    $inputFileType = 'Xlsx';
                }
                else{
                    $inputFileType = 'Xls';
                }
                $auth_site_id=Auth::guard('admin')->user()->site_id;

                $systemConfig = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();
                if($get_file_aws_local_flag->file_aws_local == '1'){
                    if($systemConfig['sandboxing'] == 1){
                        $sandbox_directory = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/';
                        //if directory not exist make directory
                        if(!is_dir($sandbox_directory)){
                
                            mkdir($sandbox_directory, 0777);
                        }

                        $aws_excel = \Storage::disk('s3')->put($subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$excelfile, file_get_contents($fullpath), 'public');
                        $filename1 = \Storage::disk('s3')->url($excelfile);
                        
                    }else{
                        
                        $aws_excel = \Storage::disk('s3')->put($subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile, file_get_contents($fullpath), 'public');
                        $filename1 = \Storage::disk('s3')->url($excelfile);
                    }
                }
                else{

                      $sandbox_directory = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/';
                    //if directory not exist make directory
                    if(!is_dir($sandbox_directory)){
            
                        mkdir($sandbox_directory, 0777);
                    }

                    if($systemConfig['sandboxing'] == 1){
                        $aws_excel = \File::copy($fullpath,public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$excelfile);
                        
                    }else{
                        $aws_excel = \File::copy($fullpath,public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile);
                        
                    }
                }
                $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                /**  Load $inputFileName to a Spreadsheet Object  **/
                $objPHPExcel1 = $objReader->load($fullpath);
                $sheet1 = $objPHPExcel1->getSheet(0);
                $highestColumn1 = $sheet1->getHighestColumn();
                $highestRow1 = $sheet1->getHighestDataRow();
                $rowData1 = $sheet1->rangeToArray('A1:' . $highestColumn1 . $highestRow1, NULL, TRUE, FALSE);
                 //For checking certificate limit updated by Mandar
                $recordToGenerate=$highestRow1-1;
                $checkStatus = CoreHelper::checkMaxCertificateLimit($recordToGenerate);
                if(!$checkStatus['status']){
                  return response()->json($checkStatus);
                }
                 $excelData=array('rowData1'=>$rowData1,'auth_site_id'=>$auth_site_id);
                $response = $this->dispatch(new ValidateExcelMONADJob($excelData));

                $responseData =$response->getData();
               
                if($responseData->success){
                    $old_rows=$responseData->old_rows;
                    $new_rows=$responseData->new_rows;
                }else{
                   return $response;
                }
               
            }

            
            if (file_exists($fullpath)) {
                unlink($fullpath);
            }
            
        return response()->json(['success'=>true,'type' => 'success', 'message' => 'success','old_rows'=>$old_rows,'new_rows'=>$new_rows]);

        }
        else{
            return response()->json(['success'=>false,'message'=>'File not found!']);
        }


    }

    public function uploadfile(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $template_id = 100;
       $previewPdf = array($request['previewPdf'],$request['previewWithoutBg']);

        //check file is uploaded or not
        if($request->hasFile('field_file')){
            //check extension
            $file_name = $request['field_file']->getClientOriginalName();
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);

            //excel file name
            $excelfile =  date("YmdHis") . "_" . $file_name;
            $target_path = public_path().'/backend/canvas/dummy_images/'.$template_id;
            $fullpath = $target_path.'/'.$excelfile;

            if(!is_dir($target_path)){
                
                            mkdir($target_path, 0777);
                        }

            if($request['field_file']->move($target_path,$excelfile)){
                //get excel file data
                
                if($ext == 'xlsx' || $ext == 'Xlsx'){
                    $inputFileType = 'Xlsx';
                }
                else{
                    $inputFileType = 'Xls';
                }
                $auth_site_id=Auth::guard('admin')->user()->site_id;

                $systemConfig = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();
                if($get_file_aws_local_flag->file_aws_local == '1'){
                    if($systemConfig['sandboxing'] == 1){
                        $sandbox_directory = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/';
                        //if directory not exist make directory
                        if(!is_dir($sandbox_directory)){
                
                            mkdir($sandbox_directory, 0777);
                        }

                        $aws_excel = \Storage::disk('s3')->put($subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$excelfile, file_get_contents($fullpath), 'public');
                        $filename1 = \Storage::disk('s3')->url($excelfile);
                        
                    }else{
                        
                        $aws_excel = \Storage::disk('s3')->put($subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile, file_get_contents($fullpath), 'public');
                        $filename1 = \Storage::disk('s3')->url($excelfile);
                    }
                }
                else{

                      $sandbox_directory = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/';
                    //if directory not exist make directory
                    if(!is_dir($sandbox_directory)){
            
                        mkdir($sandbox_directory, 0777);
                    }

                    if($systemConfig['sandboxing'] == 1){
                        $aws_excel = \File::copy($fullpath,public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$excelfile);
                        
                        
                    }else{
                        $aws_excel = \File::copy($fullpath,public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile);
                        
                    }
                }
                $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                /**  Load $inputFileName to a Spreadsheet Object  **/
                $objPHPExcel1 = $objReader->load($fullpath);
                $sheet1 = $objPHPExcel1->getSheet(0);
                $highestColumn1 = $sheet1->getHighestColumn();
                $highestRow1 = $sheet1->getHighestDataRow();
                $rowData1 = $sheet1->rangeToArray('A1:' . $highestColumn1 . $highestRow1, NULL, TRUE, FALSE);
                
                
                 }
               
            
                       
        }
        else{
            return response()->json(['success'=>false,'message'=>'File not found!']);
        }

     
        unset($rowData1[0]);
        $rowData1=array_values($rowData1);
        //store ghost image
        $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
        $admin_id = \Auth::guard('admin')->user()->toArray();

        
        $pdfData=array('studentDataOrg'=>$rowData1,'auth_site_id'=>$auth_site_id,'template_id'=>$template_id,'previewPdf'=>$previewPdf,'excelfile'=>$excelfile);
                $link = $this->dispatch(new PdfGenerateMONADJob($pdfData));
        
        return response()->json(['success'=>true,'message'=>'Certificates generated successfully.','link'=>$link]);
    }
    
    
 
    public function addCertificate($serial_no, $certName, $dt,$template_id,$admin_id,$blob)
    {

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $file1 = public_path().'/backend/temp_pdf_file/'.$certName;
        $file2 = public_path().'/backend/pdf_file/'.$certName;
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

        $pdfActualPath=public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName;

        
        copy($file1, $file2);
        
        $aws_qr = \File::copy($file2,$pdfActualPath);
                
            
        @unlink($file2);

        @unlink($file1);

       

        $sts = '1';
        $datetime  = date("Y-m-d H:i:s");
        $ses_id  = $admin_id["id"];
        $certName = str_replace("/", "_", $certName);

        $get_config_data = Config::select('configuration')->first();
     
        $c = explode(", ", $get_config_data['configuration']);
        $key = "";


        $tempDir = public_path().'/backend/qr';
        $key = strtoupper(md5($serial_no)); 
        $codeContents = $key;
        $fileName = $key.'.png'; 
        
        $urlRelativeFilePath = 'qr/'.$fileName; 

        if($systemConfig['sandboxing'] == 1){
        $resultu = SbStudentTable::where('serial_no','T-'.$serial_no)->update(['status'=>'0']);
        // Insert the new record
        
        $result = SbStudentTable::create(['serial_no'=>'T-'.$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        }else{
        $resultu = StudentTable::where('serial_no',$serial_no)->update(['status'=>'0']);
        // Insert the new record
        
        $result = StudentTable::create(['serial_no'=>$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        }
        
    }

    public function getPrintCount($serial_no)
    {
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $numCount = PrintingDetail::select('id')->where('sr_no',$serial_no)->count();
        
        return $numCount + 1;
    }

    public function addPrintDetails($username, $print_datetime, $printer_name, $printer_count, $print_serial_no, $sr_no,$template_name,$admin_id,$card_serial_no)
    {
        $sts = 1;
        $datetime = date("Y-m-d H:i:s");
        $ses_id = $admin_id["id"];

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

        if($systemConfig['sandboxing'] == 1){
        $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>'T-'.$sr_no,'template_name'=>$template_name,'card_serial_no'=>$card_serial_no,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);
        }else{
        $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$sr_no,'template_name'=>$template_name,'card_serial_no'=>$card_serial_no,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);    
        }
    }

    public function nextPrintSerial()
    {
        $current_year = 'PN/' . $this->getFinancialyear() . '/';
        // find max
        $maxNum = 0;
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $result = \DB::select("SELECT COALESCE(MAX(CONVERT(SUBSTR(print_serial_no, 10), UNSIGNED)), 0) AS next_num "
                . "FROM printing_details WHERE SUBSTR(print_serial_no, 1, 9) = '$current_year'");
        //get next num
        $maxNum = $result[0]->next_num + 1;
        
        return $current_year . $maxNum;
    }

    public function getNextCardNo($template_name)
    { 
        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

        if($systemConfig['sandboxing'] == 1){
        $result = \DB::select("SELECT * FROM sb_card_serial_numbers WHERE template_name = '$template_name'");
        }else{
        $result = \DB::select("SELECT * FROM card_serial_numbers WHERE template_name = '$template_name'");
        }
          
        return $result[0];
    }

    public function updateCardNo($template_name,$count,$next_serial_no)
    { 
        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        if($systemConfig['sandboxing'] == 1){
        $result = \DB::select("UPDATE sb_card_serial_numbers SET card_count='$count',next_serial_no='$next_serial_no' WHERE template_name = '$template_name'");
        }else{
        $result = \DB::select("UPDATE card_serial_numbers SET card_count='$count',next_serial_no='$next_serial_no' WHERE template_name = '$template_name'");
        }
        
        return $result;
    }


    public function getFinancialyear()
    {
        $yy = date('y');
        $mm = date('m');
        $fy = str_pad($yy, 2, "0", STR_PAD_LEFT);
        if($mm > 3)
            $fy = $fy . "-" . ($yy + 1);
        else
            $fy = str_pad($yy - 1, 2, "0", STR_PAD_LEFT) . "-" . $fy;
        return $fy;
    }

    public function createTemp($path){
        //create ghost image folder
        $tmp = date("ymdHis");
        
        $tmpname = tempnam($path, $tmp);
        unlink($tmpname);
        mkdir($tmpname);
        return $tmpname;
    }

    public function CreateMessage($tmpDir, $name = "",$font_size,$print_color)
    {
        if($name == "")
            return;
        $name = strtoupper($name);
        // Create character image
        if($font_size == 15 || $font_size == "15"){
            $AlphaPosArray = array(
                "A" => array(0, 825),
                "B" => array(825, 840),
                "C" => array(1665, 824),
                "D" => array(2489, 856),
                "E" => array(3345, 872),
                "F" => array(4217, 760),
                "G" => array(4977, 848),
                "H" => array(5825, 896),
                "I" => array(6721, 728),
                "J" => array(7449, 864),
                "K" => array(8313, 840),
                "L" => array(9153, 817),
                "M" => array(9970, 920),
                "N" => array(10890, 728),
                "O" => array(11618, 944),
                "P" => array(12562, 736),
                "Q" => array(13298, 920),
                "R" => array(14218, 840),
                "S" => array(15058, 824),
                "T" => array(15882, 816),
                "U" => array(16698, 800),
                "V" => array(17498, 841),
                "W" => array(18339, 864),
                "X" => array(19203, 800),
                "Y" => array(20003, 824),
                "Z" => array(20827, 876),
                "0" => array(21703, 850),
                "1" => array(22553, 850),
                "2" => array(23403, 850),
                "3" => array(24253, 850),
                "4" => array(25103, 850),
                "5" => array(25953, 850),
                "6" => array(26803, 850),
                "7" => array(27653, 850),
                "8" => array(28503, 850),
                "9" => array(29353, 635)
            );

            $filename = public_path()."/backend/canvas/ghost_images/F15_H14_W504.png";

            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            // Create Backgoround image
            $filename   = public_path()."/backend/canvas/ghost_images/alpha_GHOST.png";
            $bgImage = imagecreatefrompng($filename);
            $currentX = 0;
            $len = strlen($name);
            
            for($i = 0; $i < $len; $i++) {
                $value = $name[$i];
                if(!array_key_exists($value, $AlphaPosArray))
                    continue;
                $X = $AlphaPosArray[$value][0];
                $W = $AlphaPosArray[$value][1];
                
                imagecopymerge($bgImage, $charsImage, $currentX, 0, $X, 0, $W, $size[1], 100);
                $currentX += $W;
            }

            $rect = array("x" => 0, "y" => 0, "width" => $currentX, "height" => $size[1]);
            $im = imagecrop($bgImage, $rect);
            
            imagepng($im, "$tmpDir/" . $name."".$font_size.".png");
            imagedestroy($bgImage);
            imagedestroy($charsImage);
            return round((14 * $currentX)/ $size[1]);

        }else if($font_size == 12){

            $AlphaPosArray = array(
                "A" => array(0, 849),
                "B" => array(849, 864),
                "C" => array(1713, 840),
                "D" => array(2553, 792),
                "E" => array(3345, 872),
                "F" => array(4217, 776),
                "G" => array(4993, 832),
                "H" => array(5825, 880),
                "I" => array(6705, 744),
                "J" => array(7449, 804),
                "K" => array(8273, 928),
                "L" => array(9201, 776),
                "M" => array(9977, 920),
                "N" => array(10897, 744),
                "O" => array(11641, 864),
                "P" => array(12505, 808),
                "Q" => array(13313, 804),
                "R" => array(14117, 904),
                "S" => array(15021, 832),
                "T" => array(15853, 816),
                "U" => array(16669, 824),
                "V" => array(17493, 800),
                "W" => array(18293, 909),
                "X" => array(19202, 800),
                "Y" => array(20002, 840),
                "Z" => array(20842, 792),
                "0" => array(21634, 850),
                "1" => array(22484, 850),
                "2" => array(23334, 850),
                "3" => array(24184, 850),
                "4" => array(25034, 850),
                "5" => array(25884, 850),
                "6" => array(26734, 850),
                "7" => array(27584, 850),
                "8" => array(28434, 850),
                "9" => array(29284, 700)            
            );
                
                $filename = public_path()."/backend/canvas/ghost_images/F12_H8_W288.png";
            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            // Create Backgoround image
            $filename   = public_path()."/backend/canvas/ghost_images/alpha_GHOST.png";
            $bgImage = imagecreatefrompng($filename);
            $currentX = 0;
            $len = strlen($name);
            
            for($i = 0; $i < $len; $i++) {
                $value = $name[$i];
                if(!array_key_exists($value, $AlphaPosArray))
                    continue;
                $X = $AlphaPosArray[$value][0];
                $W = $AlphaPosArray[$value][1];
                
                imagecopymerge($bgImage, $charsImage, $currentX, 0, $X, 0, $W, $size[1], 100);
                $currentX += $W;
            }

            $rect = array("x" => 0, "y" => 0, "width" => $currentX, "height" => $size[1]);
            $im = imagecrop($bgImage, $rect);
            
            imagepng($im, "$tmpDir/" . $name."".$font_size.".png");
            imagedestroy($bgImage);
            imagedestroy($charsImage);
            return round((8 * $currentX)/ $size[1]);

        }else if($font_size == "10" || $font_size == 10){
            $AlphaPosArray = array(
                "A" => array(0, 700),
                "B" => array(700, 757),
                "C" => array(1457, 704),
                "D" => array(2161, 712),
                "E" => array(2873, 672),
                "F" => array(3545, 664),
                "G" => array(4209, 752),
                "H" => array(4961, 744),
                "I" => array(5705, 616),
                "J" => array(6321, 736),
                "K" => array(7057, 784),
                "L" => array(7841, 673),
                "M" => array(8514, 752),
                "N" => array(9266, 640),
                "O" => array(9906, 760),
                "P" => array(10666, 664),
                "Q" => array(11330, 736),
                "R" => array(12066, 712),
                "S" => array(12778, 664),
                "T" => array(13442, 723),
                "U" => array(14165, 696),
                "V" => array(14861, 696),
                "W" => array(15557, 745),
                "X" => array(16302, 680),
                "Y" => array(16982, 728),
                "Z" => array(17710, 680),
                "0" => array(18310, 725),
                "1" => array(19035, 725),
                "2" => array(19760, 725),
                "3" => array(20485, 725),
                "4" => array(21210, 725),
                "5" => array(21935, 725),
                "6" => array(22660, 725),
                "7" => array(23385, 725),
                "8" => array(24110, 725),
                "9" => array(24835, 630)                
            );
            
            $filename = public_path()."/backend/canvas/ghost_images/F10_H5_W180.png";
            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            // Create Backgoround image
            $filename   = public_path()."/backend/canvas/ghost_images/alpha_GHOST.png";
            $bgImage = imagecreatefrompng($filename);
            $currentX = 0;
            $len = strlen($name);
            
            for($i = 0; $i < $len; $i++) {
                $value = $name[$i];
                if(!array_key_exists($value, $AlphaPosArray))
                    continue;
                $X = $AlphaPosArray[$value][0];
                $W = $AlphaPosArray[$value][1];
                imagecopymerge($bgImage, $charsImage, $currentX, 0, $X, 0, $W, $size[1], 100);
                $currentX += $W;
            }
            
            $rect = array("x" => 0, "y" => 0, "width" => $currentX, "height" => $size[1]);
            $im = imagecrop($bgImage, $rect);
           
            imagepng($im, "$tmpDir/" . $name."".$font_size.".png");
            imagedestroy($bgImage);
            imagedestroy($charsImage);
            return round((5 * $currentX)/ $size[1]);

        }else if($font_size == 11){

            $AlphaPosArray = array(
                "A" => array(0, 833),
                "B" => array(833, 872),
                "C" => array(1705, 800),
                "D" => array(2505, 888),
                "E" => array(3393, 856),
                "F" => array(4249, 760),
                "G" => array(5009, 856),
                "H" => array(5865, 896),
                "I" => array(6761, 744),
                "J" => array(7505, 832),
                "K" => array(8337, 887),
                "L" => array(9224, 760),
                "M" => array(9984, 920),
                "N" => array(10904, 789),
                "O" => array(11693, 896),
                "P" => array(12589, 776),
                "Q" => array(13365, 904),
                "R" => array(14269, 784),
                "S" => array(15053, 872),
                "T" => array(15925, 776),
                "U" => array(16701, 832),
                "V" => array(17533, 824),
                "W" => array(18357, 872),
                "X" => array(19229, 806),
                "Y" => array(20035, 832),
                "Z" => array(20867, 848),
                "0" => array(21715, 850),
                "1" => array(22565, 850),
                "2" => array(23415, 850),
                "3" => array(24265, 850),
                "4" => array(25115, 850),
                "5" => array(25695, 850),
                "6" => array(26815, 850),
                "7" => array(27665, 850),
                "8" => array(28515, 850),
                "9" => array(29365, 610)
            );
                
                $filename = public_path()."/backend/canvas/ghost_images/F11_H7_W250.png";
            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            // Create Backgoround image
            $filename   = public_path()."/backend/canvas/ghost_images/alpha_GHOST.png";
            $bgImage = imagecreatefrompng($filename);
            $currentX = 0;
            $len = strlen($name);
            
            for($i = 0; $i < $len; $i++) {
                $value = $name[$i];
                if(!array_key_exists($value, $AlphaPosArray))
                    continue;
                $X = $AlphaPosArray[$value][0];
                $W = $AlphaPosArray[$value][1];
                
                imagecopymerge($bgImage, $charsImage, $currentX, 0, $X, 0, $W, $size[1], 100);
                $currentX += $W;
            }

            $rect = array("x" => 0, "y" => 0, "width" => $currentX, "height" => $size[1]);
            $im = imagecrop($bgImage, $rect);
            

            imagepng($im, "$tmpDir/" . $name."".$font_size.".png");
            imagedestroy($bgImage);
            imagedestroy($charsImage);
            return round((7 * $currentX)/ $size[1]);

        }else if($font_size == "13" || $font_size == 13){

            $AlphaPosArray = array(
                "A" => array(0, 865),
                "B" => array(865, 792),
                "C" => array(1657, 856),
                "D" => array(2513, 888),
                "E" => array(3401, 768),
                "F" => array(4169, 864),
                "G" => array(5033, 824),
                "H" => array(5857, 896),
                "I" => array(6753, 784),
                "J" => array(7537, 808),
                "K" => array(8345, 877),
                "L" => array(9222, 664),
                "M" => array(9886, 976),
                "N" => array(10862, 832),
                "O" => array(11694, 856),
                "P" => array(12550, 776),
                "Q" => array(13326, 896),
                "R" => array(14222, 816),
                "S" => array(15038, 784),
                "T" => array(15822, 816),
                "U" => array(16638, 840),
                "V" => array(17478, 794),
                "W" => array(18272, 920),
                "X" => array(19192, 808),
                "Y" => array(20000, 880),
                "Z" => array(20880, 800),
                "0" => array(21680, 850),
                "1" => array(22530, 850),
                "2" => array(23380, 850),
                "3" => array(24230, 850),
                "4" => array(25080, 850),
                "5" => array(25930, 850),
                "6" => array(26780, 850),
                "7" => array(27630, 850),
                "8" => array(28480, 850),
                "9" => array(29330, 670)
            
            );

                
            $filename = public_path()."/backend/canvas/ghost_images/F13_H10_W360.png";
            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            // Create Backgoround image
            $filename   = public_path()."/backend/canvas/ghost_images/alpha_GHOST.png";
            $bgImage = imagecreatefrompng($filename);
            $currentX = 0;
            $len = strlen($name);
            
            for($i = 0; $i < $len; $i++) {
                $value = $name[$i];
                if(!array_key_exists($value, $AlphaPosArray))
                    continue;
                $X = $AlphaPosArray[$value][0];
                $W = $AlphaPosArray[$value][1];
                
                imagecopymerge($bgImage, $charsImage, $currentX, 0, $X, 0, $W, $size[1], 100);
                $currentX += $W;
            }

            $rect = array("x" => 0, "y" => 0, "width" => $currentX, "height" => $size[1]);
            
            $im = imagecrop($bgImage, $rect);
            
            imagepng($im, "$tmpDir/" . $name."".$font_size.".png");
            imagedestroy($bgImage);
            imagedestroy($charsImage);
            return round((10 * $currentX)/ $size[1]);

        }else if($font_size == "14" || $font_size == 14){

            $AlphaPosArray = array(
                "A" => array(0, 833),
                "B" => array(833, 872),
                "C" => array(1705, 856),
                "D" => array(2561, 832),
                "E" => array(3393, 832),
                "F" => array(4225, 736),
                "G" => array(4961, 892),
                "H" => array(5853, 940),
                "I" => array(6793, 736),
                "J" => array(7529, 792),
                "K" => array(8321, 848),
                "L" => array(9169, 746),
                "M" => array(9915, 1024),
                "N" => array(10939, 744),
                "O" => array(11683, 864),
                "P" => array(12547, 792),
                "Q" => array(13339, 848),
                "R" => array(14187, 872),
                "S" => array(15059, 808),
                "T" => array(15867, 824),
                "U" => array(16691, 872),
                "V" => array(17563, 736),
                "W" => array(18299, 897),
                "X" => array(19196, 808),
                "Y" => array(20004, 880),
                "Z" => array(80884, 808),
                "0" => array(21692, 825),
                "1" => array(22517, 825),
                "2" => array(23342, 825),
                "3" => array(24167, 825),
                "4" => array(24992, 825),
                "5" => array(25817, 825),
                "6" => array(26642, 825),
                "7" => array(27467, 825),
                "8" => array(28292, 825),
                "9" => array(29117, 825)
            
            );
                
                $filename = public_path()."/backend/canvas/ghost_images/F14_H12_W432.png";
            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            // Create Backgoround image
            $filename   = public_path()."/backend/canvas/ghost_images/alpha_GHOST.png";
            $bgImage = imagecreatefrompng($filename);
            $currentX = 0;
            $len = strlen($name);
            
            for($i = 0; $i < $len; $i++) {
                $value = $name[$i];
                if(!array_key_exists($value, $AlphaPosArray))
                    continue;
                $X = $AlphaPosArray[$value][0];
                $W = $AlphaPosArray[$value][1];
                
                imagecopymerge($bgImage, $charsImage, $currentX, 0, $X, 0, $W, $size[1], 100);
                $currentX += $W;
            }

            $rect = array("x" => 0, "y" => 0, "width" => $currentX, "height" => $size[1]);
            $im = imagecrop($bgImage, $rect);
            
            imagepng($im, "$tmpDir/" . $name."".$font_size.".png");
            imagedestroy($bgImage);
            imagedestroy($charsImage);
            return round((12 * $currentX)/ $size[1]);

        }else{
            $AlphaPosArray = array(
                "A" => array(0, 944),
                "B" => array(943, 944),
                "C" => array(1980, 944),
                "D" => array(2923, 944),
                "E" => array(3897, 944),
                "F" => array(4840, 753),
                "G" => array(5657, 943),
                "H" => array(6694, 881),
                "I" => array(7668, 504),
                "J" => array(8265, 692),
                "K" => array(9020, 881),
                "L" => array(9899, 944),
                "M" => array(10842, 944),
                "N" => array(11974, 724),
                "O" => array(12916, 850),
                "P" => array(13859, 850),
                "Q" => array(14802, 880),
                "R" => array(15776, 944),
                "S" => array(16719, 880),
                "T" => array(17599, 880),
                "U" => array(18479, 880),
                "V" => array(19485, 880),
                "W" => array(20396, 1038),
                "X" => array(21465, 944),
                "Y" => array(22407, 880),
                "Z" => array(23287, 880)
            );  

            $filename = public_path()."/backend/canvas/ghost_images/ALPHA_GHOST.png";
            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);

            // Create Backgoround image
            $filename   = public_path()."/backend/canvas/ghost_images/alpha_GHOST.png";
            $bgImage = imagecreatefrompng($filename);
            $currentX = 0;
            $len = strlen($name);
            
            for($i = 0; $i < $len; $i++) {
                $value = $name[$i];
                if(!array_key_exists($value, $AlphaPosArray))
                    continue;
                $X = $AlphaPosArray[$value][0];
                $W = $AlphaPosArray[$value][1];
                imagecopymerge($bgImage, $charsImage, $currentX, 0, $X, 0, $W, $size[1], 100);
                $currentX += $W;
            }

            $rect = array("x" => 0, "y" => 0, "width" => $currentX, "height" => $size[1]);
            $im = imagecrop($bgImage, $rect);
            
            imagepng($im, "$tmpDir/" . $name."".$font_size.".png");
            imagedestroy($bgImage);
            imagedestroy($charsImage);
            return round((10 * $currentX)/ $size[1]);
        }
    }

    function GetStringPositions($strings,$pdf)
    {
        $len = count($strings);
        $w = array();
        $sum = 0;
        foreach ($strings as $key => $str) {
            $width = $pdf->GetStringWidth($str[0], $str[1], $str[2], $str[3], false);
            $w[] = $width;
            $sum += intval($width);
            
        }
        
        $ret = array();
        $ret[0] = (205 - $sum)/2;
        for($i = 1; $i < $len; $i++)
        {
            $ret[$i] = $ret[$i - 1] + $w[$i - 1] ;
            
        }
        
        return $ret;
    }

    function sanitizeQrString($content){
         $find = array('â€œ', 'â€™', 'â€¦', 'â€”', 'â€“', 'â€˜', 'Ã©', 'Â', 'â€¢', 'Ëœ', 'â€'); // en dash
         $replace = array('“', '’', '…', '—', '–', '‘', 'é', '', '•', '˜', '”');
        return $content = str_replace($find, $replace, $content);
    }
    
    public function importData(){
        Session::forget('progressFile');
        Session::save();
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain);        
        $filename='progress_'.uniqid().'.txt';
        $file = public_path().'\\'.$subdomain[0].'\\progess_files\\'.$filename;              
        if (!file_exists($file)) {
            $myfile=fopen($file, "w");
            $txt= array('percent'=>0, 'show_msg'=>'');
            fwrite($myfile, json_encode($txt, JSON_PRETTY_PRINT));          
            //echo "File created.".$file;
        }
        Session::put('progressFile', $filename);
        Session::save();            
        $apiKey="ZXlKcGRpSTZJa0ZLZDF3dlptbFBUMVJOY2xOek1qbEJTWEZRUkdkUlBUMGlMQ0oyWVd4MVpTSTZJbWxJUkZOak1UUXhlbGdyVm1wdk1rSXJVVzB5WmpkSmRqbE1NR2xqYVVkb01sYzFjMmN4WEM5RGIwVTViMlJGVGtwMFNrTXdjR04zVkcxQlNIaGFjMjVrYjFWaFRtMHpSamRLVG5aYU1VbGhZMEZPVW5GT1FUMDlJaXdpYldGaklqb2lNemN4T0RrMk9EbGtZekl3T1RBeVlUYzBZamszTkdGa05EaGtZakl4Wm1JeVpqRTRNMlUzTTJOak9HTTJPRFkwTURZMk56VTFNVE00TVdFMFlqVmpNQ0o5";        
        $params = "per_page=1&page_no=1";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,"http://mis.molwa.gov.bd/api/ff_list?".$params);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('api_key: ' . $apiKey));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        curl_close ($ch);      
        $json = json_decode(json_encode($server_output), true);
        $object = json_decode( $json, true ); 
        $total_record=$object['total_record'];
        $per_page=50;
        $total_pages=ceil($total_record/$per_page);
        $total_pages=1;        
        //print_r($object['records']);        
        //echo $total_pages;        
        //exit;
        
        $cnt=1;
        for ($x = 1; $x <= $total_pages; $x++) {
            //echo $x."<br>";
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL,"http://mis.molwa.gov.bd/api/ff_list?per_page=".$per_page."&page_no=".$x);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, array('api_key: ' . $apiKey));
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            $server_output2 = curl_exec($ch2);
            curl_close ($ch2);      
            $json2 = json_decode(json_encode($server_output2), true);
            $object2 = json_decode( $json2, true );             
            if (is_array($object2['records']) || is_object($object2['records'])){
                $input=array();
                foreach ($object2['records'] as $value){  
                    $input['ff_id'] = $value['ff_id']; 
                    $input['ff_name'] = $value['ff_name']; 
                    $input['father_or_husband_name'] = $value['father_or_husband_name'];
                    $input['mother_name'] = $value['mother_name']; 
                    $input['post_office'] = $value['post_office'];
                    $input['post_code'] = $value['post_code']; 
                    $input['district'] = $value['district']; 
                    $input['upazila_or_thana'] = $value['upazila_or_thana']; 
                    $input['village_or_ward'] = $value['village_or_ward'];
                    $input['nid'] = $value['nid']; 
                    $input['ghost_image_code'] = $value['ghost_image_code']; 
                    $input['ff_photo'] = $value['ff_photo'];       
                    $input['created_at'] = now();    
                    $input['updated_at'] = now();    
                    //FreedomFighterList::create($input);
                    //$percent = ($cnt/$total_record) * 100;
                    $show_msg = "Importing ".$cnt."/".$total_record." record(s)";
                    $percent = ($cnt/50) * 100;
                    if (file_exists($file)) {
                        $myfile=fopen($file, "w");
                        $txt=array('percent'=>$percent, 'show_msg'=>$show_msg);
                        fwrite($myfile, json_encode($txt, JSON_PRETTY_PRINT));
                        sleep(1);
                        //echo $file;
                    }                    
                    //echo $cnt.", ".$percent." | ";
                    //Session::put('percentage', round($percent,2));
                    //Session::save();
                    $cnt++;
                }
            }
                            
        }     
            $myfile=file_get_contents($file, true);
            $json2 = json_decode(json_encode($myfile), true);
            $object2 = json_decode( $json2, true );    //print_r($json2); print_r($object2['percent']);     
        return response()->json(['success'=>true, 'message'=>$total_record.' records imported successfully.']);
    }
    public function getProgess(){
        $filename = Session::get('progressFile');
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain);           
        $file = public_path().'\\'.$subdomain[0].'\\progess_files\\'.$filename;              
        if (file_exists($file)) {
            $myfile=file_get_contents($file, true);
            $json2 = json_decode(json_encode($myfile), true);
            $object2 = json_decode( $json2, true );  
            $percent=round($object2['percent'],2);
            $show_msg=$object2['show_msg'];
        }
        //if($percent == 100 ){Session::forget('progressFile');Session::save();}
        //return response()->json($myfile);
        return response()->json(['percent'=>$percent, 'show_msg'=>$show_msg]);
    }
    public function ImportPrint(Request $request){
        $imported_count = ImportLogDetail::sum('fresh_records'); 
        $id_generated_count = ImportLogDetail::where('idcard_status', 'GENERATED')->sum('fresh_records');             
        $c_generated_count = ImportLogDetail::where('certificate_status', 'GENERATED')->sum('fresh_records');             
        $generated_count = $id_generated_count."/".$c_generated_count; 

        $id_completed_count = ImportLogDetail::where('idcard_status', 'COMPLETED')->sum('fresh_records');             
        $c_completed_count = ImportLogDetail::where('certificate_status', 'COMPLETED')->sum('fresh_records');             
        $completed_count = $id_completed_count."/".$c_completed_count;     
        
        $apiKey="ZXlKcGRpSTZJa0ZLZDF3dlptbFBUMVJOY2xOek1qbEJTWEZRUkdkUlBUMGlMQ0oyWVd4MVpTSTZJbWxJUkZOak1UUXhlbGdyVm1wdk1rSXJVVzB5WmpkSmRqbE1NR2xqYVVkb01sYzFjMmN4WEM5RGIwVTViMlJGVGtwMFNrTXdjR04zVkcxQlNIaGFjMjVrYjFWaFRtMHpSamRLVG5aYU1VbGhZMEZPVW5GT1FUMDlJaXdpYldGaklqb2lNemN4T0RrMk9EbGtZekl3T1RBeVlUYzBZamszTkdGa05EaGtZakl4Wm1JeVpqRTRNMlUzTTJOak9HTTJPRFkwTURZMk56VTFNVE00TVdFMFlqVmpNQ0o5";        
        $params = "per_page=1&page_no=1";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,"http://mis.molwa.gov.bd/api/ff_list?".$params);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('api_key: ' . $apiKey));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        curl_close ($ch);      
        $json = json_decode(json_encode($server_output), true);
        $object = json_decode( $json, true ); 
        $total_record=$object['total_record'];    	
        if($request->ajax()){
            $where_str    = "1 = ?";
            //$where_str    .= " AND publish = '1'";
            $where_params = array(1); 

            //for seraching the keyword in datatable
            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and ( created_at like \"%{$search}%\""
                . " or name like \"%{$search}%\""
                /*. " or ff_name like \"%{$search}%\""
                . " or father_or_husband_name like \"%{$search}%\""
                . " or mother_name like \"%{$search}%\""
                . " or post_office like \"%{$search}%\""*/
                . ")";
            } 
            $auth_site_id=Auth::guard('admin')->user()->site_id;
            //for serial number
            $iDisplayStart=$request->input('iDisplayStart'); 
            DB::statement(DB::raw('set @rownum='.$iDisplayStart));
            //DB::statement(DB::raw('set @rownum=0'));

            //column that we wants to display in datatable
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'), 'id', 'created_at', 'name', 'per_page', 'page_no', 'fresh_records', 'repeat_records', 'idcard_status', 'certificate_status', 'record_unique_id', 'idcard_file', 'certificate_file'];
            
            $ffl_count = ImportLogDetail::select($columns)
            ->whereRaw($where_str, $where_params)
            //->where('site_id',$auth_site_id)
            ->count();

            //get list
            $ffl_list = ImportLogDetail::select($columns)
            ->whereRaw($where_str, $where_params);
            //->where('site_id',$auth_site_id);

            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $ffl_list = $ffl_list->take($request->input('iDisplayLength'))
                ->skip($request->input('iDisplayStart'));
            }          

            //sorting the data column wise
            if($request->input('iSortCol_0')){
                $sql_order='';
                for ( $i = 0; $i < $request->input('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->input('iSortCol_' . $i)];
                    if(false !== ($index = strpos($column, ' as '))){
                        $column = substr($column, 0, $index);
                    }
                    $ffl_list = $ffl_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            }
            $ffl_list = $ffl_list->get();

            $response['iTotalDisplayRecords'] = $ffl_count;
            $response['iTotalRecords'] = $ffl_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $ffl_list->toArray();

            return $response;
        }        
        return view('admin.molwa.import',compact(['total_record', 'imported_count', 'generated_count', 'completed_count']));
    }
    public function importFfData(Request $request){
        $admin_id=Auth::guard('admin')->user()->id;
        $api_option=$request['api_option'];
        $log_name=$request['log_name'];
        $per_page=$request['per_page'];
        $page_no=$request['page_no'];
        $record_unique_id = date('dmYHis').'_'.uniqid();
        $statement = DB::select("SHOW TABLE STATUS LIKE 'import_log_details'");
        $nextId = $statement[0]->Auto_increment;
            Session::forget('progressFile');
            Session::save();
        if($api_option==2){  
            $domain = \Request::getHost();        
            $subdomain = explode('.', $domain);        
            $filename='progress_'.uniqid().'.txt';
            $file = public_path().'\\'.$subdomain[0].'\\progess_files\\'.$filename;              
            if (!file_exists($file)) {
                $myfile=fopen($file, "w");
                $txt= array('percent'=>0, 'show_msg'=>'');
                fwrite($myfile, json_encode($txt, JSON_PRETTY_PRINT));          
                //echo "File created.".$file;
            }
            Session::put('progressFile', $filename);
            Session::save();            
            $apiKey="ZXlKcGRpSTZJa0ZLZDF3dlptbFBUMVJOY2xOek1qbEJTWEZRUkdkUlBUMGlMQ0oyWVd4MVpTSTZJbWxJUkZOak1UUXhlbGdyVm1wdk1rSXJVVzB5WmpkSmRqbE1NR2xqYVVkb01sYzFjMmN4WEM5RGIwVTViMlJGVGtwMFNrTXdjR04zVkcxQlNIaGFjMjVrYjFWaFRtMHpSamRLVG5aYU1VbGhZMEZPVW5GT1FUMDlJaXdpYldGaklqb2lNemN4T0RrMk9EbGtZekl3T1RBeVlUYzBZamszTkdGa05EaGtZakl4Wm1JeVpqRTRNMlUzTTJOak9HTTJPRFkwTURZMk56VTFNVE00TVdFMFlqVmpNQ0o5";        
            $params = "per_page=$per_page&page_no=$page_no";            
            $total_record=$per_page;
            $total_pages=1; 
            $cnt=1;
            $fresh=1;
            $repeat=1;
            
            //echo $x."<br>";
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL,"http://mis.molwa.gov.bd/api/ff_list?per_page=".$per_page."&page_no=".$page_no);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, array('api_key: ' . $apiKey));
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            $server_output2 = curl_exec($ch2);
            curl_close ($ch2);      
            $json2 = json_decode(json_encode($server_output2), true);
            $object2 = json_decode( $json2, true );             
            if (is_array($object2['records']) || is_object($object2['records'])){
                $input=array();
                $input_log=array();
                foreach ($object2['records'] as $value)
                {  
                    $input['ff_id'] = $value['ff_id']; 
                    $input['ff_name'] = $value['ff_name']; 
                    $input['father_or_husband_name'] = $value['father_or_husband_name'];
                    $input['mother_name'] = $value['mother_name']; 
                    $input['post_office'] = $value['post_office'];
                    $input['post_code'] = $value['post_code']; 
                    $input['district'] = $value['district']; 
                    $input['upazila_or_thana'] = $value['upazila_or_thana']; 
                    $input['village_or_ward'] = $value['village_or_ward'];
                    $input['nid'] = $value['nid']; 
                    $input['ghost_image_code'] = $value['ghost_image_code']; 
                    $input['ff_photo'] = $value['ff_photo'];       
                    $input['ffl_id'] = $nextId;
                    $input['record_unique_id'] = $record_unique_id;
                    $input['created_at'] = now();    
                    $input['updated_at'] = (NULL);    
                    $input['generated_at'] = (NULL);
                    $input['completed_at'] = (NULL);
                    $input['reimported_at'] = (NULL);
                    $input['admin_id'] = $admin_id;
                    if (FreedomFighterList::where('ff_id',$value['ff_id'])->count() > 0){
                        //ff_id found
                        $repeat++;
                        
                    }else{
                        FreedomFighterList::create($input);
                        $fresh++;
                    }
                    $percent = ($cnt/$total_record) * 100;
                    $show_msg = "Importing ".$cnt."/".$total_record." record(s)";
                    //$percent = ($cnt/50) * 100;
                    if (file_exists($file)) {
                        $myfile=fopen($file, "w");
                        $txt=array('percent'=>$percent, 'show_msg'=>$show_msg);
                        fwrite($myfile, json_encode($txt, JSON_PRETTY_PRINT));
                        sleep(1);
                    }
                    $cnt++;
                }
                $repeat_records=$repeat-1;
                $fresh_records=$fresh-1;
                
                $input_log['name'] = $log_name;
                $input_log['per_page'] = $per_page;
                $input_log['page_no'] = $page_no;
                $input_log['fresh_records'] = $fresh_records;
                $input_log['repeat_records'] = $repeat_records;
                $input_log['idcard_status'] = 'IMPORTED';
                $input_log['certificate_status'] = 'IMPORTED';                
                $input_log['record_unique_id'] = $record_unique_id;
                $input_log['created_at'] = now();
                $input_log['updated_at'] = (NULL);
                $input_log['generated_at'] = (NULL);
                $input_log['completed_at'] = (NULL);
                $input_log['reimported_at'] = (NULL);
                $input_log['admin_id'] = $admin_id;
                ImportLogDetail::create($input_log);
                //echo $repeat_records.", ".$fresh_records." | ";
            }             
                 
            return response()->json(['success'=>true, 'message'=>$total_record.' records imported successfully.', 'fresh_records'=>$fresh_records, 'repeat_records'=>$repeat_records]);
        } else{
            return response()->json(['success'=>false, 'message'=>'Something went wrong.']);
        }
    }
    
    public function ExportToExcel(Request $request, $id)
    {
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain); 
        $data = FreedomFighterList::where('record_unique_id',$id)->get();        
        $html_tbl="<table border='1'>";
        $html_tbl .="<tr>";
        $html_tbl .="
        <td><strong>ID</strong></td>
        <td><strong>Name</strong></td>
        <td><strong>Father/Husband</strong></td>
        <td><strong>Mother</strong></td>
        <td><strong>Post Office</strong></td>
        <td><strong>Post Code</strong></td>
        <td><strong>District</strong></td>
        <td><strong>Upazila/Thana</strong></td>
        <td><strong>Village/Ward</strong></td>
        <td><strong>NID</strong></td>        
        <td><strong>Ghost Image Code</strong></td>
        <td><strong>Photo</strong></td>
        ";
        $html_tbl .="</tr>";        
        foreach ($data as $sheet_one) {
            $ff_id=$sheet_one->ff_id;
            $ff_name=$sheet_one->ff_name; 
            $father_or_husband_name=$sheet_one->father_or_husband_name;
            $mother_name=$sheet_one->mother_name;
            $post_office=$sheet_one->post_office;
            $post_code=$sheet_one->post_code;
            $district=$sheet_one->district;
            $upazila_or_thana=$sheet_one->upazila_or_thana;
            $village_or_ward=$sheet_one->village_or_ward;
            $nid=$sheet_one->nid;
            $ghost_image_code=$sheet_one->ghost_image_code;
            $ff_photo=$sheet_one->ff_photo;
            $html_tbl .="<tr>";
            $html_tbl .="
                <td>".htmlspecialchars($ff_id)."</td>
                <td>".$ff_name."</td>
                <td>".$father_or_husband_name."</td>
                <td>".$mother_name."</td>
                <td>".$post_office."</td>                
                <td>".$post_code."</td>
                <td>".$district."</td>
                <td>".$upazila_or_thana."</td>
                <td>".$village_or_ward."</td>
                <td>".htmlspecialchars($nid)."</td>
                <td>".htmlspecialchars($ghost_image_code)."</td>
                <td>".$ff_photo."</td>  
                "; 
            $html_tbl .="</tr>";
        }
        $html_tbl .="</table>"; 
        $inputType = 'Xlsx';
        $datas = ImportLogDetail::where('record_unique_id',$id)->first();
        $name=preg_replace('/[^A-Za-z0-9\-]/', '', $datas->name); // Removes special chars from log name
        $newFileName = $name."_".date('dmYHis').'_'.uniqid().'.xlsx';    
        // save $table inside temporary file that will be deleted later
        $tmpfile = tempnam(sys_get_temp_dir(), 'xlsx');
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
        unlink($tmpfile); // delete temporary file because it isn't needed anymore          
        $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet,$inputType);
        $objWriter->save(public_path().'/'.$subdomain[0].'/molwa_excels/'.$newFileName);
        return response()->download(public_path().'/'.$subdomain[0].'/molwa_excels/'.$newFileName, $newFileName);
        //unlink(public_path().'/'.$subdomain[0].'/molwa_excels/'.$newFileName);
        //$username = \Auth::guard('admin')->user()->id; 
    }   
    public function IdGenerate(Request $request){
        $admin_info = \Auth::guard('admin')->user()->toArray(); 
        $admin_id=Auth::guard('admin')->user()->id;
        $id=$request['id'];
        $records = ImportLogDetail::where('record_unique_id',$id)->first();
        Session::forget('progressFile');
        Session::save();        
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain); 
        $data = FreedomFighterList::where('record_unique_id',$id)->get();  
        $domain = \Request::getHost();
        
        $filename='progress_'.uniqid().'.txt';
        $file = public_path().'\\'.$subdomain[0].'\\progess_files\\'.$filename;              
        if (!file_exists($file)) {
            $myfile=fopen($file, "w");
            $txt= array('percent'=>0, 'show_msg'=>'');
            fwrite($myfile, json_encode($txt, JSON_PRETTY_PRINT));          
            //echo "File created.".$file;
        }
        Session::put('progressFile', $filename);
        Session::save();   
        
        $pdfBig = new \Mpdf\Mpdf(['orientation' => 'P', 'mode' => 'utf-8', 'format' => [86, 54], 'tempDir'=>storage_path('tempdir')]);
        $pdfBig->SetCreator('seqr'); //PDF_CREATOR
        $pdfBig->SetAuthor('MPDF');
        $pdfBig->SetTitle('ID Card');
        $pdfBig->SetSubject('');
        // remove default header/footer
        $pdfBig->setHeader(false);
        $pdfBig->setFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0); 
        $generated_documents=0;  
        $cnt=1;
        $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
        $inputf=array(); 
        foreach ($data as $sheet_one) { 
            $high_res_bg="molwa_ID_BG.jpg"; 
            $low_res_bg="molwa_ID_BG.jpg";
            $pdfBig->AddPage();  //All PDFs      
            /*Individual PDF*/
            $pdf = new \Mpdf\Mpdf(['orientation' => 'P', 'mode' => 'utf-8', 'format' => [86, 54], 'tempDir'=>storage_path('tempdir')]);
            $pdf->SetCreator('seqr'); //PDF_CREATOR
            $pdf->SetAuthor('MPDF');
            $pdf->SetTitle('ID Card');
            $pdf->SetSubject('');
            // remove default header/footer
            $pdf->setHeader(false);
            $pdf->setFooter(false);
            $pdf->SetAutoPageBreak(false, 0); 
            $pdf->AddPage();            
            //set background image
            $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\\'.$low_res_bg;
            $pdf->Image($template_img_generate, 0, 0, 86, 54, 'jpg', '', true, false);
            
            $fid=$sheet_one->id;
            $ff_id=$sheet_one->ff_id;
            $ff_name=$sheet_one->ff_name;
            $fh_name=$sheet_one->father_or_husband_name;
            $mother_name=$sheet_one->mother_name;
            $post_office=$sheet_one->post_office;
            $post_code=$sheet_one->post_code;
            $district=$sheet_one->district;
            $ut_name=$sheet_one->upazila_or_thana;
            $vw_name=$sheet_one->village_or_ward;
            $nid=$sheet_one->nid;
            $ghost_image_code=$sheet_one->ghost_image_code;
            $ff_photo=$sheet_one->ff_photo; 
            $serial_no='ID_'.$ff_id;
            //UV 
            $JOYBANGLA=public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\uv_2.png'; 
            //$pdfBig->Image($JOYBANGLA, 20.8, 1, 9, 3, 'png', '', true, false);            
            $JOYBANGLA_BANDHU=public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\uv_1.png'; 
            //$pdfBig->Image($JOYBANGLA_BANDHU, 55.1, 1, 9, 3, 'png', '', true, false);            
            $Picture_Sheikh_Mujibur_Rahman=public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\uv_4.png'; 
            //$pdfBig->Image($Picture_Sheikh_Mujibur_Rahman, 76.7, 2.5, 9.5, 7.5, 'png', '', true, false);            
            $signature_1=public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\uv_3.png'; 
            //$pdfBig->Image($signature_1, 27.9, 46.3, 9, 3.5, 'png', '', true, false);            
            $signature_2=public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\uv_3.png'; 
            //$pdfBig->Image($signature_2, 60.9, 46.3, 9, 3.5, 'png', '', true, false);            
            $National_Monument=public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\uv_5.png'; 
            //$pdfBig->Image($National_Monument, 37.2, 41.2, 10.9, 10, 'png', '', true, false); 
            //photo
            if($ff_photo != ''){
                $pic = $this->getImage($ff_photo);
                if ($pic !== false){
                    $TEMPIMGLOC = tempnam(sys_get_temp_dir(), $pic[1]);
                    $decodedImg=$pic[2];
                    if (file_put_contents($TEMPIMGLOC, $decodedImg) !== false) {
                        $pdfBig->Image($TEMPIMGLOC, 3, 18, 18.7, 19.5, $pic[1], '', true, false);
                        $pdf->Image($TEMPIMGLOC, 3, 18, 18.7, 19.5, $pic[1], '', true, false);
                    }
                    unlink($TEMPIMGLOC); 
                }                   
            }             
            $pdfBig->SetXY(48,16.7);
            $pdfBig->SetFont('verdana', '', 6);
            $pdfBig->MultiCell(37, 3, $ff_id, 0, 'L');
            $pdfBig->SetFont('nikosh', '', 9);
            //Freedom Fighter Name
            $pdfBig->SetXY(36.5,21.5);        
            $pdfBig->MultiCell(48.5, 0, $ff_name, 0, 'L');        
            //Father/Husband Name of FF
            $pdfBig->SetXY(35,25);        
            $pdfBig->MultiCell(50, 0, $fh_name, 0, 'L');    
            //Name of Village/Ward
            $pdfBig->SetXY(35,28.6);        
            $pdfBig->MultiCell(50, 0, $vw_name, 0, 'L');  
            //Name of Post Office
            $pdfBig->SetXY(32,32);
            $pdfBig->MultiCell(53, 0, $post_office, 0, 'L'); 
            //Upazila/Thana Name
            $pdfBig->SetXY(38.5,35.5);
            $pdfBig->MultiCell(46.5, 0, $ut_name, 0, 'L');
            //Name of District
            $pdfBig->SetXY(30,38.9);
            $pdfBig->MultiCell(55, 0, $district, 0, 'L'); 
            
            $pdf->SetXY(48,16.7);
            $pdf->SetFont('verdana', '', 6);
            $pdf->MultiCell(37, 3, $ff_id, 0, 'L');
            $pdf->SetFont('nikosh', '', 9);
            //Freedom Fighter Name
            $pdf->SetXY(36.5,21.5);        
            $pdf->MultiCell(48.5, 0, $ff_name, 0, 'L');        
            //Father/Husband Name of FF
            $pdf->SetXY(35,25);        
            $pdf->MultiCell(50, 0, $fh_name, 0, 'L');    
            //Name of Village/Ward
            $pdf->SetXY(35,28.6);        
            $pdf->MultiCell(50, 0, $vw_name, 0, 'L');  
            //Name of Post Office
            $pdf->SetXY(32,32);
            $pdf->MultiCell(53, 0, $post_office, 0, 'L'); 
            //Upazila/Thana Name
            $pdf->SetXY(38.5,35.5);
            $pdf->MultiCell(46.5, 0, $ut_name, 0, 'L');
            //Name of District
            $pdf->SetXY(30,38.9);
            $pdf->MultiCell(55, 0, $district, 0, 'L');         
            
            //qr code 1   
            $dt = $z.date("_ymdHis");
            $encryptedString=strtoupper(md5($dt));
            $codeContents1 ="zVjbVPFeo2o";
            $qr_code_path1 = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
            $qrCodex = 3.1;
            $qrCodey = 40.3;
            $qrCodeWidth =11;
            $qrCodeHeight = 11;
            $ecc = 'L';
            $pixel_Size = 4;
            $frame_Size = 1;  
            \PHPQRCode\QRcode::png($codeContents1, $qr_code_path1, $ecc, $pixel_Size, $frame_Size);
            $pdfBig->Image($qr_code_path1, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false);
            $pdf->Image($qr_code_path1, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false);
            
            //qr code    
            $dt = date("_ymdHis");
            $str=$serial_no.$dt;
            $codeContents =$encryptedString = strtoupper(md5($str));
            $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
            $qrCodex = 72;
            $qrCodey = 40;
            $qrCodeWidth =11;
            $qrCodeHeight = 11;
            $ecc = 'L';
            $pixel_Size = 4;
            $frame_Size = 1;  
            \PHPQRCode\QRcode::png($codeContents, $qr_code_path, $ecc, $pixel_Size, $frame_Size);
            $pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false);             
            $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false);             
            //Save individual PDF
            $pdfPath=public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$serial_no.'.pdf';
            $pdf->output($pdfPath,'F');
            
            if (StudentTable::where('serial_no',$serial_no)->count() == 0){
                $certName=$serial_no.'.pdf';
                $key = $encryptedString; 
                $urlRelativeFilePath = 'qr/'.$encryptedString.'.png'; 
                $datetime  = date("Y-m-d H:i:s");    
                $sts=1;
                $auth_site_id=Auth::guard('admin')->user()->site_id;
                StudentTable::create(['serial_no'=>$serial_no, 'certificate_filename'=>$certName, 'template_id'=>(NULL), 'key'=>$key, 'path'=>$urlRelativeFilePath, 'created_at'=>$datetime, 'created_by'=>$admin_id, 'updated_at'=>$datetime, 'updated_by'=>$admin_id, 'status'=>$sts, 'site_id'=>$auth_site_id]);
            }    
            $percent = ($cnt/$records->fresh_records) * 100;
            //$show_msg = $cnt."/".$records->fresh_records." record(s)";
            $show_msg = "Generating<br />".$cnt."/".$records->fresh_records;
            if (file_exists($file)) {
                $myfile=fopen($file, "w");
                $txt=array('percent'=>$percent, 'show_msg'=>$show_msg);
                fwrite($myfile, json_encode($txt, JSON_PRETTY_PRINT));
                sleep(1);
            }    
            //$inputf['updated_at'] = (NULL);
            $inputf['generated_at'] = now();      
            $inputf['g_admin_id'] = $admin_id;
            $dataf = FreedomFighterList::where('id',$fid)->update($inputf);      
            $generated_documents++;
            $cnt++;
        }
        $datas = ImportLogDetail::where('record_unique_id',$id)->first();
        $name=preg_replace('/[^A-Za-z0-9\-]/', '', $datas->name); // Removes special chars from log name
        $file_name =  $name."_id_".$id.'.pdf';
        $filepath = public_path().'\\'.$subdomain[0].'/molwa_pdfs/ID/'.$file_name;            
        $pdfBig->output($filepath,'F');     
        //edit idcard status
        $input=array();
        $input['idcard_status'] = "GENERATED";
        $input['generated_at'] = now();      
        $input['g_admin_id'] = $admin_id; //generated by      
        $input['idcard_file'] = $file_name; 
        ImportLogDetail::where('record_unique_id',$id)->update($input);  
        return response()->json(['success'=>true]);    
    }
    public function getImage($dataURI) {
       $img = explode(',', $dataURI, 2);
       $pic = 'data://text/plain;base64,'.$img[1];
       $decodedImg = base64_decode($img[1]);
       $type = explode("/", explode(':', substr($dataURI, 0, strpos($dataURI, ';')))[1])[1]; // get the image type
       if ($type == "png" || $type == "jpeg" || $type == "gif") return array($pic, $type, $decodedImg);
       return false;
    }   
    public function CertificateGenerate(Request $request){
        $admin_info = \Auth::guard('admin')->user()->toArray(); 
        $admin_id=Auth::guard('admin')->user()->id;
        $id=$request['id'];
        $records = ImportLogDetail::where('record_unique_id',$id)->first();
        Session::forget('progressFile');
        Session::save();         
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain); 
        $data = FreedomFighterList::where('record_unique_id',$id)->get();  

        $filename='progress_'.uniqid().'.txt';
        $file = public_path().'\\'.$subdomain[0].'\\progess_files\\'.$filename;              
        if (!file_exists($file)) {
            $myfile=fopen($file, "w");
            $txt= array('percent'=>0, 'show_msg'=>'');
            fwrite($myfile, json_encode($txt, JSON_PRETTY_PRINT));          
            //echo "File created.".$file;
        }
        Session::put('progressFile', $filename);
        Session::save();      
        
        $ghostImgArr = array();
        $pdfBig = new \Mpdf\Mpdf(['orientation' => 'P', 'mode' => 'utf-8', 'format' => [296.926, 210.058], 'tempDir'=>storage_path('tempdir')]);
        $pdfBig->SetCreator('seqr'); //PDF_CREATOR
        $pdfBig->SetAuthor('MPDF');
        $pdfBig->SetTitle('Certificate');
        $pdfBig->SetSubject('');
        // remove default header/footer
        $pdfBig->setHeader(false);
        $pdfBig->setFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0); 
        $generated_documents=0;  
        $cnt=1;
        $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
        $inputf=array(); 
        foreach ($data as $sheet_one) { 
            $high_res_bg="Molwa_Certificate_Bg.png"; 
            $low_res_bg="Molwa_Certificate_Bg.png";
            $pdfBig->AddPage();        
            /*Individual PDF*/
            $pdf = new \Mpdf\Mpdf(['orientation' => 'P', 'mode' => 'utf-8', 'format' => [296.926, 210.058], 'tempDir'=>storage_path('tempdir')]);
            $pdf->SetCreator('seqr'); //PDF_CREATOR
            $pdf->SetAuthor('MPDF');
            $pdf->SetTitle('Certificate');
            $pdf->SetSubject('');
            // remove default header/footer
            $pdf->setHeader(false);
            $pdf->setFooter(false);
            $pdf->SetAutoPageBreak(false, 0); 
            $pdf->AddPage();     
            $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\\'.$low_res_bg;
            $pdf->Image($template_img_generate, 0, 0, 296.926,  210.058, 'jpg', '', true, false);
            
            $fid=$sheet_one->id;
            $ff_id=$sheet_one->ff_id;
            $ff_name=$sheet_one->ff_name;
            $fh_name=$sheet_one->father_or_husband_name;
            $mother_name=$sheet_one->mother_name;
            $post_office=$sheet_one->post_office;
            $post_code=$sheet_one->post_code;
            $district=$sheet_one->district;
            $ut_name=$sheet_one->upazila_or_thana;
            $vw_name=$sheet_one->village_or_ward;
            $nid=$sheet_one->nid;
            $ghost_image_code=$sheet_one->ghost_image_code;
            $ff_photo=$sheet_one->ff_photo; 
            $serial_no='C_'.$ff_id;

            $j = mb_strlen($ff_id);
            $char_x=231; //231.1
            $char_y=38.9; //38.7
            $digit_x=230.5;
            $digit_y=36.4; //36.6
            for ($k = 0; $k < $j; $k++) 
            {
                $digit = mb_substr($ff_id, $k, 1);
                $digit_char = $this->digitToChar($digit);            
                
                $pdfBig->SetXY($char_x,$char_y);
                $pdfBig->StartTransform();
                $pdfBig->Rotate(90);
                $pdfBig->SetFont('verdana', '', 4.5); //3.5
                $pdfBig->Cell(0, 0, $digit_char, 0, false, 'L');
                $pdfBig->StopTransform();
                $pdfBig->SetXY($digit_x,$digit_y);
                $pdfBig->SetFont('verdana', '', 11); //8.5
                $pdfBig->Cell(0, 0, $digit, 0, false, 'L'); 
                
                $pdf->SetXY($char_x,$char_y);
                $pdf->StartTransform();
                $pdf->Rotate(90);
                $pdf->SetFont('verdana', '', 4.5); //3.5
                $pdf->Cell(0, 0, $digit_char, 0, false, 'L');
                $pdf->StopTransform();
                $pdf->SetXY($digit_x,$digit_y);
                $pdf->SetFont('verdana', '', 11); //8.5
                $pdf->Cell(0, 0, $digit, 0, false, 'L');
                
                $char_x +=3.9; //3
                $digit_x +=3.9; //3
            }
            $pdfBig->SetFont('nikosh', '', 17);
            $pdfBig->SetXY(132,89);        
            $pdfBig->MultiCell(129, 7, $ff_name, 0, 'L');  
            //Father/Husband Name of FF
            $pdfBig->SetXY(64,100);
            $pdfBig->MultiCell(92, 7, $fh_name, 0, 'L');  
            //Name of Village/Ward
            $pdfBig->SetXY(188,100);
            $pdfBig->MultiCell(90, 7, $vw_name, 0, 'L');
            //Name of Post Office
            $pdfBig->SetXY(64,110);
            $pdfBig->MultiCell(90, 7, $post_office, 0, 'L');  
            //Upazila/Thana Name
            $pdfBig->SetXY(188,110);
            $pdfBig->MultiCell(90, 7, $ut_name, 0, 'L');
            //Name of District
            $pdfBig->SetXY(64,121);
            $pdfBig->MultiCell(94, 7, $district, 0, 'L');
            
            $pdf->SetFont('nikosh', '', 17);
            $pdf->SetXY(132,89);        
            $pdf->MultiCell(129, 7, $ff_name, 0, 'L');  
            //Father/Husband Name of FF
            $pdf->SetXY(64,100);
            $pdf->MultiCell(92, 7, $fh_name, 0, 'L');  
            //Name of Village/Ward
            $pdf->SetXY(188,100);
            $pdf->MultiCell(90, 7, $vw_name, 0, 'L');
            //Name of Post Office
            $pdf->SetXY(64,110);
            $pdf->MultiCell(90, 7, $post_office, 0, 'L');  
            //Upazila/Thana Name
            $pdf->SetXY(188,110);
            $pdf->MultiCell(90, 7, $ut_name, 0, 'L');
            //Name of District
            $pdf->SetXY(64,121);
            $pdf->MultiCell(94, 7, $district, 0, 'L');
            
            // Ghost image  
            $ghost_font_size = 11;
            if($ghost_font_size == '10'){
                $ghostimageHeight = 5;
                $ghostimageWidth = 32;
            }
            else if($ghost_font_size == '11'){
                $ghostimageHeight = 7;
                $ghostimageWidth = 33.426664583;
            }else if($ghost_font_size == '12'){
                $ghostimageHeight = 8;
                $ghostimageWidth = 36.415397917;
            }
            else if($ghost_font_size == '13'){
                $ghostimageHeight = 10;
                $ghostimageWidth = 39.405983333; 
            }
            $nameOrg=trim($ff_id); 
            $ghostImagex = 110;
            $ghostImagey = 197.5;
            $name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 11);
            $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
            $pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostimageHeight, 'png', '', true, false);
            $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostimageHeight, 'png', '', true, false);
            
            //qr code 1   
            $dt = $z.date("_ymdHis");
            $encryptedString=strtoupper(md5($dt));
            $codeContents1 ="zVjbVPFeo2o";
            $qr_code_path1 = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
            $qrCodex = 7;
            $qrCodey = 162.1;
            $qrCodeWidth =24;
            $qrCodeHeight = 24;
            $ecc = 'L';
            $pixel_Size = 4;
            $frame_Size = 1;  
            \PHPQRCode\QRcode::png($codeContents1, $qr_code_path1, $ecc, $pixel_Size, $frame_Size);
            $pdfBig->Image($qr_code_path1, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false); 
            $pdf->Image($qr_code_path1, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false); 
            
            //qr code 2   
            $dtc = date("_ymdHis");
            $str=$serial_no.$dtc;
            $codeContents =$encryptedString = strtoupper(md5($str));
            $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
            $qrCodex = 258;
            $qrCodey = 162.1;
            $qrCodeWidth =16;
            $qrCodeHeight = 16;
            $ecc = 'L';
            $pixel_Size = 4;
            $frame_Size = 1;  
            \PHPQRCode\QRcode::png($codeContents, $qr_code_path, $ecc, $pixel_Size, $frame_Size);
            $pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false); 
            $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false); 
            //Save individual PDF
            $pdfPath=public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$serial_no.'.pdf';
            $pdf->output($pdfPath,'F');           

            if (StudentTable::where('serial_no',$serial_no)->count() == 0){
                $certName=$serial_no.'.pdf';
                $key = $encryptedString; 
                $urlRelativeFilePath = 'qr/'.$encryptedString.'.png'; 
                $datetime  = date("Y-m-d H:i:s");    
                $sts=1;
                $auth_site_id=Auth::guard('admin')->user()->site_id;
                StudentTable::create(['serial_no'=>$serial_no, 'certificate_filename'=>$certName, 'template_id'=>(NULL), 'key'=>$key, 'path'=>$urlRelativeFilePath, 'created_at'=>$datetime, 'created_by'=>$admin_id, 'updated_at'=>$datetime, 'updated_by'=>$admin_id, 'status'=>$sts, 'site_id'=>$auth_site_id]);
            }    
            
            $percent = ($cnt/$records->fresh_records) * 100;
            //$show_msg = $cnt."/".$records->fresh_records." record(s)";
            $show_msg = "Generating<br />".$cnt."/".$records->fresh_records;
            if (file_exists($file)) {
                $myfile=fopen($file, "w");
                $txt=array('percent'=>$percent, 'show_msg'=>$show_msg);
                fwrite($myfile, json_encode($txt, JSON_PRETTY_PRINT));
                sleep(1);
            }            
            //$inputf['updated_at'] = (NULL);
            $inputf['c_generated_at'] = now();      
            $inputf['c_admin_id'] = $admin_id; //generated by
            $dataf = FreedomFighterList::where('id',$fid)->update($inputf);              
            $generated_documents++;
            $cnt++;  
        }
        $datas = ImportLogDetail::where('record_unique_id',$id)->first();
        $name=preg_replace('/[^A-Za-z0-9\-]/', '', $datas->name); // Removes special chars from log name
        $file_name =  $name."_cert_".$id.'.pdf';        
        $filepath = public_path().'\\'.$subdomain[0].'/molwa_pdfs/certificate/'.$file_name;            
        $pdfBig->output($filepath,'F');     
        //edit idcard status
        $input=array();
        $input['certificate_status'] = "GENERATED";  
        $input['c_generated_at'] = now();   
        $input['c_admin_id'] = $admin_id;      
        $input['certificate_file'] = $file_name;      
        $datas = ImportLogDetail::where('record_unique_id',$id)->update($input);    
        return response()->json(['success'=>true]);    
    }
   
    public function StatusComplete(Request $request){
        $admin_info = \Auth::guard('admin')->user()->toArray(); 
        $admin_id=Auth::guard('admin')->user()->id;
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $username = $admin_info['username'];        
        $print_datetime = date("Y-m-d H:i:s");
        $template_name='Basic';
        $printer_name = 'Printer 1';
        $datetime = date("Y-m-d H:i:s");
        $id=$request['id'];
        $col=$request['col'];
        $input=array();
        $inputf=array();
        if($col == 'idcard_status'){            
            $initial="ID_";
            $input['idcard_status'] = "COMPLETED";      
            $input['completed_at'] = now();      
            $input['icc_admin_id'] = $admin_id;
            ImportLogDetail::where('record_unique_id',$id)->update($input);     
            $inputf['completed_at'] = now();      
            $inputf['icc_admin_id'] = $admin_id; //generated by
            FreedomFighterList::where('record_unique_id',$id)->update($inputf);    
        }else{
            $initial="C_";
            $input['certificate_status'] = "COMPLETED";      
            $input['c_completed_at'] = now();      
            $input['cc_admin_id'] = $admin_id;
            ImportLogDetail::where('record_unique_id',$id)->update($input);  
            $inputf['c_completed_at'] = now();      
            $inputf['cc_admin_id'] = $admin_id; //generated by
            FreedomFighterList::where('record_unique_id',$id)->update($inputf); 
        }
        $data = FreedomFighterList::where('record_unique_id',$id)->get();
        foreach ($data as $sheet_one) {
            $fid=$sheet_one->id;
            $serial_no=$initial.$sheet_one->ff_id;
            $numCount = PrintingDetail::select('id')->where('sr_no',$serial_no)->count();
            $printer_count = $numCount + 1;        
            $print_serial_no = $this->nextPrintSerial();
            PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$serial_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$admin_id,'updated_at'=>$datetime,'updated_by'=>$admin_id,'status'=>1,'site_id'=>$auth_site_id,'publish'=>1]);
        }
        return response()->json(['success'=>true]);
    }
    
    public function ViewImportFF(Request $request){        
        $id=$request['id'];
        $data = ImportLogDetail::where('id',$id)->first(); 
        $created_at="<strong>Date:</strong> ".date('d-m-Y h:i A', strtotime($data->created_at));
        $created_by="<strong>User:</strong> ".Admin::where('id',$data->admin_id)->first()->fullname;
        if($data->generated_at != ''){
            $generated_at="<strong>Date:</strong> ".date('d-m-Y h:i A', strtotime($data->generated_at));
            $generated_by="<strong>User:</strong> ".Admin::where('id',$data->g_admin_id)->first()->fullname;
        }else{
            $generated_at="Pending";
            $generated_by='';
        }
        if($data->completed_at != ''){
            $completed_at="<strong>Date:</strong> ".date('d-m-Y h:i A', strtotime($data->completed_at));
            $completed_by="<strong>User:</strong> ".Admin::where('id',$data->icc_admin_id)->first()->fullname;
        }else{
            $completed_at="Pending";
            $completed_by='';
        }
        if($data->c_generated_at != ''){
            $c_generated_at="<strong>Date:</strong> ".date('d-m-Y h:i A', strtotime($data->c_generated_at));
            $c_generated_by="<strong>User:</strong> ".Admin::where('id',$data->c_admin_id)->first()->fullname;
        }else{
            $c_generated_at="Pending";
            $c_generated_by='';
        }
        if($data->c_completed_at != ''){
            $c_completed_at="<strong>Date:</strong> ".date('d-m-Y h:i A', strtotime($data->c_completed_at));
            $c_completed_by="<strong>User:</strong> ".Admin::where('id',$data->cc_admin_id)->first()->fullname;
        }else{
            $c_completed_at="Pending";
            $c_completed_by='';
        }
        
        $tbl="
        <table class='table table-hover table-bordered'>
            <caption>
            <h5><strong>Imported</strong> $created_at | $created_by</h5>
            <h5><strong>Name:</strong> $data->name | <strong>Per Page:</strong> $data->per_page | <strong>Page Number:</strong> $data->page_no | <strong>Quantity:</strong> $data->fresh_records</h5> 
            </caption>
            <tr>
                <th width='15%'></th>
                <th width='33%'>Generated</th>
                <th width='33%'>Completed</th>
            </tr>
            <tr>
                <th>ID Card</th> 
                <td>$generated_at <br />$generated_by</td>
                <td>$completed_at <br />$completed_by</td>
            </tr>
            <tr>
                <th>Certificate</th>
                <td>$c_generated_at <br />$c_generated_by</td>
                <td>$c_completed_at <br />$c_completed_by</td>
            </tr>
        </table>
        ";
        echo $tbl;
    }
    public function ViewFF(Request $request){        
        $id=$request['id'];
        $flag=$request['flag'];
        $data = FreedomFighterList::where('id',$id)->first(); 
        if($flag=='Process'){
            $created_at="<strong>Date:</strong> ".date('d-m-Y h:i A', strtotime($data->created_at));
            $created_by="<strong>User:</strong> ".Admin::where('id',$data->admin_id)->first()->fullname;
            if($data->generated_at != ''){
                $generated_at="<strong>Date:</strong> ".date('d-m-Y h:i A', strtotime($data->generated_at));
                $generated_by="<strong>User:</strong> ".Admin::where('id',$data->g_admin_id)->first()->fullname;
            }else{
                $generated_at="Pending";
                $generated_by='';
            }
            if($data->completed_at != ''){
                $completed_at="<strong>Date:</strong> ".date('d-m-Y h:i A', strtotime($data->completed_at));
                $completed_by="<strong>User:</strong> ".Admin::where('id',$data->icc_admin_id)->first()->fullname;
            }else{
                $completed_at="Pending";
                $completed_by='';
            }
            if($data->c_generated_at != ''){
                $c_generated_at="<strong>Date:</strong> ".date('d-m-Y h:i A', strtotime($data->c_generated_at));
                $c_generated_by="<strong>User:</strong> ".Admin::where('id',$data->c_admin_id)->first()->fullname;
            }else{
                $c_generated_at="Pending";
                $c_generated_by='';
            }
            if($data->c_completed_at != ''){
                $c_completed_at="<strong>Date:</strong> ".date('d-m-Y h:i A', strtotime($data->c_completed_at));
                $c_completed_by="<strong>User:</strong> ".Admin::where('id',$data->cc_admin_id)->first()->fullname;
            }else{
                $c_completed_at="Pending";
                $c_completed_by='';
            }
            
            $tbl="
            <table class='table table-bordered'>
                <caption>
                <h5><strong>Imported</strong> $created_at | $created_by</h5>                
                </caption>
                <tr>
                    <th width='15%'></th>
                    <th width='33%'>Generated</th>
                    <th width='33%'>Completed</th>
                </tr>
                <tr>
                    <th>ID Card</th> 
                    <td>$generated_at <br />$generated_by</td>
                    <td>$completed_at <br />$completed_by</td>
                </tr>
                <tr>
                    <th>Certificate</th>
                    <td>$c_generated_at <br />$c_generated_by</td>
                    <td>$c_completed_at <br />$c_completed_by</td>
                </tr>
            </table>
            ";
            echo $tbl;
        }else{
            $fid=$data->id;
            $ff_id=$data->ff_id;
            $ff_name=$data->ff_name;
            $fh_name=$data->father_or_husband_name;
            $mother_name=$data->mother_name;
            $post_office=$data->post_office;
            $post_code=$data->post_code;
            $district=$data->district;
            $ut_name=$data->upazila_or_thana;
            $vw_name=$data->village_or_ward;
            $nid=$data->nid;
            $ghost_image_code=$data->ghost_image_code;
            $ff_photo=$data->ff_photo;      
            
            $tbl="
            <table>
            <tr>
            <td width='10%' style='vertical-align: top !important;'><img src='$ff_photo' class='img-thumbnail' /></td>
            <td width='90%'>
                <table class='table table-bordered'>
                    <tr><th width='20%'>Freedom Fighter ID</th><td width='80%'>$ff_id</td></tr>
                    <tr><th>Freedom Fighter Name</th><td>$ff_name</td></tr>
                    <tr><th>Father/Husband Name</th><td>$fh_name</td></tr>
                    <tr><th>Mother Name</th><td>$mother_name</td></tr>
                    <tr><th>Post Office</th><td>$post_office</td></tr>
                    <tr><th>Post Code</th><td>$post_code</td></tr>
                    <tr><th>District</th><td>$district</td></tr>
                    <tr><th>Upazila/Thana</th><td>$ut_name</td></tr>
                    <tr><th>Village/Ward</th><td>$vw_name</td></tr>
                    <tr><th>NID</th><td>$nid</td></tr>
                    <tr><th>Ghost Image Code</th><td>$ghost_image_code</td></tr>
                </table>
            </td>
            </tr>
            </table>
            ";
            echo $tbl;
        }
            
    }
    
    public function ActiveInactiveRecord(Request $request){        
        $id=$_POST['id'];
        $ffid=$_POST['ffid'];
        if($id != ''){            
            $publish=$_POST['publish'];
            $mode=$_POST['mode'];    
            if($mode=='active'){ $modes='activated'; }else{ $modes='deactivated'; }
            DB::select(DB::raw('UPDATE `freedom_fighter_list` SET active="'.$publish.'" WHERE id="'.$id.'"'));
            if($ffid != ''){
                if($mode=='active'){ 
                    DB::select(DB::raw('UPDATE `student_table` SET status=1 WHERE serial_no="ID_'.$ffid.'"'));
                    DB::select(DB::raw('UPDATE `student_table` SET status=1 WHERE serial_no="C_'.$ffid.'"'));
                }else{
                    DB::select(DB::raw('UPDATE `student_table` SET status=0 WHERE serial_no="ID_'.$ffid.'"'));
                    DB::select(DB::raw('UPDATE `student_table` SET status=0 WHERE serial_no="C_'.$ffid.'"'));
                }
            }
            $result = json_encode(array('rstatus'=>'Success','message'=>'This document is '.$modes.'.','mode'=>$mode));
            echo $result;
        }
    }    
    
}

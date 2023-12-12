<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use TCPDF,TCPDF_FONTS,QrCode,Auth;
use App\models\SystemConfig;
use App\models\Config;
use App\models\StudentTable;
use App\models\PrintingDetail;
use App\models\ExcelUploadHistory;
use App\models\SiteDocuments;
use App\Helpers\CoreHelper;
use Helper;

class DegreeCertificatePdfJob
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
    public function handle()
    {
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();

        $pdf_data = $this->pdf_data;

        $highestRow = $pdf_data['highestRow'];
        $highestColumn = $pdf_data['highestColumn'];
        $FID = $pdf_data['FID'];
        $Orientation = $pdf_data['Orientation'];
        $template_Width = $pdf_data['template_Width'];
        $template_height = $pdf_data['template_height'];
        $bg_template_img_generate = $pdf_data['bg_template_img_generate'];
        $bg_template_width_generate = $pdf_data['bg_template_width_generate'];
        $bg_template_height_generate = $pdf_data['bg_template_height_generate'];
        $bg_template_img = $pdf_data['bg_template_img'];
        $bg_template_width = $pdf_data['bg_template_width'];
        $bg_template_height = $pdf_data['bg_template_height'];
        $mapped_excel_col_unique_serial_no = $pdf_data['mapped_excel_col_unique_serial_no'];
        $ext = $pdf_data['ext'];
        $fullpath = $pdf_data['fullpath'];
        $tmpDir = $pdf_data['tmpDir'];
        $excelfile = $pdf_data['excelfile'];
        $timezone = $pdf_data['timezone'];
        $printer_name = $pdf_data['printer_name'];
        $style2D = $pdf_data['style2D'];
        $style1D = $pdf_data['style1D'];
        $style1Da = $pdf_data['style1Da'];
        $admin_id = $pdf_data['admin_id'];
        
        $excel_row_num = $pdf_data['excel_row_num'];
        $pdf_flag = $pdf_data['pdf_flag'];


        $ghostImgArr = array();
        //set fonts
        $arial = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial.TTF', 'TrueTypeUnicode', '', 96);
        $arialb = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial Bold.TTF', 'TrueTypeUnicode', '', 96);

        $krutidev100 = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/K100.TTF', 'TrueTypeUnicode', '', 96); 
        $krutidev101 = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/K101.TTF', 'TrueTypeUnicode', '', 96);
        $HindiDegreeBold = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/KRUTI_DEV_100__BOLD.TTF', 'TrueTypeUnicode', '', 96); 
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/ARIALNB.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanBI = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Times-New-Roman-Bold-Italic.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanI = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Times New Roman_I.TTF', 'TrueTypeUnicode', '', 96);
        
        $template_name = '';
        if(isset($FID['template_name'])){
            $template_name = $FID['template_name'];
        }

        if($ext == 'xlsx' || $ext == 'Xlsx'){
            $inputFileType = 'Xlsx';
        }
        else{
            $inputFileType = 'Xls';
        }


        $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
        /**  Load $inputFileName to a Spreadsheet Object  **/
        $objPHPExcel = $objReader->load($fullpath);
        $sheet = $objPHPExcel->getSheet(0);

        if($pdf_flag == 0){
            \Session::forget('pdf_obj');
            $pdfBig = new TCPDF($Orientation, 'mm', array($bg_template_width, $bg_template_height), true, 'UTF-8', false);
            \Session::put('pdf_obj',$pdfBig);
        }
        if(\Session::get('pdf_obj') != null){
            $pdfBig = \Session::get('pdf_obj');
        }

        $pdfBig->setPrintHeader(false);
        $pdfBig->setPrintFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0);
        $pdfBig->SetProtection(array('modify', 'copy','annot-forms','fill-forms','extract','assemble'),'',null,0,null);

        $log_serial_no = 1;
        // dd($highestRow);
        if(isset($excel_row)){
            $excel_row = (int)$excel_row;
        }
        
        for($excel_row = $excel_row_num; $excel_row <= $highestRow; $excel_row++)
        {
      
            \File::copy(public_path().'/'.$subdomain[0].'/backend/templates/'.$FID['template_id'].'/CE_Sign.png',public_path().'/'.$subdomain[0].'/backend/templates/'.$FID['template_id'].'/CE_Sign_'.$excel_row.'.png');

            \File::copy(public_path().'/backend/templates/'.$FID['template_id'].'/VC_Sign.png',public_path().'/backend/templates/'.$FID['template_id'].'/VC_Sign_'.$excel_row.'.png');
            
            $rowData = $sheet->rangeToArray('A'. $excel_row . ':' . $highestColumn . $excel_row, NULL, TRUE, FALSE);
            if($this->isEmptyRow(reset($rowData))) {
                continue; 
            }
            
            $pdf = new TCPDF($Orientation, 'mm', array($template_Width, $template_height), true, 'UTF-8', false);

            $pdf->SetCreator('TCPDF');
            $pdf->SetAuthor('TCPDF');
            $pdf->SetTitle('Certificate');
            $pdf->SetSubject('');

            // remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
            $pdf->AddSpotColor('Spot Dark Green', 100, 50, 80, 45);

            $pdf->SetCreator('SetCreator');
            $pdfBig->AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
            $pdfBig->AddSpotColor('Spot Dark Green', 100, 50, 80, 45); // clear text on bottom red and in clear text logo
            $pdf->AddPage();
            if($bg_template_img_generate != ''){
                $template_img_generate = str_replace(' ', '+', $bg_template_img_generate);
                
                $image_extension = 'JPG';
                if ($ext == 'PNG' || 'png') {
                    $image_extension = 'PNG';
                }
                if ($ext == 'jpg') {
                    $image_extension = 'jpg';
                }
                
                if($rowData[0][10] == 'DB'){
                    $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/DB_Light Background.jpg';
                }
                else if($rowData[0][10] == 'DIP'){
                    $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/DIP_Background_lite.jpg';
                }
                else if($rowData[0][10] == 'INT'){
                    $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/INT_lite background.jpg';
                }
                else if($rowData[0][10] == 'DO'){
                    $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/DO_Background Light.jpg';   
                }
                $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
            }   
            $pdfBig->AddPage();

           
            $pdfBig->SetProtection($permissions = array('modify', 'copy', 'annot-forms', 'fill-forms', 'extract', 'assemble'), '', null, 0, null);

            $print_serial_no = $this->nextPrintSerial();

            $pdfBig->SetCreator('TCPDF');

            $pdf->SetTextColor(0, 0, 0, 100, false, '');
            
            $serial_no = trim($rowData[0][$mapped_excel_col_unique_serial_no]);

            

            
            $pdfBig->SetTextColor(0, 0, 0, 100, false, '');

          

                //set enrollment no
                $enrollment_font_size = '8';
                $enrollmentx= 26.7;
                
                $enrollmenty = 10.8;
                $enrollmentstr = trim($rowData[0][1]);
                
                $pdfBig->SetFont($arial, '', $enrollment_font_size, '', false);
                $pdfBig->SetXY($enrollmentx, $enrollmenty);
                $pdfBig->Cell(0, 0, $enrollmentstr, 0, false, 'L');

                $pdf->SetFont($arial, '', $enrollment_font_size, '', false);
                $pdf->SetXY($enrollmentx, $enrollmenty);
                $pdf->Cell(0, 0, $enrollmentstr, 0, false, 'L');


                //set serial No
                $serial_no_split = (string)trim($rowData[0][3]);
                $serialx = 186.4;
                $serialx = 181;
                $serialy = 10.9;
                for($i=0;$i<strlen($serial_no_split);$i++)
                { 
                    $get_last_four_digits = strlen($serial_no_split) - 4;
                    
                    $serial_font_size = 8;
                    if($i == 0){
                        $serialx = $serialx;
                    }
                    else{
                        if($i <= $get_last_four_digits){
                            if($serial_no_split[$i-1] == '/'){
                                $serialx = $serialx + (0.9);
                            }
                            else{
                                $serialx = $serialx + (1.7);
                            }
                        }
                        else{
                            $serialx = $serialx + (2.1);
                        }
                    }
                    if($i >= $get_last_four_digits){
                        
                        $serial_font_size = $serial_font_size + ($i - $get_last_four_digits) + 1;
                        $serialy = $serialy - 0.3;
                   
                    }
                    $serialstr = $serial_no_split[$i];

                    $pdfBig->SetFont($arial, '', $serial_font_size, '', false);
                    $pdfBig->SetXY($serialx, $serialy);
                    $pdfBig->Cell(0, 0, $serialstr, 0, false, 'L');

                    $pdf->SetFont($arial, '', $serial_font_size, '', false);
                    $pdf->SetXY($serialx, $serialy);
                    $pdf->Cell(0, 0, $serialstr, 0, false, 'L');
                }



                //qr code    
                //name // enrollment // degree // branch // cgpa // guid
                
                if($rowData[0][10] == 'DB' || $rowData[0][10] == 'INT' || $rowData[0][10] == 'DIP'){
                    $codeContents = trim($rowData[0][4])."\n".trim($rowData[0][1])."\n".trim($rowData[0][11])."\n".trim($rowData[0][13])."\n".number_format(trim($rowData[0][6]),2)."\n\n".md5(trim($rowData[0][0]));
                }
                else if($rowData[0][10] == 'DO'){
                    $codeContents = trim($rowData[0][4])."\n".trim($rowData[0][1])."\n".trim($rowData[0][11])."\n".number_format(trim($rowData[0][6]),2)."\n\n".md5(trim($rowData[0][0]));
                }
                $codePath = strtoupper(md5(rand()));
                
                $qr_code_path = public_path().'/'.$subdomain[0].'/backend/canvas/images/qr/'.$codePath.'.png';
                $qrCodex = 5.3;
                $qrCodey = 17.9;
                $qrCodeWidth =26.3;
                $qrCodeHeight = 25.3;
                        
                \QrCode::size(75.6)
                    ->format('png')
                    ->generate($codeContents, $qr_code_path);

                $pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
                $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
                

                
                $extension = '';
                if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2]).'.jpg.jpg'){
                    $extension = '.jpg.jpg';
                }
                else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2]).'.jpeg.jpg'){
                    $extension = '.jpeg.jpg';
                }
                else{
                    $extension = '.jpg';
                }
                $profile_path = public_path().'/backend/templates/'.$FID['template_id'].'/'.trim($rowData[0][2]).$extension;

                $profilex = 181;
                $profiley = 19.8;
                $profileWidth = 22.2;
                
                $profileHeight = 26.6;
                $pdfBig->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);  
                $pdf->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);


                //invisible data
                $invisible_font_size = '10';

                $invisible_degreex = 7.3;
                $invisible_degreey = 48.3;
                $invisible_degreestr = trim($rowData[0][11]);
                $pdfBig->SetOverprint(true, true, 0);
                
                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_font_size, '', false);
                $pdfBig->SetXY($invisible_degreex, $invisible_degreey);
                $pdfBig->Cell(0, 0, $invisible_degreestr, 0, false, 'L');

                if($rowData[0][10] == 'DB' || $rowData[0][10] == 'INT' || $rowData[0][10] == 'DIP'){
                    $invisible1y = 51.9;
                    $invisible1str = trim($rowData[0][13]);
                    
                    $pdfBig->SetTextSpotColor('Spot Red', 100);
                    $pdfBig->SetFont($arial, '', $invisible_font_size, '', false);
                    $pdfBig->SetXY($invisible_degreex, $invisible1y);
                    $pdfBig->Cell(0, 0, $invisible1str, 0, false, 'L');

                }

                if($rowData[0][10] == 'DB' || $rowData[0][10] == 'INT' || $rowData[0][10] == 'DIP'){
                    
                    $invisible2y = 55.1;
                }
                else if($rowData[0][10] == 'DO'){
                    $invisible2y = 51.9;   
                }
                $invisible2str = 'CGPA '.number_format(trim($rowData[0][6]),2); 
                
                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_font_size, '', false);
                $pdfBig->SetXY($invisible_degreex, $invisible2y);
                $pdfBig->Cell(0, 0, $invisible2str, 0, false, 'L');

                

                if($rowData[0][10] == 'DB' || $rowData[0][10] == 'INT' || $rowData[0][10] == 'DIP'){
                    
                    $invisible3y = 58.2;
                }
                else if($rowData[0][10] == 'DO'){
                    $invisible3y = 55.1;
                }
                $invisible3str = trim($rowData[0][8]); 
                
                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_font_size, '', false);
                $pdfBig->SetXY($invisible_degreex, $invisible3y);
                $pdfBig->Cell(0, 0, $invisible3str, 0, false, 'L');



                //invisible data profile name
                $invisible_profile_font_size = '10';
                $invisible_profile_name1x = 175.9;
                
                $invisible_profile_name1y = 47.6;
                $invisible_profile_name1str = strtoupper(trim($rowData[0][4]));
                
                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_profile_font_size, '', false);
                $pdfBig->SetXY($invisible_profile_name1x, $invisible_profile_name1y);
                $pdfBig->Cell(28.8, 0, $invisible_profile_name1str, 0, false, 'R');

                
                $invisible_profile_name2x = 186.6;
                
                $invisible_profile_name2y = 50.8;
                $invisible_profile_name2str = trim($rowData[0][1]);
                
                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_profile_font_size, '', false);
                $pdfBig->SetXY($invisible_profile_name2x, $invisible_profile_name2y);
                $pdfBig->Cell(18, 0, $invisible_profile_name2str, 0, false, 'R');
                $pdfBig->SetOverprint(false, false, 0);

                

                //enrollment no inside round
                $enrollment_no_font_size = '7';
                
                $enrollment_nox = 184.8;
                $enrollment_noy = 66;
                
                $enrollment_nostr = trim($rowData[0][1]);
                $pdfBig->SetFont($arialNarrowB, '', $enrollment_no_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,8,false,'');
                $pdfBig->SetXY(186, $enrollment_noy);
                $pdfBig->Cell(12, 0, $enrollment_nostr, 0, false, 'C');

                $pdf->SetFont($arialNarrowB, '', $enrollment_no_font_size, '', false);
                $pdf->SetTextColor(0,0,0,8,false,'');
                $pdf->SetXY(186, $enrollment_noy);
                $pdf->Cell(12, 0, $enrollment_nostr, 0, false, 'C');



                //profile name
                $profile_name_font_size = '20';
                $profile_namex = 71.7;
                
                if($rowData[0][10] == 'DB' || $rowData[0][10] == 'INT' || $rowData[0][10] == 'DIP'){
                    $profile_namey = 83.4;
                }
                else if($rowData[0][10] == 'DO'){
                    $profile_namey = 85;
                }
                $profile_namestr = strtoupper(trim($rowData[0][4]));
                $pdfBig->SetFont($timesNewRomanBI, '', $profile_name_font_size, '', false);
                $pdfBig->SetTextColor(0,92,88,7,false,'');
                $pdfBig->SetXY(10, $profile_namey);
                $pdfBig->Cell(190, 0, $profile_namestr, 0, false, 'C');

                $pdf->SetFont($timesNewRomanBI, '', $profile_name_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $profile_namey);
                $pdf->Cell(190, 0, $profile_namestr, 0, false, 'C');


                //degree name
                $degree_name_font_size = '20';
                $degree_namex = 55;
                
                if($rowData[0][10] == 'DB' || $rowData[0][10] == 'DIP'){
                    $degree_namey = 99.4;
                }
                else if($rowData[0][10] == 'INT'){
                    $degree_name_font_size = '14';
                    $degree_namey = 103.5;
                }
                else if($rowData[0][10] == 'DO'){
                    $degree_namey = 104.5;
                }
                
                $degree_namestr = trim($rowData[0][11]);
                $pdfBig->SetFont($timesNewRomanBI, '', $degree_name_font_size, '', false);
                $pdfBig->SetTextColor(0,92,88,7,false,'');
                $pdfBig->SetXY(10, $degree_namey);
                $pdfBig->Cell(190, 0, $degree_namestr, 0, false, 'C');

                $pdf->SetFont($timesNewRomanBI, '', $degree_name_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $degree_namey);
                $pdf->Cell(190, 0, $degree_namestr, 0, false, 'C');


                //branch name
                if($rowData[0][10] == 'DB' || $rowData[0][10] == 'INT' || $rowData[0][10] == 'DIP'){
                    if($rowData[0][10] == 'DB' || $rowData[0][10] == 'DIP'){
                        $branch_name_font_size = '18';
                        $branch_namey = 114.2;
                    }
                    else if($rowData[0][10] == 'INT'){
                        $branch_name_font_size = '14';
                        $branch_namey = 111.5;
                    }
                    
                    $branch_namex = 80;
                    $branch_namestr = trim($rowData[0][13]);
                    $pdfBig->SetFont($timesNewRomanBI, '', $branch_name_font_size, '', false);
                    $pdfBig->SetTextColor(0,92,88,7,false,'');
                    $pdfBig->SetXY(10, $branch_namey);
                    $pdfBig->Cell(190, 0, $branch_namestr, 0, false, 'C');

                    $pdf->SetFont($timesNewRomanBI, '', $branch_name_font_size, '', false);
                    $pdf->SetTextColor(0,92,88,7,false,'');
                    $pdf->SetXY(10, $branch_namey);
                    $pdf->Cell(190, 0, $branch_namestr, 0, false, 'C');
                }

                //grade
                $grade_font_size = '17';

                if($rowData[0][10] == 'DB' || $rowData[0][10] == 'INT' || $rowData[0][10] == 'DIP'){
                  
                    $gradey = 137.2;
                }
                else if($rowData[0][10] == 'DO'){
                    
                    $gradey = 133.3;
                }

                $gradestr = 'CGPA '. number_format(trim($rowData[0][6]),2).' ';
                $instr = 'in ';
                $datestr = trim($rowData[0][10]);


                $grade_str_result = $this->GetStringPositions(
                    array(
                        array($gradestr, $timesNewRomanBI, '', $grade_font_size), 
                        array($instr, $timesNewRomanI, '', $grade_font_size),
                        array($datestr, $timesNewRomanBI, '', $grade_font_size)
                    ),$pdf
                );
                

                $pdf->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_str_result[0], $gradey);
                $pdf->Cell(0, 0, $gradestr, 0, false, 'L');

                $pdfBig->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($grade_str_result[0], $gradey);
                $pdfBig->Cell(0, 0, $gradestr, 0, false, 'L');

                $pdf->SetFont($timesNewRomanI, '', $grade_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_str_result[1], $gradey);
                $pdf->Cell(0, 0, $instr, 0, false, 'L');

                $pdfBig->SetFont($timesNewRomanI, '', $grade_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($grade_str_result[1], $gradey);
                $pdfBig->Cell(0, 0, $instr, 0, false, 'L');

                $pdf->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_str_result[2], $gradey);
                $pdf->Cell(0, 0, $datestr, 0, false, 'L');

                $pdfBig->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($grade_str_result[2], $gradey);
                $pdfBig->Cell(0, 0, $datestr, 0, false, 'L');

      

                //micro line name
                if($rowData[0][10] == 'DB' || $rowData[0][10] == 'INT' || $rowData[0][10] == 'DIP'){
                    $microlinestr = strtoupper(str_replace(' ','',$rowData[0][4].$rowData[0][11].$rowData[0][13]));
                }
                else if($rowData[0][10] == 'DO'){
                    $microlinestr = strtoupper(str_replace(' ','',$rowData[0][4].$rowData[0][11]));
                }
                $textArray = imagettfbbox(1.4, 0, public_path().'/backend/canvas/fonts/Arial Bold.TTF', $microlinestr);
                $strWidth = ($textArray[2] - $textArray[0]);
                $strHeight = $textArray[6] - $textArray[1] / 1.4;
                
                if($rowData[0][10] == 'DB' || $rowData[0][10] == 'INT' || $rowData[0][10] == 'DIP'){
                    $latestWidth = 557;
                }
                else if($rowData[0][10] == 'DO'){
                    $latestWidth = 564;
                }
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
                $pdfBig->SetFont($arialb, '', 1.2, '', false);
                $pdfBig->SetTextColor(0, 0, 0);
                $pdfBig->StartTransform();
                if($rowData[0][10] == 'DB' || $rowData[0][10] == 'INT' || $rowData[0][10] == 'DIP'){
                    
                    $pdfBig->SetXY(36.8, 146.6);
                }
                else if($rowData[0][10] == 'DO'){
                    $pdfBig->SetXY(36.8, 145.5);
                }
                
                $pdfBig->Cell(0, 0, $array, 0, false, 'L');

                $pdfBig->StopTransform();


                $pdf->SetFont($arialb, '', 1.2, '', false);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->StartTransform();
                if($rowData[0][10] == 'DB' || $rowData[0][10] == 'INT' || $rowData[0][10] == 'DIP'){
                    
                    $pdf->SetXY(36.8, 146.6);
                }
                else if($rowData[0][10] == 'DO'){
                    $pdf->SetXY(36.8, 145.5);
                }
                $pdf->Cell(0, 0, $array, 0, false, 'L');

                $pdf->StopTransform();




                $microlineEnrollment = strtoupper(str_replace(' ','',$rowData[0][1].'CGPA'.number_format($rowData[0][6],2).$rowData[0][8]));
                $textArrayEnrollment = imagettfbbox(1.4, 0, public_path().'/backend/canvas/fonts/Arial Bold.TTF', $microlineEnrollment);
                $strWidthEnrollment = ($textArrayEnrollment[2] - $textArrayEnrollment[0]);
                $strHeightEnrollment = $textArrayEnrollment[6] - $textArrayEnrollment[1] / 1.4;
                
                $latestWidthEnrollment = 627;
                $wdEnrollment = '';
                $last_widthEnrollment = 0;
                $messageEnrollment = array();
                for($i=1;$i<=1000;$i++){

                    if($i * $strWidthEnrollment > $latestWidthEnrollment){
                        $wdEnrollment = $i * $strWidthEnrollment;
                        $last_widthEnrollment =$wdEnrollment - $strWidthEnrollment;
                        $extraWidth = $latestWidthEnrollment - $last_widthEnrollment;
                        $stringLength = strlen($microlineEnrollment);
                        $extraCharacter = intval($stringLength * $extraWidth / $strWidthEnrollment);
                        $messageEnrollment[$i]  = mb_substr($microlineEnrollment, 0,$extraCharacter);
                        break;
                    }
                    $messageEnrollment[$i] = $microlineEnrollment.'';
                }

                $horizontal_lineEnrollment = array();
                foreach ($messageEnrollment as $key => $value) {
                    $horizontal_lineEnrollment[] = $value;
                }
                
                $stringEnrollment = implode(',', $horizontal_lineEnrollment);
                $arrayEnrollment = str_replace(',', '', $stringEnrollment);
                $pdfBig->SetFont($arialb, '', 1.2, '', false);
                $pdfBig->SetTextColor(0, 0, 0);
                $pdfBig->StartTransform();
                if($rowData[0][10] == 'DB' || $rowData[0][10] == 'INT' || $rowData[0][10] == 'DIP'){
                    
                    $pdfBig->SetXY(36.4, 216.6);
                }
                else if($rowData[0][10] == 'DO'){
                    $pdfBig->SetXY(36.4, 216);
                }
                
                $pdfBig->Cell(0, 0, $arrayEnrollment, 0, false, 'L');

                $pdfBig->StopTransform();

                $pdf->SetFont($arialb, '', 1.2, '', false);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->StartTransform();
                if($rowData[0][10] == 'DB' || $rowData[0][10] == 'INT' || $rowData[0][10] == 'DIP'){
                    
                    $pdf->SetXY(36.4, 216.6);
                }
                else if($rowData[0][10] == 'DO'){
                    $pdf->SetXY(36.4, 216);
                }
                
                $pdf->Cell(0, 0, $arrayEnrollment, 0, false, 'L');

                $pdf->StopTransform();




                //profile name in hindi
                $profile_name_hindi_font_size = '25';
                $profile_name_hidix = 85.1;
                if($rowData[0][10] == 'DB' || $rowData[0][10] == 'INT' || $rowData[0][10] == 'DIP'){
                    
                    $profile_name_hidiy = 156.5;
                }
                else if($rowData[0][10] == 'DO'){
                    $profile_name_hidiy = 159;
                }
                $profile_name_hindistr = trim($rowData[0][5]);
                $pdfBig->SetFont($krutidev101, '', $profile_name_hindi_font_size, '', false);
                $pdfBig->SetTextColor(0,92,88,7,false,'');
                $pdfBig->SetXY(10, $profile_name_hidiy);
                $pdfBig->Cell(190, 0, $profile_name_hindistr, 0, false, 'C');

                $pdf->SetFont($krutidev101, '', $profile_name_hindi_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $profile_name_hidiy);
                $pdf->Cell(190, 0, $profile_name_hindistr, 0, false, 'C');

                //date in hindi (make whole string)
                $date_font_size =  '20';
                $str = 'dks bl mikf/k dh çkfIr gsrq fofue; fofgr vis{kkvksa dks ';
                $date_hindistr = trim($rowData[0][9]).' ';
                $hindiword_str = 'esa' ; 

                $strx = 20;
                $date_hindix = 159;
                if($rowData[0][10] == 'DB' || $rowData[0][10] == 'INT' || $rowData[0][10] == 'DIP'){
                    // $date_hindiy = 167.2;
                    $date_hindiy = 168.4;
                }
                else if($rowData[0][10] == 'DO'){
                    $date_hindiy = 170.6;
                }

                $result = $this->GetStringPositions(
                    array(
                        array($str, $krutidev100, '', $date_font_size), 
                        array($date_hindistr, $krutidev101, '', $date_font_size),
                        array($hindiword_str, $krutidev100, '', $date_font_size)
                    ),$pdf
                );

                $pdf->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[0], $date_hindiy);
                $pdf->Cell(0, 0, $str, 0, false, 'L');

                $pdfBig->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($result[0], $date_hindiy);
                $pdfBig->Cell(0, 0, $str, 0, false, 'L');

                $pdf->SetFont($krutidev101, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[1], $date_hindiy);
                $pdf->Cell(0, 0, $date_hindistr, 0, false, 'L');

                $pdfBig->SetFont($krutidev101, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($result[1], $date_hindiy);
                $pdfBig->Cell(0, 0, $date_hindistr, 0, false, 'L');

                $pdf->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[2], $date_hindiy);
                $pdf->Cell(0, 0, $hindiword_str, 0, false, 'L');

                $pdfBig->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($result[2], $date_hindiy);
                $pdfBig->Cell(0, 0, $hindiword_str, 0, false, 'L');


                //grade in hindi
                $grade_hindix = 39.7;
                if($rowData[0][10] == 'DB' || $rowData[0][10] == 'INT' || $rowData[0][10] == 'DIP'){
                    
                    $grade_hindiy = 177.5;
                }
                else if($rowData[0][10] == 'DO'){
                    $grade_hindiy = 181.7;
                }
                $grade_hindistr = 'lh-th-ih-,-'.trim($rowData[0][7]);
                $pdfBig->SetFont($krutidev101, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($grade_hindix, $grade_hindiy);
                $pdfBig->Cell(0, 0, $grade_hindistr, 0, false, 'L');

                $pdf->SetFont($krutidev101, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_hindix, $grade_hindiy);
                $pdf->Cell(0, 0, $grade_hindistr, 0, false, 'L');


                //degree name in hindi
                $degree_hindi_font_size = '25';
                if($rowData[0][10] == 'INT'){
                    $degree_hindi_font_size = '18';
                    $degree_hindiy = 188.8;
                }
                $degree_hindix = 66;
                if($rowData[0][10] == 'DB' || $rowData[0][10] == 'DIP'){
                    $degree_hindiy = 185.2;
                }
                else if($rowData[0][10] == 'DO'){
                    $degree_hindiy = 192;
                }
                $degree_hindistr = trim($rowData[0][12]);
                $pdfBig->SetFont($krutidev101, '', $degree_hindi_font_size, '', false);
                $pdfBig->SetTextColor(0,92,88,7,false,'');
                $pdfBig->SetXY(10, $degree_hindiy);
                $pdfBig->Cell(190, 0, $degree_hindistr, 0, false, 'C');

                $pdf->SetFont($krutidev101, '', $degree_hindi_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $degree_hindiy);
                $pdf->Cell(190, 0, $degree_hindistr, 0, false, 'C');

                //branch name in hindi
                if($rowData[0][10] == 'DB' || $rowData[0][10] == 'INT' || $rowData[0][10] == 'DIP'){
                    $branch_hindi_font_size = '20';
                    $branch_hindiy = 196.5;
                    if($rowData[0][10] == 'INT'){
                        $branch_hindi_font_size = '15';
                        $branch_hindiy = 196.8;
                    }
                    $branch_hindix = 75.2;
                    $branch_hindistr = trim($rowData[0][14]);
                    $pdfBig->SetFont($krutidev101, '', $branch_hindi_font_size, '', false);
                    $pdfBig->SetTextColor(0,92,88,7,false,'');
                    $pdfBig->SetXY(10, $branch_hindiy);
                    $pdfBig->Cell(190, 0, $branch_hindistr, 0, false, 'C');

                    $pdf->SetFont($krutidev101, '', $branch_hindi_font_size, '', false);
                    $pdf->SetTextColor(0,92,88,7,false,'');
                    $pdf->SetXY(10, $branch_hindiy);
                    $pdf->Cell(190, 0, $branch_hindistr, 0, false, 'C');
                }

                //today date
                $today_date_font_size = '12';
                
                $today_datex = 95;
                $today_datey = 273.8;
                $todaystr = 'September, 2020';
                $pdfBig->SetFont($timesNewRomanBI, '', $today_date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($today_datex, $today_datey);
                $pdfBig->Cell(0, 0, $todaystr, 0, false, 'L');

                $pdf->SetFont($timesNewRomanBI, '', $today_date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(84, $today_datey);
                $pdf->Cell(47, 0, $todaystr, 0, false, 'C');

                //1D Barcode
                $style1D = array(
                    'position' => '',
                    'align' => 'C',
                    'stretch' => false,
                    'fitwidth' => true,
                    'cellfitalign' => '',
                    'border' => false,
                    'hpadding' => 'auto',
                    'vpadding' => 'auto',
                    'fgcolor' => array(0,0,0),
                    'bgcolor' => false, //array(255,255,255),
                    'text' => false,
                    'font' => 'helvetica',
                    'fontsize' => 8,
                    'stretchtext' => 4
                );              
                
                $barcodex = 84;
                $barcodey = 278;
                $barcodeWidth = 46;
                $barodeHeight = 12;
                $pdfBig->SetAlpha(1);
                // Barcode CODE_39 - Roll no 
                $pdfBig->write1DBarcode(trim($rowData[0][1]), 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1D, 'N');

                $pdf->SetAlpha(1);
                // Barcode CODE_39 - Roll no 
                $pdf->write1DBarcode(trim($rowData[0][1]), 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1D, 'N');



                $footer_name_font_size = '12';
                $footer_namex = 84.9;
                $footer_namey = 290.9;
                $footer_namestr = strtoupper(trim($rowData[0][4]));



                $pdfBig->SetOverprint(true, true, 0);
                $pdfBig->SetFont($arial, '', $footer_name_font_size, '', false);
                
                $pdfBig->SetTextSpotColor('Spot Dark Green', 100);
                $pdfBig->SetXY(10, $footer_namey);
                $pdfBig->Cell(190, 0, $footer_namestr, 0, false, 'C');
                $pdfBig->SetOverprint(false, false, 0);

                //repeat line
                $repeat_font_size = '9.5';
                $repeatx= 0;

                $name_repeaty = 242.9;
                $name_repeatstr = strtoupper(trim($rowData[0][4])).' '.'CGPA '.number_format(trim($rowData[0][6]),2).' '.strtoupper(trim($rowData[0][8])); 
                $name_repeatstr .= $name_repeatstr . $name_repeatstr . $name_repeatstr . $name_repeatstr . $name_repeatstr;

                $degree_repeaty = 247;
                $degree_repeatstr = strtoupper(trim($rowData[0][11])).' '.strtoupper(trim($rowData[0][13])); 
                $degree_repeatstr .= $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr;

                //grade repeat line
                $grade_repeaty = 251.1;
                $grade_repeatstr = 'GALGOTIAS UNIVERSITY'; 
                $grade_repeatstr .= $grade_repeatstr . $grade_repeatstr . $grade_repeatstr . $grade_repeatstr . $grade_repeatstr;

                //date repeat line
                $date_repeaty = 255.2;
                $date_repeatstr = 'GALGOTIAS UNIVERSITY'; 
                $date_repeatstr .= $date_repeatstr . $date_repeatstr . $date_repeatstr . $date_repeatstr . $date_repeatstr;


                
                $pdfBig->SetTextColor(0,0,0,5,false,'');
                $pdfBig->SetFont($arialb, '', $repeat_font_size, '', false);
                $pdfBig->StartTransform();
                $pdfBig->SetXY($repeatx, $name_repeaty);
                $pdfBig->Cell(0, 0, $name_repeatstr, 0, false, 'L');
                $pdfBig->StopTransform();

                $pdf->SetTextColor(0,0,0,5,false,'');
                $pdf->SetFont($arialb, '', $repeat_font_size, '', false);
                $pdf->StartTransform();
                $pdf->SetXY($repeatx, $name_repeaty);
                $pdf->Cell(0, 0, $name_repeatstr, 0, false, 'L');
                $pdf->StopTransform();


                //degree repeat line
             
                $pdfBig->SetFont($arialb, '', $repeat_font_size, '', false);
                $pdfBig->StartTransform();
                $pdfBig->SetXY($repeatx, $degree_repeaty);
                $pdfBig->Cell(0, 0, $degree_repeatstr, 0, false, 'L');
                $pdfBig->StopTransform();

                $pdf->SetFont($arialb, '', $repeat_font_size, '', false);
                $pdf->StartTransform();
                $pdf->SetXY($repeatx, $degree_repeaty);
                $pdf->Cell(0, 0, $degree_repeatstr, 0, false, 'L');
                $pdf->StopTransform();

                //grade repeat line
               
                $pdfBig->SetFont($arialb, '', $repeat_font_size, '', false);
                $pdfBig->StartTransform();
                $pdfBig->SetXY($repeatx, $grade_repeaty);
                $pdfBig->Cell(0, 0, $grade_repeatstr, 0, false, 'L');
                $pdfBig->StopTransform();

                $pdf->SetFont($arialb, '', $repeat_font_size, '', false);
                $pdf->StartTransform();
                $pdf->SetXY($repeatx, $grade_repeaty);
                $pdf->Cell(0, 0, $grade_repeatstr, 0, false, 'L');
                $pdf->StopTransform();

                //date repeat line
                
                $pdfBig->SetFont($arialb, '', $repeat_font_size, '', false);
                $pdfBig->StartTransform();
                $pdfBig->SetXY($repeatx, $date_repeaty);
                $pdfBig->Cell(0, 0, $date_repeatstr, 0, false, 'L');
                $pdfBig->StopTransform();

                $pdf->SetFont($arialb, '', $repeat_font_size, '', false);
                $pdf->StartTransform();
                $pdf->SetXY($repeatx, $date_repeaty);
                $pdf->Cell(0, 0, $date_repeatstr, 0, false, 'L');
                $pdf->StopTransform();
                
                //ce sign visible
                $ce_sign_visible_path = public_path().'/backend/templates/'.$FID['template_id'].'/CE_Sign_'.$excel_row.'.png';
                
                $ce_sign_visibllex = 26;
                $ce_sign_visiblley = 243.7;
                
                $ce_sign_visiblleWidth = 35;
                $ce_sign_visiblleHeight = 16;
                $pdfBig->image($ce_sign_visible_path,$ce_sign_visibllex,$ce_sign_visiblley,$ce_sign_visiblleWidth,$ce_sign_visiblleHeight,"",'','L',true,3600);
                $pdf->image($ce_sign_visible_path,$ce_sign_visibllex,$ce_sign_visiblley,$ce_sign_visiblleWidth,$ce_sign_visiblleHeight,"",'','L',true,3600);

                unlink(public_path().'/backend/templates/'.$FID['template_id'].'/CE_Sign_'.$excel_row.'.png');

                //vc sign visible
                $vc_sign_visible_path = 'C:\xampp\htdocs\seqr\public/backend/templates/'.$FID['template_id'].'\/VC_Sign_'.$excel_row.'.png';
                
                $vc_sign_visibllex = 168.5;
                $vc_sign_visiblley = 243.7;
                $vc_sign_visiblleWidth = 21;
                $vc_sign_visiblleHeight = 16;
                $pdfBig->image($vc_sign_visible_path,$vc_sign_visibllex,$vc_sign_visiblley,$vc_sign_visiblleWidth,$vc_sign_visiblleHeight,"",'','L',true,3600);
                $pdf->image($vc_sign_visible_path,$vc_sign_visibllex,$vc_sign_visiblley,$vc_sign_visiblleWidth,$vc_sign_visiblleHeight,"",'','L',true,3600);

                unlink(public_path().'/backend/templates/'.$FID['template_id'].'/VC_Sign_'.$excel_row.'.png');
                

                // Ghost image
                $ghost_font_size = '13';
                $ghostImagex = 10;
                $ghostImagey = 278.8;
                $ghostImageWidth = 68;
                $ghostImageHeight = 9.8;
                $name = str_replace(' ','',substr(trim($rowData[0][4]), 0, 6));
                $student_name = str_replace(' ','',$rowData[0][4]);
                $name = substr(trim(strtoupper($student_name)), 0, 6);
                

                $tmpDir = $this->createTemp(public_path().'/backend/canvas/ghost_images/temp');
               
                
                $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
                

                $pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, 10, "PNG", '', 'L', true, 3600);

                $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, 10, "PNG", '', 'L', true, 3600);

            $withoutExt = preg_replace('/\\.[^.\\s]{3,4}$/', '', $excelfile);

           // unlink($tmpDir);

            //delete temp dir 26-04-2022 
            CoreHelper::rrmdir($tmpDir);
            
            $template_name  = $FID['template_name'];
            $certName = str_replace("/", "_", $serial_no) .".pdf";
            
            $myPath = public_path().'/backend/temp_pdf_file';
            $dt = date("_ymdHis");
            
            $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');

            
            $student_table_id = $this->addCertificate($serial_no, $certName, $dt,$FID['template_id'],$admin_id);
            

            $username = $admin_id['username'];
            date_default_timezone_set('Asia/Kolkata');

            $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
            $date = date('Y-m-d H:i:s').PHP_EOL;
            $print_datetime = date("Y-m-d H:i:s");
            

            $print_count = $this->getPrintCount($serial_no);
            $printer_name = /*'HP 1020';*/$printer_name;
            $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,$template_name,$admin_id,$student_table_id);

            $new_excel_row = (int)$excel_row + 1;

            
            $excelDelPath = public_path().'/backend/templates/'.$FID['template_id'].'/'.$excelfile;
            if(file_exists($excelDelPath)){
            
                unlink($excelDelPath);
            }
            

            $progressbar_array = ['excel_row'=>$new_excel_row,'is_progress'=>'yes','highestRow'=>$highestRow,'msg'=>'','success'=>true];
            return $progressbar_array;


        }
        
        $msg = '';
        if(is_dir($tmpDir)){
            rmdir($tmpDir);
        }   
        $highestRecord = $highestRow - 1;
        $file_name = $template_name.'_'.$highestRecord.'_'.date("Ymdhms").'.pdf';
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


        
        $filename = public_path().'/backend/tcpdf/exmples/'.$file_name;
        $pdfBig->output($filename,'F');
        

        
        $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/exmples/'.$file_name);
        @unlink($filename);
        $pdf_name = str_replace(' ', '+', $file_name);

        $path = public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/';
        $msg = "<b>Click <a href='".$path.$pdf_name."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";

        $progressbar_array_last = ['excel_row'=>$excel_row,'is_progress'=>'no','highestRow'=>$highestRow,'msg'=>$msg];
            return $progressbar_array_last;

        
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


    public function createTemp($path){
        //create ghost image folder
        $tmp = date("ymdHis");
        
        $tmpname = tempnam($path, $tmp);
        //unlink($tmpname);
        //mkdir($tmpname);
        if (file_exists($tmpname)) {
         unlink($tmpname);
        }
        mkdir($tmpname, 0777);
        return $tmpname;
    }

    public function isEmptyRow($row){
        foreach($row as $cell){
            if (null !== $cell) return false;
        }
        return true;
    }

    public function addCertificate($serial_no, $certName, $dt,$template_id,$admin_id)
    {

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $file1 = public_path().'/backend/temp_pdf_file/'.$certName;
        $file2 = public_path().'/backend/pdf_file/'.$certName;
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();



        /*copy($file1, $file2);        
        $aws_qr = \File::copy($file2,public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName);               
        @unlink($file2);*/
		$source=\Config::get('constant.directoryPathBackward')."\\backend\\temp_pdf_file\\".$certName;//$file1
		$output=\Config::get('constant.directoryPathBackward')."\\backend\\pdf_file\\".$certName;
		CoreHelper::compressPdfFile($source,$output);
        @unlink($file1);

        $sts = '1';
        $datetime  = date("Y-m-d H:i:s");
        $ses_id  = $admin_id["id"];
        $certName = str_replace("/", "_", $certName);

        $get_config_data = Config::select('configuration')->first();
     
        $c = explode(", ", $get_config_data['configuration']);
        $key = "";


        $tempDir = public_path().'/backend/qr';
        $key = strtoupper(md5($serial_no.$dt)); 
        $codeContents = $key;
        $fileName = $key.'.png'; 
        
        $urlRelativeFilePath = 'qr/'.$fileName; 

        
        $resultu = StudentTable::where('serial_no',$serial_no)->update(['status'=>'0']);
        // Insert the new record
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $result = StudentTable::create(['serial_no'=>$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);

        //$dbName = 'seqr_demo';
        
        \DB::disconnect('mysql'); 
        
        \Config::set("database.connections.mysql", [
            'driver'   => 'mysql',
            'host'     => \Config::get('constant.SDB_HOST'),
            "port" => \Config::get('constant.SDB_PORT'),
            'database' => \Config::get('constant.SDB_NAME'),
            'username' => \Config::get('constant.SDB_UN'),
            'password' => \Config::get('constant.SDB_PW'),
            "unix_socket" => "",
            "charset" => "utf8mb4",
            "collation" => "utf8mb4_unicode_ci",
            "prefix" => "",
            "prefix_indexes" => true,
            "strict" => true,
            "engine" => null,
            "options" => []
        ]);
        \DB::reconnect();

        $last_add = StudentTable::select('id')->orderBy('id','desc')->skip(0)->take(1)->first();
        $active_documents = StudentTable::select('id')->where('site_id',$auth_site_id)->where('status','1')->count();
        $inactive_documents = StudentTable::select('id')->where('site_id',$auth_site_id)->where('status','0')->count();

        $last_genrate_date = StudentTable::select('created_at')->where('site_id',$auth_site_id)->where('id',$last_add['id'])->first();

        SiteDocuments::where('site_id',$auth_site_id)->update(['active_documents'=>$active_documents]);
        SiteDocuments::where('site_id',$auth_site_id)->update(['inactive_documents'=>$inactive_documents]);
        SiteDocuments::where('site_id',$auth_site_id)->update(['last_genration_date'=>$last_genrate_date['created_at']]);
        if($subdomain[0] == 'demo')
        {
            $dbName = 'seqr_'.$subdomain[0];
        }else{

            $dbName = 'seqr_d_'.$subdomain[0];
        }

        \DB::disconnect('mysql');     
        \Config::set("database.connections.mysql", [
            'driver'   => 'mysql',
            'host'     => \Config::get('constant.DB_HOST'),
            "port" => \Config::get('constant.DB_PORT'),
            'database' => $dbName,
            'username' => \Config::get('constant.DB_UN'),
            'password' => \Config::get('constant.DB_PW'),
            "unix_socket" => "",
            "charset" => "utf8mb4",
            "collation" => "utf8mb4_unicode_ci",
            "prefix" => "",
            "prefix_indexes" => true,
            "strict" => true,
            "engine" => null,
            "options" => []
        ]);
        \DB::reconnect();

        return $result['id'];
    }

    public function getPrintCount($serial_no)
    {
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $numCount = PrintingDetail::select('id')->where('sr_no',$serial_no)->count();
        
        return $numCount + 1;
    }

    public function addPrintDetails($username, $print_datetime, $printer_name, $printer_count, $print_serial_no, $sr_no,$template_name,$admin_id,$student_table_id)
    {
        // dd($sr_no);
        $sts = 1;
        $datetime = date("Y-m-d H:i:s");
        $ses_id = $admin_id["id"];

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

        $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$sr_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1,'student_table_id'=>$student_table_id]);
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
                "Z" => array(20827, 876)
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
                "Z" => array(20842, 792)
            
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
                "Z" => array(17710, 680)
                
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
                "Z" => array(20867, 848)
            
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
                "Z" => array(20880, 800)
            
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
                "Z" => array(80884, 808)
            
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
}

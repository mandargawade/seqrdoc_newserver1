<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\models\BackgroundTemplateMaster;
use Session,TCPDF,TCPDF_FONTS,Auth,DB;
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
use App\Helpers\CoreHelper;
use Helper;
use setasign\Fpdi\Fpdi;

class PdfGenerateUtilityNIITJobDegreeCertificate
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

	function negate($image)
	{
		if(function_exists('imagefilter'))
		{
		return imagefilter($image, IMG_FILTER_NEGATE);
		}
		for($x = 0; $x < imagesx($image); ++$x)
		{
			for($y = 0; $y < imagesy($image); ++$y)
			{
			$index = imagecolorat($image, $x, $y);
			$rgb = imagecolorsforindex($index);
			$color = imagecolorallocate($image, 255 - $rgb['red'], 255 - $rgb['green'], 255 - $rgb['blue']);
			imagesetpixel($im, $x, $y, $color);
			}
		}
		return(true);
	}	
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    {
        $pdf_data = $this->pdf_data;        
        $studentDataOrg=$pdf_data['studentDataOrg'];
        		
		$Photo_key=10;
		$subj_col = $pdf_data['subj_col'];
		$subj_start = $pdf_data['subj_start'];
		$subj_end = $pdf_data['subj_end'];       
		$template_id=$pdf_data['template_id'];
        $dropdown_template_id=$pdf_data['dropdown_template_id'];
        $previewPdf=$pdf_data['previewPdf'];
        $excelfile=$pdf_data['excelfile'];
        $auth_site_id=$pdf_data['auth_site_id'];
         $previewWithoutBg=$previewPdf[1];
        $previewPdf=$previewPdf[0];
        
        $first_sheet=$pdf_data['studentDataOrg']; // get first worksheet rows
        //$second_sheet=$pdf_data['subjectsMark']; // get second worksheet rows
        $total_unique_records=count($first_sheet);
        $last_row=$total_unique_records+1;
        //$course_count = array_count_values(array_column($second_sheet, '0'));
        //$max_course_count = (max($course_count)); 
        
        if(isset($pdf_data['generation_from'])&&$pdf_data['generation_from']=='API'){
            $admin_id=$pdf_data['admin_id'];
        }else{
            $admin_id = \Auth::guard('admin')->user()->toArray();  
        }
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $excelfile_pdf=public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$pdf_data['excelfile_pdf'];
        $excelfile_pdf_uc=public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'."uc_".$pdf_data['excelfile_pdf'];

       /* $systemConfig = SystemConfig::select('sandboxing','printer_name')->where('site_id',$auth_site_id)->first();
        $printer_name = $systemConfig['printer_name'];*/

      
       /* print_r($pdf_data);
        exit;*/
         $ghostImgArr=[];

        //Excel Data Fet
        /*$inputFileType="Xlsx";
        $fullpath=public_path().'/demo/NIIT/NIITExcel.xlsx';
        $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
        *//**  Load $inputFileName to a Spreadsheet Object  **/
        /*$objPHPExcel1 = $objReader->load($fullpath);
        $sheet1 = $objPHPExcel1->getSheet(0);
        $highestColumn1 = $sheet1->getHighestColumn();
        $highestRow1 = $sheet1->getHighestDataRow();
        $excelData = $sheet1->rangeToArray('A1:' . $highestColumn1 . $highestRow1, NULL, TRUE, FALSE);
*/

        $excelData =$studentDataOrg;

        
       
        // initiate FPDI
        //$pdf = new Fpdi('P', 'mm', array('205', '315'));
        $pdf = new Fpdi('P', 'mm', array('228.6', '317.5'));
        //add a page
        $pdf->AddPage();
        
        try {
          $pageCount=  $pdf->setSourceFile($excelfile_pdf);

        } catch (\Exception $exception) {
         
            exec('pdftk '.$excelfile_pdf." output ".$excelfile_pdf_uc." uncompress");
           $pageCount= $pdf->setSourceFile($excelfile_pdf_uc);
 
        }
      
        $generated_documents=0;

        $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {

        //For Custom Loader
            $startTimeLoader =  date('Y-m-d H:i:s'); 

        
        //Read each line of excel data
       $name=$excelData[$pageNo-1][0];  
       $name=preg_replace("/[^A-Za-z0-9 ]/", '', $name);
       $name=strtoupper($name);
       $name = preg_replace('/\s+/', '', $name);

       //exit; 
       // $name=trim($excelData[$pageNo-1][6]);
        // $rollNo=trim($excelData[$pageNo-1][1]);
        // $department=trim($excelData[$pageNo-1][7]);
        // $department=str_replace('DEPARTMENT OF ', '', $department);
        //$name= "asda";
        $department="";
        //$ypDate = $excelData[$pageNo-1][10];
        $ypDate =substr($name, 0, 12);

        if(!empty($name)){
            /*echo $name;
            echo "<br>";*/
                
            $tplIdx = $pdf->importPage($pageNo);
            
            if($pageNo!=1){
                $size = $pdf->getTemplateSize($tplIdx);  
            //    print_r($size);
              //  exit; 
                // create a page (landscape or portrait depending on the imported page size)
                $pdf->AddPage('P', array($size[0], $size[1])); 
            }
            
            
           
            $pdf->useTemplate($tplIdx, null, null, 228.6, 317.5, true);

            /***********QR Code***************************/

            // $qrText=trim($name).', '.trim($rollNo).', '.trim($department);
            // $key=$rollNo;
            
            //  $temp_path = public_path().'/demo/utility/qr/'.$key.'.png';

            //  $qrImagex=15;
            //  $qrImagey=16;
            //  $qrImageWidth=20;
            //  $qrImageHeight=20;
           
            // $qrCodeHeight = 20;//21
            // $ecc = 'L';
            // $pixel_Size = 2.65;
            // $frame_Size = 1;  
            //  \PHPQRCode\QRcode::png($qrText, $temp_path, $ecc, $pixel_Size, $frame_Size);
            
            /*
		 \QrCode::size(75.6)
                ->format('png')
                ->generate($qrText, $temp_path); 
	*/

           // $pdf->Image($temp_path, $qrImagex, $qrImagey, $qrImageWidth, $qrImageHeight, "PNG", '', 'L', true, 3600);

            /*******************End QR **********************************/

            /************Ghost Image Start***********************************/
            

            
            $ghost_font_size = '10';
            $ghostImagex = 175;//81.5;//75.5
            $ghostImagey = 294.5;
            

            $name=preg_replace("/[^A-Za-z0-9 ]/", '', $name);
            $name=strtoupper($name);
            $name = str_replace(' ','',$name);
            $name=substr($name, 0, 12);
            $strLen=strLen($name);
            $widthPerChar=5.6; //7.33 for font 13 Sikkim
            $ghostImageWidth = $widthPerChar*$strLen;//44
            $ghostImageHeight = 8;
            $name = str_replace(' ','',$name);
            if(!array_key_exists($name, $ghostImgArr))
            {
                $w = $this->createGhostImageNIITY($tmpDir, $name ,$ghost_font_size,$name);
                $ghostImgArr[$name] = $w;   
            }
            else{
                $w = $ghostImgArr[$name];
            }

            $ghostImagex = $ghostImagex -(($strLen*5/2));
            $pdf->Image("$tmpDir/" . $name.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);
           

            /*********Ghost Image End**********************/


            /***Yellow Patch**/
            // $name=preg_replace("/[^A-Za-z0-9 ]/", '', $name);
            // $name=strtoupper($name);
            $qrCodex=180;
            $qrCodey=294.5;
            $qrCodeHeight=8;
            $len = strlen($ypDate);
            $totalWidth=0;
            
            for($i = 0; $i < $len; $i++) {
                $value = $ypDate[$i];
                if($value == '/') {
                    $ypImage = 'slash';
                } else {
                    $ypImage = $value;
                }
                $qr_code_path = public_path().'\\backend\canvas\yellowpatchimages\/'.$ypImage.'.png';
                 
                list($width, $height) = getimagesize($qr_code_path);
                //$width;
                //exit;
                $qrCodeWidth=$width*0.04 ;
                $totalWidth=$totalWidth+$qrCodeWidth;

                

               //echo "<br>";
               // $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
                //$qrCodex=$qrCodex+$qrCodeWidth;
            }
               
            $qrCodex=20;
            // exit;
             for($i = 0; $i < $len; $i++) {
                $value = $ypDate[$i];
                if($value == '/') {
                    $ypImage = 'slash';
                } else {
                    $ypImage = $value;
                }
                
                $qr_code_path = public_path().'\\backend\canvas\yellowpatchimages\/'.$ypImage.'.png';
                 
                list($width, $height) = getimagesize($qr_code_path);
                //$width;
                //exit;
                $qrCodeWidth=$width*0.04 ;
                
            

               // echo $qrCodex;
               // echo "<br>";
                $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
                $qrCodex=$qrCodex+$qrCodeWidth;
            }
            /***Yellow Patch End**/
            

       

        }

            $generated_documents++;

              //For Custom loader calculation
                //echo $generated_documents;
              $endTimeLoader = date('Y-m-d H:i:s');
              $time1 = new \DateTime($startTimeLoader);
              $time2 = new \DateTime($endTimeLoader);
              $interval = $time1->diff($time2);
              $interval = $interval->format('%s');

              $jsonArr=array();
              $jsonArr['token'] = $pdf_data['loader_token'];
              $jsonArr['generatedCertificates'] =$generated_documents;
              $jsonArr['timePerCertificate'] =$interval;
             
              $loaderData=CoreHelper::createLoaderJson($jsonArr,0);
        }

         CoreHelper::rrmdir($tmpDir);
        //echo $a;//67.5//7
      

       /* for($dataIndex=0;$dataIndex<count($studentDataOrg);$dataIndex++){
            

           

           

           
            
          


          


            
       } */
        
      
       $msg = '';
        
        $file_name =  str_replace("/", "_",'NIIT_'.date("Ymdhms")).'.pdf';
        
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


       $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        
        $pdf->output($filename,'F');


            $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
            @unlink($filename);
            $no_of_records = count($studentDataOrg);
            $user = $admin_id['username'];
            $template_name="UNEBCert";
           /* if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                // with sandbox
                
                $result = SbExceUploadHistory::create(['template_name'=>$template_name,'excel_sheet_name'=>$excelfile,'pdf_file'=>$file_name,'user'=>$user,'no_of_records'=>$no_of_records,'site_id'=>$auth_site_id]);
            }else{
                // without sandbox
                $result = ExcelUploadHistory::create(['template_name'=>$template_name,'excel_sheet_name'=>$excelfile,'pdf_file'=>$file_name,'user'=>$user,'no_of_records'=>$no_of_records,'site_id'=>$auth_site_id]);
            } */
            
        
        $protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';
        $path = $protocol.'://'.$subdomain[0].'.'.$subdomain[1].'.com/';
        $pdf_url=$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name;
        $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name."'class='downloadpdf download' target='_blank'>Here</a> to download file<b>";
      
   

        return $msg;



    }


 public function createTemp($path){
        //create ghost image folder
        $tmp = date("ymdHis");
        // print_r($path);
        // dd($tmp);
        $tmpname = tempnam($path, $tmp);
        //unlink($tmpname);
        //mkdir($tmpname);
        if (file_exists($tmpname)) {
         unlink($tmpname);
        }
        mkdir($tmpname, 0777);
        return $tmpname;
    }



public function createGhostImageNIITY($tmpDir, $name = "",$font_size,$customName="")
    {
        if($name == "")
            return;
        $name = strtoupper($name);
        // Create character image
        $font_size=10;
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
            

            $filename = public_path()."/backend/canvas/ghost_images/7_1.png";//F10_H5_W180//F10_RND_latest //F10_H5_W180_TWO_IMG
//echo 'convert '.public_path().'/backend/canvas/ghost_images/7.png '.public_path().'/backend/canvas/ghost_images/out.png';
           // exec('convert '.public_path().'/backend/canvas/ghost_images/7.png '.public_path().'/backend/canvas/ghost_images/out.png');
            $charsImage = imagecreatefrompng($filename);
            
           // imagealphablending($charsImage, false);
           // imagesavealpha($charsImage, true);
            //$filename = public_path()."/backend/canvas/ghost_images/F10_RND_latest.png";//F10_H5_W180
            //$charsImage2 = imagecreatefrompng($filename);
            //imagealphablending($charsImage2, false);
            //imagesavealpha($charsImage2, true);
            
            $size = getimagesize($filename);
            // Create Backgoround image
            $filename   = public_path()."/backend/canvas/ghost_images/alpha_GHOST.png";//alpha_GHOST
            $bgImage = imagecreatefrompng($filename);

            $currentX = 0;
            $len = strlen($name);
            
            for($i = 0; $i < $len; $i++) {
                $value = $name[$i];
                if(!array_key_exists($value, $AlphaPosArray))
                    continue;
                $X = $AlphaPosArray[$value][0];
                $W = $AlphaPosArray[$value][1];
               // imagecopymerge($bgImage, $charsImage, $currentX, 0, $X, 0, $W, $size[1], 70);
                 imagecopymerge($bgImage, $charsImage, $currentX, 0, $X, 0, $W, $size[1], 100);
                $currentX += $W;
            }
            
           

            $rect = array("x" => 0, "y" => 0, "width" => $currentX, "height" => $size[1]);
             $im = imagecrop($bgImage, $rect);

            //imagepng($im, "$tmpDir/" . $name."".$font_size.".png");
             imagepng($im, "$tmpDir/" . $customName.".png");
            
            imagedestroy($bgImage);
            imagedestroy($charsImage);
            return round((5 * $currentX)/ $size[1]);

        
    }
   
  
}

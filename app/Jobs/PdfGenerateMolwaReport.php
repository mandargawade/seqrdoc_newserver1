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
class PdfGenerateMolwaReport
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
        $pdf_data = $this->pdf_data;        
        $district=$pdf_data['district'];
        $upazila=$pdf_data['upazila'];
        $is_alive=$pdf_data['is_alive'];
		if($is_alive=="হ্যাঁ"){ $str_isalive="ALIVE"; }else{ $str_isalive="DEAD"; }
        $report_name=$pdf_data['report_name'];
		switch ($report_name) {
		  case "1":
			$str_qty="ID CARD Qty";
			$report_title="Register Book of Smart ID Card";
			$acknowledge="Received by";
			if($str_isalive == "ALIVE"){
				$check_column="generated_at";
			}else{
				$check_column="c_generated_at";
			}
			break;
		  case "2":
			$str_qty="Certificate Qty";
			$report_title="Register Book of Digital Certificate";
			$acknowledge="Received by";
			$check_column="c_generated_at";
			break;
		  case "3":
			$str_qty="Certificate Qty";
			$report_title="Delivery Challan (Digital Certificate)";
			$acknowledge="";
			$challan_col="Total Certificate";
			$check_column="c_generated_at";
			break;
		  case "4":
			$str_qty="ID CARD Qty";
			$report_title="Delivery Challan (Smart ID Card)";
			$acknowledge="";
			$challan_col="Total ID Card";
			if($str_isalive == "ALIVE"){
				$check_column="generated_at";
			}else{
				$check_column="c_generated_at";
			}
			break;
		  case "5":
			$str_qty="Certificate Qty";
			$report_title="Digital Certificate";
			$acknowledge="Ack/Sig";
			$check_column="c_generated_at";
			break;
		  case "6":
			$str_qty="ID CARD Qty";
			$report_title="Digital Smart ID Card";
			$acknowledge="Ack/Sig";
			$challan_col="Total ID Card";
			if($str_isalive == "ALIVE"){
				$check_column="generated_at";
			}else{
				$check_column="c_generated_at";
			}
			break;
		  default:
			$str_qty="";
			$report_title="";
			$acknowledge="";
			$check_column="";
		}		
        $admin_id=$pdf_data['admin_id'];
        $record_unique_id = date('dmYHis').'_'.uniqid();
		if($upazila=='All'){
			$whereclause = [
				['district', $district],
				['is_alive', $is_alive],
				['active', 'Yes']	
			];
			$whereclause_challan = [
				['district', $district],
				['is_alive', $is_alive],
				['active', 'Yes']	
			];
		}else{
			$whereclause = [
				['district', $district],
				['upazila_or_thana', $upazila],
				['is_alive', $is_alive],
				['active', 'Yes']	
			];
			$whereclause_challan = [
				['district', $district],
				['is_alive', $is_alive],
				['active', 'Yes']	
			];
			/*$whereclause = [
			'district'=>$district, 'upazila_or_thana'=>$upazila, 'is_alive'=>$is_alive, 'active'=>'Yes'
			];*/
		}
        if($report_name != 3 && $report_name != 4){
			if($upazila=='All'){
				$data_s=DB::table('freedom_fighter_list')
				->where($whereclause)
				->whereNotNull($check_column)
				->select('upazila_or_thana', DB::raw('COUNT(*) AS upazila_records'))
				->groupBy('upazila_or_thana')
				->orderBy('upazila_records', 'desc')
				->get();
				$record_count=0;
				foreach ($data_s as $value) {
					$record_count+=$value->upazila_records;
				}
			}else{
				$data_s=DB::table('freedom_fighter_list')
				->where($whereclause)
				->whereNotNull($check_column)
				->select('ff_id', 'ff_name', 'father_or_husband_name', 'mother_name', 'village_or_ward', 'district', 'upazila_or_thana')
				->get();		
				$record_count=count($data_s); 
			}			
		}else{
			//$data_s = DB::select(DB::raw('SELECT upazila_or_thana, COUNT(*) AS upazila_records from freedom_fighter_list where district = "'.$district.'" GROUP BY upazila_or_thana'));
			$data_s=DB::table('freedom_fighter_list')
			->where($whereclause_challan)
			->whereNotNull($check_column)
			->select('upazila_or_thana', DB::raw('COUNT(*) AS upazila_records'))
			->groupBy('upazila_or_thana')
			->orderBy('upazila_records', 'desc')
			->get();
			$record_count=0;
		}	
		//print_r($data_s);
		//exit();
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
          
        //$pdfBig = new PDF_MC_Table('P', 'mm', array('210', '297'), true, 'UTF-8', false);
		$pdfBig = new PDF_MC_Table(['orientation' => 'P', 'mode' => 'utf-8', 'format' => ['210', '297'],'margin_left' => 6, 'margin_right' => 6, 'tempDir'=>storage_path('tempdir')]);
        $pdfBig->SetCreator('Molwa');
        $pdfBig->SetAuthor('TCPDF');
        $pdfBig->SetTitle('Report');
        $pdfBig->SetSubject('');

        //set fonts
        $arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.TTF', 'TrueTypeUnicode', '', 96);
        $arialb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arialb.TTF', 'TrueTypeUnicode', '', 96);
        $arialNarrow = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\arialn.TTF', 'TrueTypeUnicode', '', 96);
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', 'TrueTypeUnicode', '', 96);        
        $nikosh = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Nikosh.TTF', 'TrueTypeUnicode', '', 96);        
        
        $left_logo=public_path().'\\'.$subdomain[0].'\backend\images\left_logo.jpg'; 
        $right_logo=public_path().'\\'.$subdomain[0].'\backend\images\right_logo.jpg'; 
		$pdfBig->SetTextColor(1, 0, 128);
		if($report_name != 3 && $report_name != 4){
			if($upazila=='All'){
				$pdfBig->setHtmlHeader('<table cellspacing="0" cellpadding="1" border="0" width="100%"><tr><td colspan="4" align="center" style="font-family:'.$arialb.';font-size:18;color:#010080;letter-spacing: 1px;"><b>'.$report_title.'</b></td></tr>
				<tr><td width="15%"><img src="'.$left_logo.'" /></td><td width="40%" style="font-family:'.$arial.';font-size:13;"><span style="font-family:'.$arialb.';font-size:13;color:#ff0300;">SECURA BANGLADESH LIMITED</span><br />Security and Confidential Printers</td><td width="15%"><img src="'.$right_logo.'" /></td><td width="40%" style="font-family:'.$nikosh.';font-size:25;text-align:center;">গণপ্রজাতন্ত্রী বাংলাদেশ সরকার <br><span style="font-family:'.$nikosh.';font-size:30;">মুক্তিযুদ্ধ বিষয়ক মন্ত্রণালয়</span></td></tr>
				<tr><td colspan="4" height="10"></td></tr>
				<tr><td colspan="4" height="30" style="font-family:'.$arialb.';font-size:15;color:#010080;border-top: 1px solid gray;border-bottom: 1px solid gray;border-spacing: 5px 1rem;">Details Report</td></tr>
				</table>
				<table cellspacing="0" cellpadding="1" border="0" width="100%" style="font-family:'.$arialb.';font-size:13;color:#010080;">
				<tr><td width="35%">District : <span style="font-family:'.$nikosh.';font-size:20;color:black;">'.$district.'</span></td><td width="35%">Upazila/Thana : <span style="font-family:'.$nikosh.';font-size:20;color:black;">'.$upazila.'</span></td><td width="20%">'.$str_qty.' : '.$record_count.'</td><td width="10%">'.$str_isalive.'</td></tr>
				<tr><td colspan="4" height="10"></td></tr>
				</table>
				<table cellspacing="0" cellpadding="0" border="0" width="100%" style="font-family:'.$arial.';font-size:13;color:#010080;">
				<tr>
				<td style="width:5.1%;height:40;text-align:center;border-left:1px solid black;border-top:1px solid black;border-right:1px solid black;border-bottom:1px solid black;">SL</td>
				<td style="width:11.6%;text-align:center;border-top:1px solid black;border-bottom:1px solid black;">FF ID</td>
				<td style="width:22.7%;text-align:center;border-left:1px solid black;border-top:1px solid black;border-bottom:1px solid black;">FF Name</td>
				<td style="width:15.8%;text-align:center;border:1px solid black;">Father/Husband</td>
				<td style="width:13%;text-align:center;border-top:1px solid black;border-bottom:1px solid black;">Mother Name</td>
				<td style="width:14.3%;text-align:center;border:1px solid black;">Village/Moholla</td>
				<td style="text-align:center;border-top:1px solid black;border-right:1px solid black;border-bottom:1px solid black;">'.$acknowledge.'</td>
				</tr>
				</table>
				');
				$pdfBig->setHtmlFooter('<table cellspacing="0" cellpadding="1" border="0" width="100%"><tr><td colspan="4">&nbsp;</td></tr><tr><td width="2%"></td><td width="48%"></td><td width="48%" align="right"></td><td width="2%"></td></tr></table>');
				
				$pdfBig->AddPage();
				$xval=6.1; //Table x position		
				$pdfBig->SetFont($arial, '', 9.5);	
				$pdfBig->SetTextColor(0, 0, 0);
				foreach ($data_s as $value){
					$upazila_or_thana=$value->upazila_or_thana;
					$upazila_records=$value->upazila_records;
					//$pdfBig->SetWidths(array(197.7));
					//$pdfBig->Row(array(''), array('C'), array($arial), array('3'), $xval, 'short', '');
					$pdfBig->SetWidths(array(197.7));
					$pdfBig->Row(array($upazila_or_thana." ".$upazila_records), array('C'), array($nikosh), array('22'), $xval, 'short', 1);
					
					$whereclause_sub = [
						['district', $district],
						['upazila_or_thana', $upazila_or_thana],
						['is_alive', $is_alive],
						['active', 'Yes']	
					];					
					$data_u=DB::table('freedom_fighter_list')
					->where($whereclause_sub)
					->whereNotNull($check_column)
					->select('ff_id', 'ff_name', 'father_or_husband_name', 'mother_name', 'village_or_ward', 'district', 'upazila_or_thana')
					->get();		
					$record_counts=count($data_u);
					$cnt=0;
					foreach ($data_u as $value_sub){
						$ff_id=$value_sub->ff_id;
						$ff_name=$value_sub->ff_name;
						$father_or_husband_name=$value_sub->father_or_husband_name;
						$mother_name=$value_sub->mother_name;
						$village_or_ward=$value_sub->village_or_ward;
						$upazila_or_thana2=$value_sub->upazila_or_thana;
						$cnt+=1;
						$pdfBig->SetWidths(array(10,23,45,31,26,28,34.7));
						srand(microtime()*1000000);						
						$cell_border='LTRB'; 
						//$numlines_pdfBig = $pdfBig->getNumLines($ff_name, 45);			
						//if($numlines_pdfBig>1){ $subject_length='long'; }else{$subject_length='';}
						$subject_length='';
						$pdfBig->Row(array($cnt, $ff_id, $ff_name, $father_or_husband_name, $mother_name, $village_or_ward, ''), array('C','L','L','L','L','L','C'), array($arial,$nikosh,$nikosh,$nikosh,$nikosh,$nikosh,$nikosh), array('8','8','12','12','12','12'), $xval, $subject_length, 1);						
						
					
					}
				}
			}else{
				$pdfBig->setHtmlHeader('<table cellspacing="0" cellpadding="1" border="0" width="100%"><tr><td colspan="4" align="center" style="font-family:'.$arialb.';font-size:18;color:#010080;letter-spacing: 1px;"><b>'.$report_title.'</b></td></tr>
				<tr><td width="15%"><img src="'.$left_logo.'" /></td><td width="40%" style="font-family:'.$arial.';font-size:13;"><span style="font-family:'.$arialb.';font-size:13;color:#ff0300;">SECURA BANGLADESH LIMITED</span><br />Security and Confidential Printers</td><td width="15%"><img src="'.$right_logo.'" /></td><td width="40%" style="font-family:'.$nikosh.';font-size:25;text-align:center;">গণপ্রজাতন্ত্রী বাংলাদেশ সরকার <br><span style="font-family:'.$nikosh.';font-size:30;">মুক্তিযুদ্ধ বিষয়ক মন্ত্রণালয়</span></td></tr>
				<tr><td colspan="4" height="10"></td></tr>
				<tr><td colspan="4" height="30" style="font-family:'.$arialb.';font-size:15;color:#010080;border-top: 1px solid gray;border-bottom: 1px solid gray;border-spacing: 5px 1rem;">Details Report</td></tr>
				</table>
				<table cellspacing="0" cellpadding="1" border="0" width="100%" style="font-family:'.$arialb.';font-size:13;color:#010080;">
				<tr><td width="35%">District : <span style="font-family:'.$nikosh.';font-size:20;color:black;">'.$district.'</span></td><td width="35%">Upazila/Thana : <span style="font-family:'.$nikosh.';font-size:20;color:black;">'.$upazila.'</span></td><td width="20%">'.$str_qty.' : '.$record_count.'</td><td width="10%">'.$str_isalive.'</td></tr>
				<tr><td colspan="4" height="10"></td></tr>
				</table>
				<table cellspacing="0" cellpadding="0" border="0" width="100%" style="font-family:'.$arial.';font-size:13;color:#010080;">
				<tr>
				<td style="width:5.1%;height:40;text-align:center;border-left:1px solid black;border-top:1px solid black;border-right:1px solid black;border-bottom:1px solid black;">SL</td>
				<td style="width:11.6%;text-align:center;border-top:1px solid black;border-bottom:1px solid black;">FF ID</td>
				<td style="width:22.7%;text-align:center;border-left:1px solid black;border-top:1px solid black;border-bottom:1px solid black;">FF Name</td>
				<td style="width:15.8%;text-align:center;border:1px solid black;">Father/Husband</td>
				<td style="width:13%;text-align:center;border-top:1px solid black;border-bottom:1px solid black;">Mother Name</td>
				<td style="width:14.3%;text-align:center;border:1px solid black;">Village/Moholla</td>
				<td style="text-align:center;border-top:1px solid black;border-right:1px solid black;border-bottom:1px solid black;">'.$acknowledge.'</td>
				</tr>
				</table>
				');
				$pdfBig->setHtmlFooter('<table cellspacing="0" cellpadding="1" border="0" width="100%"><tr><td colspan="4">&nbsp;</td></tr><tr><td width="2%"></td><td width="48%"></td><td width="48%" align="right"></td><td width="2%"></td></tr></table>');
				//$pdfBig->SetAutoPageBreak(true, 10); 
				$pdfBig->AddPage();
				//$pdfBig->SetXY(6,45);	
				$xval=6.1; //Table x position		
				$pdfBig->SetFont($arial, '', 9.5);		
				$pdfBig->SetWidths(array(10,23,45,31,26,28,34.7));
				//Table's Titles			
				$pdfBig->SetFont($arialb, '', 9.5);                
				//$pdfBig->Row(array('SL', 'FF ID', 'FF Name', 'Father/Husband', 'Mother Name','Village/Moholla',$acknowledge), array('C','L','L','L','L','C'), array($arialb,$arialb,$arialb,$arialb,$arialb,$arialb,$arialb), array('9.5','9.5','9.5','9.5','9.5','9.5'), $xval, '', 1);  
				$cnt=0;
				$pdfBig->SetTextColor(0, 0, 0);
				foreach ($data_s as $value) {
					$ff_id=$value->ff_id;
					$ff_name=$value->ff_name;
					$father_or_husband_name=$value->father_or_husband_name;
					$mother_name=$value->mother_name;
					$village_or_ward=$value->village_or_ward;
					$upazila_or_thana=$value->upazila_or_thana;
					$cnt+=1;
					$pdfBig->SetWidths(array(10,23,45,31,26,28,34.7));
					srand(microtime()*1000000);						
					$cell_border='LTRB'; 
					//$numlines_pdfBig = $pdfBig->getNumLines($ff_name, 45);			
					//if($numlines_pdfBig>1){ $subject_length='long'; }else{$subject_length='';}
					$subject_length='';
					$pdfBig->SetFont($nikosh, '', 10); 
					$pdfBig->Row(array($cnt, $ff_id, $ff_name, $father_or_husband_name, $mother_name, $village_or_ward, $upazila_or_thana), array('C','L','L','L','L','L','C'), array($arial,$nikosh,$nikosh,$nikosh,$nikosh,$nikosh,$nikosh), array('8','8','12','12','12','12'), $xval, $subject_length, 1);
				}
			}
        }else{
			$pdfBig->setHtmlHeader('<table cellspacing="0" cellpadding="1" border="0" width="100%"><tr><td colspan="4" align="center" style="font-family:'.$arialb.';font-size:18;color:#010080;letter-spacing: 1px;"><b>'.$report_title.'</b></td></tr>
			<tr><td width="15%"><img src="'.$left_logo.'" /></td><td width="40%" style="font-family:'.$arial.';font-size:13;"><span style="font-family:'.$arialb.';font-size:13;color:#ff0300;">SECURA BANGLADESH LIMITED</span><br />Security and Confidential Printers</td><td width="15%"><img src="'.$right_logo.'" /></td><td width="40%" style="font-family:'.$nikosh.';font-size:25;text-align:center;">গণপ্রজাতন্ত্রী বাংলাদেশ সরকার <br><span style="font-family:'.$nikosh.';font-size:30;">মুক্তিযুদ্ধ বিষয়ক মন্ত্রণালয়</span></td></tr>
			<tr><td colspan="4" height="10"></td></tr>
			</table>
			<table cellspacing="0" cellpadding="1" border="0" width="100%" style="font-family:'.$arialb.';font-size:13;color:#010080;">
			<tr><td width="40%" style="border-top: 1px solid gray;border-bottom: 1px solid gray;">Summary Report <span style="font-size:13;color:black;">('.$str_isalive.')</span></td><td width="60%" style="border-top: 1px solid gray;border-bottom: 1px solid gray;">District : <span style="font-family:'.$nikosh.';font-size:20;color:black;">'.$district.'</span></td></tr>
			<tr><td colspan="4" height="20"></td></tr>
			</table>
			');
			$pdfBig->setHtmlFooter('<table cellspacing="0" cellpadding="1" border="0" width="100%"><tr><td colspan="4">&nbsp;</td></tr><tr><td width="2%"></td><td width="48%"></td><td width="48%" align="right"></td><td width="2%"></td></tr></table>');
			//$pdfBig->SetAutoPageBreak(true, 10); 
			$pdfBig->AddPage();
			$subject_length='short';
			//$pdfBig->SetXY(6,45);	
			$xval=20; //Table x position		
			$pdfBig->SetFont($arial, '', 9.5);		
			$pdfBig->SetWidths(array(10,95,62));
			//Table's Titles			
			$pdfBig->SetFont($arialb, '', 9.5);                
			$pdfBig->Row(array('SL', 'Upazila/Thana', $challan_col), array('C', 'L','R'), array($arialb,$arialb), array('9.5','9.5','9.5'), $xval, $subject_length, 1);  
			$cnt=0;
			$pdfBig->SetTextColor(0, 0, 0);
			foreach ($data_s as $value) {
				$upazila_or_thana=$value->upazila_or_thana;
				$upazila_records=$value->upazila_records;
				$record_count+=$upazila_records;
				$cnt+=1;
				$pdfBig->SetWidths(array(10,95,62));
				srand(microtime()*1000000);						
				$cell_border='LTRB';				
				$pdfBig->SetFont($nikosh, '', 10); 
				$pdfBig->Row(array($cnt, $upazila_or_thana, $upazila_records), array('C','L','R'), array($arial,$nikosh,$arial), array('11','14','11'), $xval, $subject_length, 1);
			}
			$pdfBig->SetTextColor(1, 0, 128);
			$pdfBig->Row(array('', 'Total ', $record_count), array('C','R','R'), array($arial,$arial,$arial), array('11','11','11'), $xval, $subject_length, 1);
			$pdfBig->SetTextColor(0, 0, 0);
		}
		$msg = '';        
        $file_name =  str_replace("/", "_",'molwaReport'.date("Ymdhms")).'.pdf';
		$filename = public_path().'\\'.$subdomain[0].'/backend/tcpdf/examples/preview/'.$file_name;        
        $pdfBig->output($filename,'F');
        //@unlink($filename);
        $protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';
        $path = $protocol.'://'.$subdomain[0].'.'.$subdomain[1].'.com/';
        $pdf_url=$path.$subdomain[0]."/backend/tcpdf/examples/preview/".$file_name;
        $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/preview/".$file_name."' class='downloadpdf download' target='_blank'>Here</a> to download file.</b> Record(s): $record_count";
        return $msg;
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

    function sanitizeQrString($content){
         $find = array('â€œ', 'â€™', 'â€¦', 'â€”', 'â€“', 'â€˜', 'Ã©', 'Â', 'â€¢', 'Ëœ', 'â€'); // en dash
         $replace = array('“', '’', '…', '—', '–', '‘', 'é', '', '•', '˜', '”');
        return $content = str_replace($find, $replace, $content);
    }

  
}

class PDF_MC_Table extends \Mpdf\Mpdf
{
    var $widths;
    var $aligns;

    function SetWidths($w)
    {
        //Set the array of column widths
        $this->widths=$w;
    }

    function SetAligns($a)
    {
        //Set the array of column alignments
        $this->aligns=$a;
    }

    function Row($data,$cell_align,$cell_font,$cell_fontsize,$xval,$subject_length='',$cell_border=0)
    { 
        //Calculate the height of the row
        $nb=0;
        for($i=0;$i<count($data);$i++)
            $nb=max($nb,$this->NbLines($this->widths[$i],$data[$i]));
        //$h=5*$nb;
        if($subject_length == 'short'){$h=8;}elseif($subject_length == 'long'){$h=12;}else{$h=12;}
        //Issue a page break first if needed
        $this->CheckPageBreaks($h,$xval);
       
        //Draw the cells of the row
        for($i=0;$i<count($data);$i++)
        {
            $w=$this->widths[$i];
            $a=isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';        
            //Save the current position
            $x=$this->x;
            $y=$this->y;        
            //Draw the border
            if($cell_border == 1){
                $this->Rect($x,$y,$w,$h);
            }
            $this->SetFont($cell_font[$i], '', $cell_fontsize[$i]); 
			//Print the text 
            $txt = mb_convert_encoding($data[$i], $this->mb_enc, 'UTF-8');            
			$this->MultiCell($w,6,$txt,0,$cell_align[$i]);
			//Put the position to the right of the cell
            $this->SetXY($x+$w,$y);
        }
        //Go to the next line
        $this->Ln($h);
    }

    function CheckPageBreaks($h,$xval)
    {
        //If the height h would cause an overflow, add a new page immediately
        if($this->y+$h>$this->PageBreakTrigger)        
            $this->AddPage($this->CurOrientation);
            $this->SetTopMargin(45);
            $y=$this->y;
            $this->SetXY($xval,$y);
    }

    function NbLines($w,$txt)
    { 
        //Computes the number of lines a MultiCell of width w will take
        $cw=&$this->CurrentFont['cw'];
        if($w==0)
            $w=$this->w-$this->rMargin-$this->x;
        //$wmax=($w-2*$this->cMargin)*1000/$this->FontSize;
        $wmax = ($w-2*($this->cMarginL + $this->cMarginR))*1000/$this->FontSize;
        if ($this->usingCoreFont) {
			$s = str_replace("\r", '', $txt);
			$nb = strlen($s);
			while ($nb > 0 and $s[$nb - 1] == "\n") {
				$nb--;
			}
		} else {
			$s = str_replace("\r", '', $txt);
			$nb = mb_strlen($s, $this->mb_enc);
			while ($nb > 0 and mb_substr($s, $nb - 1, 1, $this->mb_enc) == "\n") {
				$nb--;
			}
		}		
		if (!$this->usingCoreFont) {

			while ($i < $nb) {

				// Get next character
				$c = mb_substr($s, $i, 1, $this->mb_enc);
				if ($c === "\n") { // Explicit line break					
					$i++;
					$sep = -1;
					$j = $i;
					$l = 0;
					$ns = 0;
					$nl++;
					continue;
				}
				if ($c == " ") {
					$sep = $i;
					$ls = $l;
					$ns++;
				}
				$l += $this->GetCharWidthNonCore($c);
				if ($l > $wmax) {
					// Automatic line break
					if ($sep == -1) { // Only one word
						if ($i == $j) {
							$i++;
						}
					} else {
						$i = $sep + 1;
					}
					$sep = -1;
					$j = $i;
					$l = 0;
					$ns = 0;
					$nl++;
				} else {
					$i++;
				}
			}

		} else {

			while ($i < $nb) {
				// Get next character
				$c = $s[$i];
				if ($c === "\n") {
					$i++;
					$sep = -1;
					$j = $i;
					$l = 0;
					$ns = 0;
					$nl++;
					continue;
				}
				if ($c === ' ') {
					$sep = $i;
					$ls = $l;
					$ns++;
				}

				$l += $this->GetCharWidthCore($c);
				if ($l > $wmax) {
					// Automatic line break
					if ($sep == -1) {
						if ($i == $j) {
							$i++;
						}

					} else {

						$i = $sep + 1;
					}
					$sep = -1;
					$j = $i;
					$l = 0;
					$ns = 0;
					$nl++;

				} else {
					$i++;
				}
			}

		}
        return $nl;
    }
    var $htmlHeader;
    var $table_header;
    var $htmlFooter;
    var $subdomain;
    var $show_bg;
	
   
    public function setHtmlHeader($header = '', $OE = '', $write = false) {
        $this->htmlHeader = $header;		
		//$this->subdomain = $subdomain;
        //$this->show_bg = $show_bg;
    }
    
    public function Header($content = '') {
        // get the current page break margin
        $bMargin = $this->bMargin;
        // get current auto-page-break mode
        $auto_page_break = $this->autoPageBreak;
        // disable auto-page-break
        $this->SetAutoPageBreak(false, 0);     
        // set background image        
        /*if($this->show_bg=='Yes'){
            $img_file = public_path().'\\'.$this->subdomain.'\backend\canvas\bg_images\\blank.jpg';
            $this->Image($img_file, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
        }*/
        $this->SetFont('Arial','',11);
        $this->SetXY(6,6);
		$this->WriteHTML($this->htmlHeader);
		// restore auto-page-break status
        $this->SetAutoPageBreak($auto_page_break, $bMargin);
        //$this->setPageMark();
    }   
    
    public function _setHtmlFooter($htmlFooter) {
        $this->htmlFooter = $htmlFooter;
    }
    
    public function _Footer()
    {
        // Position at 10mm from bottom
        $this->SetY(-10);
        // Arial italic 8
        $this->SetFont('Arial','B',10);
        $this->writeHTMLCell(
            $w = 0, $h = 0, $x = '', $y = '',
            $this->htmlFooter, $border = 0, $ln = 1, $fill = 0,
            $reseth = true, $align = 'top', $autopadding = true);          
    }
    
}

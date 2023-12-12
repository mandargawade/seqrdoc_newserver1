<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\WebUserRegisterRequest;
use App\models\Site;
use Hash;
use App\Jobs\SendMailJob;
use Session,TCPDF,TCPDF_FONTS,DB;
use App\models\User;
use App\models\SessionManager;
use Illuminate\Support\Facades\Auth;
use App\Utility\ApiSecurityLayer;
use App\models\ApiTracker;
use App\models\sgrsa_supplier;
use App\models\sgrsa_recall_informations;
use App\models\sgrsa_governor;
use App\models\sgrsa_unitserial;
use App\models\StudentTable;
use App\models\Admin;
class RecallController extends Controller
{
    public function AddInfo(Request $request)
    {
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain);   
		//$data = $request->all();
        $data = $request->post();
		//return $request['certificate_no']; exit;
        /*$data = '{
		"supplier_id":"1",
		"admin_id":"1",
		"certificate_no": "H0002", 
		"vehicle_reg_no": "KBC 609A",
		"chassis_no": "L034418",
		"type_of_governor": "HG 2.1",
		"unit_sr_no": "13044624767",
		"date_of_installation": "2022-10-04",
		"date_of_expiry": "2023-10-04"
		}';*/
        $supplier_id=trim($request['supplier_id']);
		$supplier=sgrsa_supplier::where('id',$supplier_id)->first();
		$supplier_name=$supplier->company_name;
		$admin_id=trim($request['admin_id']);
		$certificate_no=trim($request['certificate_no']);
		$vehicle_reg_no=trim($request['vehicle_reg_no']);
		$chassis_no=trim($request['chassis_no']);
		$type_of_governor=trim($request['type_of_governor']);
		$type_name=$type_of_governor;
		/*$gtype=sgrsa_governor::where('id',$type_of_governor)->first();
		$type_name=$gtype->type_name;*/
		$unit_sr_no=trim($request['unit_sr_no']);		
		$doi=trim($request['date_of_installation']);
		$date_of_installation=date('Y-m-d', strtotime($doi));
		$doe=trim($request['date_of_expiry']);
		$date_of_expiry=date('Y-m-d', strtotime($doe));
		$record_type=trim($request['record_type']); //New OR Renew
		$current_date=now();
		
		$input=array();		  
		$input['certificate_no'] = $certificate_no; 
		$input['vehicle_reg_no'] = $vehicle_reg_no; 
		$input['chassis_no'] = $chassis_no; 
		$input['type_of_governor'] = $type_of_governor; 
		$input['unit_sr_no'] = $unit_sr_no; 
		$input['supplier']=$supplier_name;
		$input['supplier_id'] = $supplier_id;
		$input['date_of_installation'] = $doi; 
		$input['date_of_expiry'] = $doe; 
		$input['admin_id']=$admin_id;
		
        if(ApiSecurityLayer::checkAuthorization())
        {
            $rules =[
                'supplier_id'   => 'required|numeric',
                'admin_id'   => 'required|numeric',
                'certificate_no'   => 'required',
                'vehicle_reg_no'   => 'required',
                'chassis_no'   => 'required',
                'type_of_governor'   => 'required',
				'unit_sr_no'   => 'required',
                'date_of_installation'   => 'required',
                'date_of_expiry'   => 'required'
            ];
            $messages = [
                'supplier_id.required'=>'Supplier ID is required.',
                'supplier_id.numeric'=>'Supplier ID should be numeric.',
				'admin_id.required'=>'Admin ID is required.',
                'admin_id.numeric'=>'Admin ID should be numeric.',
				'certificate_no.required'=>'Certificate Number is required.',
				'vehicle_reg_no.required'=>'Registration Number is required.',
				'chassis_no.required'=>'Chassis Number is required.',
				'type_of_governor.required'=>'Type of Governor is required.',
				'unit_sr_no.required'=>'Unit Serial Number is required.',
				'date_of_installation.required'=>'Installation Date is required.',
				'date_of_expiry.required'=>'Expiry Date is required.'
            ];
            $validator = \Validator::make($request->post(),$rules,$messages);
            if ($validator->fails()) {
                $message = array('success' => false,'status'=>400, 'message' => ApiSecurityLayer::getMessage($validator->errors()));
                if ($message['success']==true) {
                    $status = 'success';
                    return $message;
                }
                else
                {
                    $status = 'failed';
                    return $message;
                }                
            }else{
				if(sgrsa_supplier::where(['id' => $supplier_id,'publish' => 1])->count() == 0){ 
					return response()->json(['success'=>false,'status'=>400, 'message'=>'Supplier not found.']); 
					exit();
				}				
				if(sgrsa_recall_informations::where(['vehicle_reg_no' => $vehicle_reg_no,'publish' => 1])->count() > 0){ 
					return response()->json(['success'=>false,'status'=>400, 'message'=>'Vehicle Registration Number already existed.']); 
					exit();
				}				
				/*if(sgrsa_governor::where(['type_name' => $type_name, 'supplier_id' => $supplier_id,'publish' => 1])->count() == 0){ 
					return response()->json(['success'=>false,'status'=>400, 'message'=>'Governor type not existed.']); 
					exit();
				}*/
		
				/***** set fonts *****/
				$arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.TTF', 'TrueTypeUnicode', '', 96);
				$arialb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arialb.TTF', 'TrueTypeUnicode', '', 96);
				$arialNarrow = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\arialn.TTF', 'TrueTypeUnicode', '', 96);
				$arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', 'TrueTypeUnicode', '', 96);
				$times = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman.ttf', 'TrueTypeUnicode', '', 96);
				$timesb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold.ttf', 'TrueTypeUnicode', '', 96);
				$oef = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\old-english-five.ttf', 'TrueTypeUnicode', '', 96);
				$arialMTblack = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIBL0.ttf', 'TrueTypeUnicode', '', 96);	
				
				//$cardDetails=$this->getNextCardNo('SGRSArecall');
				//$card_serial_no=$cardDetails->next_serial_no;		
				$low_res_bg="sgrsa_bg.jpg";
				//qr code info   
				$GUID=preg_replace('/\s+/', '', $vehicle_reg_no);
				$dt = date("_ymdHis");
				$str=$GUID.$dt;
				$encryptedString = strtoupper(md5($str));
				$codeContents = "$vehicle_reg_no\n$supplier_name\n$doe\n\n$encryptedString";
				$qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';	
				$qrCodex = 125; 
				$qrCodey = 16.5;
				$qrCodeWidth =14;
				$qrCodeHeight = 14;
				$ecc = 'L';
				$pixel_Size = 1;
				$frame_Size = 1; 		
				//set background image
				$template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\\'.$low_res_bg;
				//File
				$file_name = str_replace("/", "_",'SGRSA_'.$vehicle_reg_no.'_'.date("Ymdhms")).'.pdf';
				$filename = public_path().'\\'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name; 
				
				$verify_file_name = $GUID.'.pdf';
				$verify_filename_path = public_path().'\\'.$subdomain[0].'/backend/pdf_file/'.$verify_file_name; 
				
				$protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';
				$path = $protocol.'://'.$subdomain[0].'.'.$subdomain[1].'.com';
				$pdfDownloadLink=$path.'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name;	
				$verifyDownloadLink=$path.'/'.$subdomain[0].'/backend/pdf_file/'.$verify_file_name;
				
			/***** Start PDF Generation *****/
				/** Start pdfBig **/
				$pdfBig = new TCPDF('L', 'mm', array('148.08', '104.902'), true, 'UTF-8', false);
				$pdfBig->SetCreator(PDF_CREATOR);
				$pdfBig->SetAuthor('TCPDF');
				$pdfBig->SetTitle('RECALL');
				$pdfBig->SetSubject('');
				/***** remove default header/footer *****/
				$pdfBig->setPrintHeader(false);
				$pdfBig->setPrintFooter(false);
				$pdfBig->SetAutoPageBreak(false, 0);
				$pdfBig->AddPage(); 
				//$pdfBig->Image($template_img_generate, 0, 0, '148.08', '104.902', "JPG", '', 'R', true);				
				//qr code
				\PHPQRCode\QRcode::png($codeContents, $qr_code_path, $ecc, $pixel_Size, $frame_Size);
				$pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false); 
				$pdfBig->setPageMark(); 			
				/******Left Side******/
				$pdfBig->SetFont($arialb, '', 8, '', false); 
				$pdfBig->SetTextColor(0, 0, 0);    
				$pdfBig->SetXY(28, 32);
				$pdfBig->Cell(0, 0, $certificate_no, 0, false, 'L');  
				$pdfBig->SetXY(29, 39);
				$pdfBig->Cell(0, 0, $vehicle_reg_no, 0, false, 'L'); 
				$pdfBig->SetXY(27, 46);
				$pdfBig->Cell(0, 0, $chassis_no, 0, false, 'L');
				$pdfBig->SetXY(33, 52);
				$pdfBig->Cell(0, 0, $type_name, 0, false, 'L');		
				
				$pdfBig->startTransaction();
				$lines = $pdfBig->MultiCell(36, 0, $supplier_name, 0, 'L', 0, 0, '', '', true, 0, false,true, 0); /******get the number of lines******/		
				$pdfBig=$pdfBig->rollbackTransaction(); /******restore previous object******/
				if($lines>1){
					$pdfBig->SetXY(20, 56); //21
				}else{
					$pdfBig->SetXY(20, 58); 
				}		
				$pdfBig->MultiCell(36, 0, $supplier_name, 0, 'L', 0, 0, '', '', true, 0, false,true, 0);
						
				$pdfBig->SetXY(22, 65);
				$pdfBig->Cell(0, 0, $unit_sr_no, 0, false, 'L');
				$pdfBig->SetXY(35, 72.2);
				$pdfBig->Cell(0, 0, $doi, 0, false, 'L');
				$pdfBig->SetXY(25, 78.2);
				$pdfBig->Cell(0, 0, $doe, 0, false, 'L');
				
				/******Right Side******/
				$pdfBig->SetFont($arialb, '', 10, '', false); 
				$pdfBig->SetXY(90, 30);
				$pdfBig->Cell(0, 0, $certificate_no, 0, false, 'L'); 
				$pdfBig->SetXY(90, 36.5);
				$pdfBig->Cell(0, 0, $vehicle_reg_no, 0, false, 'L');
				$pdfBig->SetXY(90, 44);
				$pdfBig->Cell(0, 0, $chassis_no, 0, false, 'L');
				$pdfBig->SetXY(90, 51);
				$pdfBig->Cell(0, 0, $type_name, 0, false, 'L');
				
				$pdfBig->startTransaction();
				$lines2 = $pdfBig->MultiCell(51, 0, $supplier_name, 0, 'L', 0, 0, '', '', true, 0, false,true, 0); /******get the number of lines******/		
				$pdfBig=$pdfBig->rollbackTransaction(); /******restore previous object******/
				if($lines2>1){
					$pdfBig->SetFont($arialb, '', 8, '', false);
					$pdfBig->SetXY(90, 55);
				}else{
					$pdfBig->SetXY(90, 57);
				}
				$pdfBig->MultiCell(51, 0, $supplier_name, 0, 'L', 0, 0, '', '', true, 0, false,true, 0);
				
				$pdfBig->SetFont($arialb, '', 10, '', false);		
				$pdfBig->SetXY(90, 64.3);
				$pdfBig->Cell(0, 0, $unit_sr_no, 0, false, 'L');
				$pdfBig->SetXY(90, 71);
				$pdfBig->Cell(0, 0, $doi, 0, false, 'L');
				$pdfBig->SetXY(90, 78);
				$pdfBig->Cell(0, 0, $doe, 0, false, 'L');		
					   
				$pdfBig->output($filename,'F');	
				/** End pdfBig **/
				
				/** Start pdf **/
				$pdf = new TCPDF('L', 'mm', array('148.08', '104.902'), true, 'UTF-8', false);
				$pdf->SetCreator(PDF_CREATOR);
				$pdf->SetAuthor('TCPDF');
				$pdf->SetTitle('RECALL');
				$pdf->SetSubject('');
				/***** remove default header/footer *****/
				$pdf->setPrintHeader(false);
				$pdf->setPrintFooter(false);
				$pdf->SetAutoPageBreak(false, 0);
				$pdf->AddPage(); 
				$pdf->Image($template_img_generate, 0, 0, '148.08', '104.902', "JPG", '', 'R', true);				
				//qr code
				\PHPQRCode\QRcode::png($codeContents, $qr_code_path, $ecc, $pixel_Size, $frame_Size);
				$pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false); 
				$pdf->setPageMark(); 			
				/******Left Side******/
				$pdf->SetFont($arialb, '', 8, '', false); 
				$pdf->SetTextColor(0, 0, 0);    
				$pdf->SetXY(28, 32);
				$pdf->Cell(0, 0, $certificate_no, 0, false, 'L');  
				$pdf->SetXY(29, 39);
				$pdf->Cell(0, 0, $vehicle_reg_no, 0, false, 'L'); 
				$pdf->SetXY(27, 46);
				$pdf->Cell(0, 0, $chassis_no, 0, false, 'L');
				$pdf->SetXY(33, 52);
				$pdf->Cell(0, 0, $type_name, 0, false, 'L');		
				
				$pdf->startTransaction();
				$lines = $pdf->MultiCell(36, 0, $supplier_name, 0, 'L', 0, 0, '', '', true, 0, false,true, 0); /******get the number of lines******/		
				$pdf=$pdf->rollbackTransaction(); /******restore previous object******/
				if($lines>1){
					$pdf->SetXY(20, 56); //21
				}else{
					$pdf->SetXY(20, 58); 
				}		
				$pdf->MultiCell(36, 0, $supplier_name, 0, 'L', 0, 0, '', '', true, 0, false,true, 0);
						
				$pdf->SetXY(22, 65);
				$pdf->Cell(0, 0, $unit_sr_no, 0, false, 'L');
				$pdf->SetXY(35, 72.2);
				$pdf->Cell(0, 0, $doi, 0, false, 'L');
				$pdf->SetXY(25, 78.2);
				$pdf->Cell(0, 0, $doe, 0, false, 'L');
				
				/******Right Side******/
				$pdf->SetFont($arialb, '', 10, '', false); 
				$pdf->SetXY(90, 30);
				$pdf->Cell(0, 0, $certificate_no, 0, false, 'L'); 
				$pdf->SetXY(90, 36.5);
				$pdf->Cell(0, 0, $vehicle_reg_no, 0, false, 'L');
				$pdf->SetXY(90, 44);
				$pdf->Cell(0, 0, $chassis_no, 0, false, 'L');
				$pdf->SetXY(90, 51);
				$pdf->Cell(0, 0, $type_name, 0, false, 'L');
				
				$pdf->startTransaction();
				$lines2 = $pdf->MultiCell(51, 0, $supplier_name, 0, 'L', 0, 0, '', '', true, 0, false,true, 0); /******get the number of lines******/		
				$pdf=$pdf->rollbackTransaction(); /******restore previous object******/
				if($lines2>1){
					$pdf->SetFont($arialb, '', 8, '', false);
					$pdf->SetXY(90, 55);
				}else{
					$pdf->SetXY(90, 57);
				}
				$pdf->MultiCell(51, 0, $supplier_name, 0, 'L', 0, 0, '', '', true, 0, false,true, 0);
				
				$pdf->SetFont($arialb, '', 10, '', false);		
				$pdf->SetXY(90, 64.3);
				$pdf->Cell(0, 0, $unit_sr_no, 0, false, 'L');
				$pdf->SetXY(90, 71);
				$pdf->Cell(0, 0, $doi, 0, false, 'L');
				$pdf->SetXY(90, 78);
				$pdf->Cell(0, 0, $doe, 0, false, 'L');		
					   
				$pdf->output($verify_filename_path,'F');	
				/** End pdf **/	
			/***** End PDF Generation *****/	
				$input['file_name']=$file_name;
				$input['encryptedString']=$encryptedString;
				$input['created_date']=$current_date;		
				DB::beginTransaction();
				try {
					sgrsa_recall_informations::create($input);
					DB::commit();
					$auth_site_id=278;
					$sts = '1';
					$datetime  = date("Y-m-d H:i:s");
					$ses_id  = $admin_id;	//Sub Agent's admin id
					$urlRelativeFilePath = 'qr/'.$encryptedString.'.png';	
					StudentTable::create(['serial_no'=>$GUID,'certificate_filename'=>$verify_file_name,'template_id'=>0,'key'=>$encryptedString,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'template_type'=>0]);
					return response()->json(['success'=>true, 'status'=>200, 'message'=>'PDF generated', 'pdflink' => $pdfDownloadLink]); 
				} catch (Exception $e) {
					print "Something went wrong.<br />";
					echo 'Exception Message: '. $e->getMessage();
					DB::rollback();
					return response()->json(['success'=>false, 'message'=>$e->getMessage()]);
					exit;
				}
            }
        }
        else
        {
            $message = array('success'=>false, 'status'=>403, 'message' => 'Access forbidden.');
            return $message;
        } 
    }
    


}

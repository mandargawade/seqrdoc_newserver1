<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Utility\ApiSecurityLayerEstamp;
use App\models\SessionManager;
use App\models\TemplateMaster;
use Maatwebsite\Excel\Facades\Excel;
use App\models\SystemConfig;
use App\models\SuperAdmin;
use App\models\Site;
use App\models\CardSerialNumbers;
use Event;
/*use App\models\StudentTable;
use App\models\FieldMaster;
use App\Jobs\PDFGenerateJob;*/
use App\models\BackgroundTemplateMaster;
use TCPDF,Session;
use App\models\FontMaster;
use Storage;
use App\models\Admin;
use Carbon\Carbon;
//use App\Events\PDFGenerateEvent;
use App\models\SeqrdocRequests;
use App\models\ThirdPartyRequests;
use App\Jobs\ValidateExcelBiharEstampCertificateJob;
use App\Jobs\PdfGenerateBiharEstampCertificateJob;
use App\Jobs\PdfGenerateBiharEstampCertificateTestJob;
use App\Jobs\PdfGenerateBiharEstampEcourtTestJob;
use App\Jobs\PdfGenerateBiharEcourtFeeTestJob;
use App\models\DemoERPData;
use DB;

class EStampTestController extends Controller
{
   
    public function generateEstamp(Request $request){


       
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        /*echo $domain;
        exit;*/

        //$hostUrl = \Request::getHttpHost();
        //$subdomain = explode('.', $hostUrl);

        /*$site = Site::select('site_id')->where('site_url',$hostUrl)->first();
        $site_id = $site['site_id'];
        */
        //if (ApiSecurityLayerThirdParty::checkAuthorization()){
       $json = file_get_contents('php://input');
       $request=json_decode($json);
       $requestArr = json_decode(json_encode($request), true);
      // print_r($requestArr);
       // $validateArr = ['Certificate No.', 'Certificate Issue Date', 'Total Amount (Rs.)', 'Party Name'];
       /*if(isset($requestArr['Certificate No.'])&&!empty($requestArr['Certificate No.'])&&
          isset($requestArr['Certificate Issued Date'])&&!empty($requestArr['Certificate Issued Date'])&&
          isset($requestArr['Account Reference'])&&!empty($requestArr['Account Reference'])&&
          isset($requestArr['Unique Doc. Reference'])&&!empty($requestArr['Unique Doc. Reference'])&&
          isset($requestArr['Purchased by'])&&!empty($requestArr['Purchased by'])&&
          isset($requestArr['Description of Document'])&&!empty($requestArr['Description of Document'])&&
          isset($requestArr['Property Description'])&&!empty($requestArr['Property Description'])&&
          isset($requestArr['Consideration Price (Rs.)'])&&!empty($requestArr['Consideration Price (Rs.)'])&&
          isset($requestArr['First Party'])&&!empty($requestArr['First Party'])&&
          isset($requestArr['Second Party'])&&!empty($requestArr['Second Party'])&&
          isset($requestArr['Stamp Duty Paid By'])&&!empty($requestArr['Stamp Duty Paid By'])&&
          isset($requestArr['Stamp Duty Paid (Rs.)'])&&!empty($requestArr['Stamp Duty Paid (Rs.)'])&&
          isset($requestArr['Reg. fee (Rs.)'])&&!empty($requestArr['Reg. fee (Rs.)'])&&
          isset($requestArr['LLR & P Fee (Rs.)'])&&!empty($requestArr['LLR & P Fee (Rs.)'])&&
          isset($requestArr['Miscellaneous Fee (Rs.)'])&&!empty($requestArr['Miscellaneous Fee (Rs.)'])&&
          isset($requestArr['Discore SC (Rs.)'])&&!empty($requestArr['Discore SC (Rs.)'])&&
          isset($requestArr['Total Amount (Rs.)'])&&!empty($requestArr['Total Amount (Rs.)'])&&
          isset($requestArr['Paper Number'])&&!empty($requestArr['Paper Number'])){
*/
        if(isset($requestArr['Certificate No.'])&&!empty($requestArr['Certificate No.'])&&
          isset($requestArr['Certificate Issue Date'])&&!empty($requestArr['Certificate Issue Date'])&&
          isset($requestArr['Purchased by'])&&!empty($requestArr['Purchased by'])&&
          isset($requestArr['Party Name'])&&!empty($requestArr['Party Name'])&&
          isset($requestArr['Total Amount (Rs.)'])&&!empty($requestArr['Total Amount (Rs.)'])&&
          isset($requestArr['Printer'])&&!empty($requestArr['Printer'])
          ){

        $user_id =1;
        $template_id=100;
        $dropdown_template_id = 1;
        $previewPdf=0;
        $excelfile="";
        $loader_token="";
        $site = Site::select('site_id')->where('site_url',$domain)->first();
        $auth_site_id = $site['site_id'];
        
        $admin_id = Admin::where('id',$user_id)->first();
        //print_r($admin_id);
        $loader_token="";
        $rowData1=$requestArr;
        // exit;
        /*$template_name="BiharEstampCert";
        //$result = CardSerialNumbers::select('next_serial_no')->where('template_name',$template_name)->first();
        //print_r($result);
        print_r(config('database.connections'));
        //$result =\DB::select('SELECT * FROM card_serial_numbers WHERE template_name = "'.$template_name.'"');

        $result = \DB::table('card_serial_numbers')
        ->where('template_name', $template_name)
        ->get();
        print_r($result);
         exit;*/
        #Add third party request in table
        $insertRequest = new ThirdPartyRequests();
        $insertRequest->session_manager_id=$user_id;
        $insertRequest->template_id=$template_id;
        /*if($request->inputSource=="Excel"){
        $insertRequest->excel_file=$excelfile;
        }else{
         $insertRequest->excel_file=$jsonfile;   
        }*/
       
        $insertRequest->generation_type='Live';
        $insertRequest->ip_address=$_SERVER['REMOTE_ADDR'];
        $insertRequest->status='In Process';
        $insertRequest->total_documents=1;
        $insertRequest->generated_documents=0;
        $insertRequest->regenerated_documents=0;
        $insertRequest->input_source='JSON';
        $insertRequest->call_back_url="";
        $insertRequest->created_at=date('Y-m-d H:i:s');
        $insertRequest->save();
        
        $insertRequest=$insertRequest->toArray();
        $request_id=$insertRequest['id'];
        $dataArr=array("request_id"=>$request_id,"template_id"=>$template_id,"excel_file"=>"","generation_type"=>'Live',"status"=>"In Process","total_documents"=>1);

            $updated=date('Y-m-d H:i:s');
            $message = array('status' => 200, 'message' => 'Generation started.',"data"=>$dataArr);
            ThirdPartyRequests::where('id',$request_id)->update(["updated_at"=>$updated,"response"=>json_encode($message)]);

        $pdfData=array('studentDataOrg'=>$rowData1,'auth_site_id'=>$auth_site_id,'template_id'=>$template_id,'dropdown_template_id'=>$dropdown_template_id,'previewPdf'=>$previewPdf,'excelfile'=>$excelfile,'loader_token'=>$loader_token, 'subj_col' => '', 'subj_start'=> '', 'subj_end'=> '','generation_from'=>'API','admin_id'=>$admin_id,"request_id"=>$request_id,"call_back_url"=>""); //For Custom Loader
        
        //if($dropdown_template_id==1){
            $link = $this->dispatch(new PdfGenerateBiharEstampCertificateTestJob($pdfData));
        //}
        
        return response()->json(['status'=>200,'message'=>'Certificates generated successfully.','pdfUrl'=>$link]);

      
        
        }else
        {
            return $message = array('status' => 400, 'message' => 'Required parameters not found.');
        }

        /*}else
        {
            return $message = array('status' => 403, 'message' => 'Access forbidden.');
        }*/
    }

    
     public function generateEcourt(Request $request){
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $json = file_get_contents('php://input');
        $request=json_decode($json);
        $requestArr = json_decode(json_encode($request), true);
 
        if(isset($requestArr['e-Court Fee Receipt No.'])&&!empty($requestArr['e-Court Fee Receipt No.'])&&
          isset($requestArr['Date & Time'])&&!empty($requestArr['Date & Time'])&&
          isset($requestArr['Name of the ACC/ Registered User'])&&!empty($requestArr['Name of the ACC/ Registered User'])&&
          isset($requestArr['Name of Applicant'])&&!empty($requestArr['Name of Applicant'])&&
          isset($requestArr['e-Court Fee Amount'])&&!empty($requestArr['e-Court Fee Amount'])&&
          isset($requestArr['Printer'])&&!empty($requestArr['Printer']))
        {

            $user_id =1;
            $template_id=100;
            $dropdown_template_id = 1;
            $previewPdf=0;
            $excelfile="";
            $loader_token="";
            $site = Site::select('site_id')->where('site_url',$domain)->first();
            $auth_site_id = $site['site_id'];
        
            $admin_id = Admin::where('id',$user_id)->first();
     
            $loader_token="";
            $rowData1=$requestArr;
            $insertRequest = new ThirdPartyRequests();
            $insertRequest->session_manager_id=$user_id;
            $insertRequest->template_id=$template_id;
            $insertRequest->generation_type='Live';
            $insertRequest->ip_address=$_SERVER['REMOTE_ADDR'];
            $insertRequest->status='In Process';
            $insertRequest->total_documents=1;
            $insertRequest->generated_documents=0;
            $insertRequest->regenerated_documents=0;
            $insertRequest->input_source='JSON';
            $insertRequest->call_back_url="";
            $insertRequest->created_at=date('Y-m-d H:i:s');
            $insertRequest->save();
        
            $insertRequest=$insertRequest->toArray();
            $request_id=$insertRequest['id'];
            $dataArr=array("request_id"=>$request_id,"template_id"=>$template_id,"excel_file"=>"","generation_type"=>'Live',"status"=>"In Process","total_documents"=>1);

            $updated=date('Y-m-d H:i:s');
            $message = array('status' => 200, 'message' => 'Generation started.',"data"=>$dataArr);
            ThirdPartyRequests::where('id',$request_id)->update(["updated_at"=>$updated,"response"=>json_encode($message)]);

            $pdfData=array('studentDataOrg'=>$rowData1,'auth_site_id'=>$auth_site_id,'template_id'=>$template_id,'dropdown_template_id'=>$dropdown_template_id,'previewPdf'=>$previewPdf,'excelfile'=>$excelfile,'loader_token'=>$loader_token, 'subj_col' => '', 'subj_start'=> '', 'subj_end'=> '','generation_from'=>'API','admin_id'=>$admin_id,"request_id"=>$request_id,"call_back_url"=>""); 
        
            $link = $this->dispatch(new PdfGenerateBiharEstampEcourtTestJob($pdfData));
            return response()->json(['status'=>200,'message'=>'Certificates generated successfully.','pdfUrl'=>$link]);
        
        }
        else
        {
            return $message = array('status' => 400, 'message' => 'Required parameters not found.');
        }
    }

    public function generateCourtFee(){
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $json = file_get_contents('php://input');
        $request=json_decode($json);
        $requestArr = json_decode(json_encode($request), true);
 
        if(isset($requestArr['transaction_id'])&&!empty($requestArr['printer'])&&
          isset($requestArr['stamp_type'])&&!empty($requestArr['data']))
        {

            $user_id =1;
            $template_id=100;
            $dropdown_template_id = 1;
            $previewPdf=0;
            $excelfile="";
            $loader_token="";
            $site = Site::select('site_id')->where('site_url',$domain)->first();
            $auth_site_id = $site['site_id'];
        
            $admin_id = Admin::where('id',$user_id)->first();
     
            $loader_token="";
            $rowData1=$requestArr;
            $insertRequest = new ThirdPartyRequests();
            $insertRequest->session_manager_id=$user_id;
            $insertRequest->template_id=$template_id;
            $insertRequest->generation_type='Live';
            $insertRequest->ip_address=$_SERVER['REMOTE_ADDR'];
            $insertRequest->status='In Process';
            $insertRequest->total_documents=1;
            $insertRequest->generated_documents=0;
            $insertRequest->regenerated_documents=0;
            $insertRequest->input_source='JSON';
            $insertRequest->call_back_url="";
            $insertRequest->created_at=date('Y-m-d H:i:s');
            $insertRequest->save();
        
            $insertRequest=$insertRequest->toArray();
            $request_id=$insertRequest['id'];
            $dataArr=array("request_id"=>$request_id,"template_id"=>$template_id,"excel_file"=>"","generation_type"=>'Live',"status"=>"In Process","total_documents"=>1);

            $updated=date('Y-m-d H:i:s');
            $message = array('status' => 200, 'message' => 'Generation started.',"data"=>$dataArr);
            ThirdPartyRequests::where('id',$request_id)->update(["updated_at"=>$updated,"response"=>json_encode($message)]);

            $pdfData=array('studentDataOrg'=>$rowData1,'auth_site_id'=>$auth_site_id,'template_id'=>$template_id,'dropdown_template_id'=>$dropdown_template_id,'previewPdf'=>$previewPdf,'excelfile'=>$excelfile,'loader_token'=>$loader_token, 'subj_col' => '', 'subj_start'=> '', 'subj_end'=> '','generation_from'=>'API','admin_id'=>$admin_id,"request_id"=>$request_id,"call_back_url"=>""); 
        
            $link = $this->dispatch(new PdfGenerateBiharEcourtFeeTestJob($pdfData));
            return response()->json(['status'=>200,'message'=>'Certificates generated successfully.','pdfUrl'=>$link]);
        
        }
        else
        {
            return $message = array('status' => 400, 'message' => 'Required parameters not found.');
        }
    }
}
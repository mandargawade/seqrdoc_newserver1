<?php

namespace App\Http\Controllers\api;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;


use App\models\StudentTable;
use App\models\SbStudentTable;
//use App\models\Site;

use App\Helpers\CoreHelper;
use DB;
use Storage;
class PdfController extends Controller
{
   public function showPdf(Request $request,$token,$type,$subtype){
        
        $valid=true;
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
         if($subdomain[0]=="demo"){
               // print_r($_SERVER['HTTP_SEC_FETCH_DEST']);
                            if( isset($_SERVER['HTTP_SEC_FETCH_DEST']) && ($_SERVER['HTTP_SEC_FETCH_DEST'] == 'iframe')) {// ||$_SERVER['HTTP_SEC_FETCH_DEST'] == 'document'
                                $valid=true;
                                if ((isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER']))) {
                                    if (strtolower(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST)) != strtolower($_SERVER['HTTP_HOST'])) {
                                    // referer not from the same domain
                                         $valid=false;
                                    }
                                }

                            
                            }else{

                            $aMobileUA = array(
                                  '/msie/i'       =>  'Internet Explorer',
                                        '/firefox/i'    =>  'Firefox',
                                        '/safari/i'     =>  'Safari',
                                        '/chrome/i'     =>  'Chrome',
                                        '/opera/i'      =>  'Opera',
                                        '/netscape/i'   =>  'Netscape',
                                        '/maxthon/i'    =>  'Maxthon',
                                        '/konqueror/i'  =>  'Konqueror',
                                        '/mobile/i'     =>  'Handheld Browser'
                            );


                            //Return true if Mobile User Agent is detected
                            foreach($aMobileUA as $sMobileKey => $sMobileOS){
                                if(preg_match($sMobileKey, $_SERVER['HTTP_USER_AGENT'])){
                                   
                                      $valid=false;
                                        break;
                                }
                            }
                            }
         
           
        }

        if($valid){

        $filename='certificate.pdf';
        
        $notFoundPdf= public_path().'/pdf-not-found.pdf';
        if(!empty($token)&&!empty($type)&&!empty($subtype)&&($type==1||$type==2)&&($subtype==1||$subtype==2||$subtype==3)){
            
            //$site = Site::select('site_id')->where('site_url',$domain)->first();
         /*  print_r($token);
           exit;*/
            //$site_id = $site['site_id'];

            $storageData=CoreHelper::fetchStorageDetails();
            
            //print_r($storageData);
             $site_id=$storageData['site_id'];
            if($storageData['status']&&!empty($storageData['pdf_storage_path'])){
                $storagePath=$storageData['pdf_storage_path'];
               
            }else{
                $storagePath=public_path();
            }
           
            //type : 1 -> Indivisual 2: printable //subtype : 1 Non-Sandbox, 2:Sandbox 3:preview
           /* echo $storagePath;
            exit;*/
            if($type==1){


                $key=$token;

                    if($subtype==1){
                      // print_r($subdomain);
                        /*$studentData = StudentTable::where('key',$key)
                                                ->where('publish',1)
                                                ->where('site_id',$site_id)
                                                ->orderBy('id','DESC')
                                                ->first();*/

                                  
                        //$studentData['certificate_filename']='1001.pdf';      
                       // if($studentData){

                            if($subdomain[0]=="test"){

             /*                     if($subdomain[0]=="test"){
                print_r($storagePath);
                exit;
            }*/
                                //echo "abc11231";
                                  $content = \Storage::disk('s3')->get('public/'.$subdomain[0].'/backend/pdf_file/'.$token.'.pdf');
                                 //exit;
                                 //print_r($content);
                               //exit;
                                return response($content)->header('Content-Type', 'application/pdf');
                            }else{
                                //echo "xyz";
                                 $file = $storagePath.'/'.$subdomain[0].'/backend/pdf_file/'.$token.'.pdf';
                                //exit;
                               
                            }
                       /* }else{
                            $file= $notFoundPdf;
                        }*/
                         
                    
                    }else{
                      /*  $studentData = SbStudentTable::where('key',$key)
                                                ->where('publish',1)
                                                ->where('site_id',$site_id)
                                                ->orderBy('id','DESC')
                                                ->first();
                        if($studentData){*/
                            if($subdomain[0]=="test"){
                                 $content = \Storage::disk('s3')->get('public/'.$subdomain[0].'/backend/pdf_file/sandbox/'.$token.'.pdf');

                                return response($content)->header('Content-Type', 'application/pdf');
                            }else{

                            $file = $storagePath.'/'.$subdomain[0].'/backend/pdf_file/sandbox/'.$token.'.pdf';
                            }
                        /*}else{
                            $file= $notFoundPdf;
                        }*/
                    }
            }else{

                switch ($subtype) {
                    case '1':
                    
                        $file =$storagePath.'/'.$subdomain[0].'/backend/tcpdf/examples/'.$token.'.pdf';
                        break;
                    case '2':
                        $file =$storagePath.'/'.$subdomain[0].'/backend/tcpdf/examples/sandbox/'.$token.'.pdf';
                        break;
                    case '3':
                        $file =$storagePath.'/'.$subdomain[0].'/backend/tcpdf/examples/preview/'.$token.'.pdf';
                         if(!file_exists($file)){  
                            $file =$storagePath.'/'.$subdomain[0].'/backend/tcpdf/examples/'.$token.'.pdf';
                        }
                        break;
                    default:
                        $file= $notFoundPdf;
                        break;
                }

                

            }


            if(!file_exists($file)){  
                            $file= $notFoundPdf;
                }
            
            $path = $file;
            

           
        }else{
           $file= public_path().'/pdf-not-found.pdf';

        }

         return response()->file($file, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
                'Content-Transfer-Encoding'=> 'binary',
                'Accept-Ranges'=>'bytes'
            ]);

     }else{
        http_response_code(404);
        echo "<h1 style='padding-top: 50; font-size:40px;text-align: center;
    color: indianred;
    min-height: 500px;
    background-color: #f0f0f0;
    border: 2px solid #dbdbdb;'>Server Error 404 - Page Not Found!</h1>";
     }

    }

}


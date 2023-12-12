<?php

namespace App\Http\Controllers\api\blockchain;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;


use App\models\StudentTable;
use App\models\SbStudentTable;
use App\models\Site;
use App\models\TemplateMaster;
use App\Helpers\CoreHelper;
use DB;
use Storage;
use App\models\Demo\Site as DemoSite;
use App\Utility\BlockChain;
use Illuminate\Contracts\Encryption\DecryptException;

class PdfController extends Controller
{
    private function isValid64base($str){
    if (base64_decode($str, true) !== false){
        return true;
    } else {
        return false;
    }
    }
   public function showDetails(Request $request,$token){
        
        $valid=true;
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        //echo $token;
        if(!empty($token)){

          
            try {
               // echo "s";
                 //$key= base64_decode($token);
                  $key = decrypt($token);
            } catch (DecryptException $e) {

                $key="";
                if($this->isValid64base($token)){
                 $key = base64_decode($token);
                }
                
                // echo "BB";

                //print_r($e->getMessage());
            }
            // echo $key;
            // exit;
            if(!empty($key)){
            
            $site = Site::select('site_id')->where('site_url',$domain)->first();
          
            $site_id = $site['site_id'];
            $studentData = StudentTable::where('key',$key)
                                                ->where('publish',1)
                                                ->where('status',1)
                                                ->where('site_id',$site_id)
                                                ->orderBy('id','DESC')
                                                ->first();

            if($studentData){

                $siteData = DemoSite::select('bc_wallet_address')->where('site_url',$domain)->first();
                //print_r($siteData);
                if(isset($siteData['bc_wallet_address'])&&!empty($siteData['bc_wallet_address'])){
                    $data['walletID']=$siteData['bc_wallet_address'];
                    $data['uniqueHash']=$key;
                    $data['template_id']=$studentData['template_id'];
                    
                    // print_r($data);
                    // exit;
                    if($studentData['template_id']==698||$studentData['template_id']==718||$studentData['template_id']==719||$subdomain[0]=="mitwpu"){
                    $mode=1;
                    }else{
                    $mode=0;
                    }
                    // print_r($data);
                    // exit;
                    $response=CoreHelper::retreiveDetails($data);
                    
                    if($response['status']==200){

                        //$response['status']=200;
                        $dataR=$response['data'];
                        $dataR['walletID']=$data['walletID'];
                        
                        $dataR['pdfUrl']="https://ipfs.io/ipfs/".substr($dataR['IPFS_URL'], 7);
                        //https://mumbai.polygonscan.com/
                        if($studentData['template_id']==698||$studentData['template_id']==718||$studentData['template_id']==719||$subdomain[0]=="mitwpu"){
                        $dataR['polygonTxnUrl']="https://polygonscan.com/tx/".$studentData['bc_txn_hash'];
                        }else{
                        $dataR['polygonTxnUrl']="https://mumbai.polygonscan.com/tx/".$studentData['bc_txn_hash'];    
                        }

                        if($studentData['template_type']==1){
                           // $checkContract = TemplateMaster::select('bc_contract_address')->where('id',$studentData['template_id'])->first();

                             $checkContract = DB::table("uploaded_pdfs")
                               ->select('bc_contract_address')
                               ->where('id',$studentData['template_id'])
                               ->get();
                               
                            if($checkContract&&!empty($checkContract[0]->bc_contract_address)){
                               
                                $dataR['contractAddress']=$checkContract[0]->bc_contract_address;
                            }else{
                                $dataR['contractAddress']="";  
                            }
                            
                        }else{

                            $checkContract = TemplateMaster::select('bc_contract_address')->where('id',$studentData['template_id'])->first();

                            if($checkContract&&!empty($checkContract['bc_contract_address'])){
                               
                                $dataR['contractAddress']=$checkContract['bc_contract_address'];
                            }else{
                                $dataR['contractAddress']="";  
                            }

                        }
                        
                       
                        $dataR['txnHash']=$studentData['bc_txn_hash'];
                        
                        $data=$dataR;

                        /*print_r($data);
                        exit;*/
                        return view('bverify.index',compact('data'));
                    }else{
                        $response['status']=400;  
                        $response['message']="Details not found.";    
                    }
                }else{
                    $response['status']=400;
                    $response['message']="Wallet address not found.";
                }

            }else{
                $response['status']=400;
                $response['message']="Data not found.";
            }


            }else{
                $response['status']=400;   
                $response['message']="Key not found."; 
            }

        }else{
            $response['status']=400;
            $response['message']="Key not found.";
        }
        
       // $response['message']="Details not found.";
        return $response;
    }

}


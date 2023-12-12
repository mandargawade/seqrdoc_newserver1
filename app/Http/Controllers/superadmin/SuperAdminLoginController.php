<?php

namespace App\Http\Controllers\superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdminRequest;
use Illuminate\Http\Request;
use Auth,DB;
class SuperAdminLoginController extends Controller
{
    public function index()
    {
    	return view('superadmin.auth.login');
    }
    public function SuperLogin(SuperAdminRequest $request)
    {

    	$credential=[
               'username'=>$request['username'],
               'password'=>$request['password'],
    	];



    	if(Auth::guard('superadmin')->attempt($credential))
    	{    
         
            $sitesData=DB::connection($mysql2)->table("sites")->get()->toArray();

        if($sitesData){

            //Super Font Master
            $fontData = DB::connection($mysql2)->table("seqr_demo.super_font_master")->select('id','font_name',DB::raw('if(total_instances= 0,0,0) as total_instances'))->orderBy('id','ASC')->get()->toArray();

            
            foreach ($sitesData as $readData) {
                # code...
                    $instance=explode('.', $readData->site_url);
                 if($instance[0]!='demo'&&$instance[0] != 'master'){
                     $dbName = 'seqr_d_'.$instance[0];
                }else{
                    $dbName = 'seqr_demo';
                }

                $templates_active=DB::connection($mysql2)->table($dbName.".template_master")->where('status','1')->count();
                $templates_in_active=DB::connection($mysql2)->table($dbName.".template_master")->where('status','0')->count();
                
                $documents_active=DB::connection($mysql2)->table($dbName.".student_table")->where('status','1')->where('publish','1')->count();
                $documents_in_active=DB::connection($mysql2)->table($dbName.".student_table")->where('status', '0')->where('publish','1')->count();

                $query="SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = '".$dbName."' AND table_name = 'uploaded_pdfs' LIMIT 1";
                
                $tableExist = DB::select($query);
                if ($tableExist) {

                    $pdf2pdf_active_templates=DB::connection($mysql2)->table($dbName.".uploaded_pdfs")->where('publish','1')->count();
                    $pdf2pdf_inactive_templates=DB::connection($mysql2)->table($dbName.".uploaded_pdfs")->where('publish','0')->count();

                }else{
                    $pdf2pdf_active_templates=0;
                    $pdf2pdf_inactive_templates=0;
                }
               
                $total_verifier=DB::connection($mysql2)->table($dbName.".user_table")->where('publish','1')->count();
                $total_scanned=DB::connection($mysql2)->table($dbName.".scanned_history")->count();


                $generationData = DB::connection($mysql2)->table($dbName.".student_table")->select('created_at')->orderBy('created_at','DESC')->limit(1)->get();

                if($generationData){
                    //print_r($generationData);
                     $lastGenerationDate=$generationData[0]->created_at;
                }else{
                    $lastGenerationDate=null;
                }

                //echo $readData->site_id;
                DB::connection($mysql2)->table("seqr_demo.sites_superdata")->where(['site_id'=>$readData->site_id])->update(['active_documents'=>$documents_active,'inactive_documents'=>$documents_in_active,'template_number'=>$templates_active,'inactive_template_number'=>$templates_in_active,'total_verifier'=>$total_verifier,'total_scanned'=>$total_scanned,'last_genration_date'=>$lastGenerationDate,'pdf2pdf_active_templates'=>$pdf2pdf_active_templates,'pdf2pdf_inactive_templates'=>$pdf2pdf_inactive_templates]);
                //print_r($templates_in_active);

                 $fontMasterData = DB::connection($mysql2)->table($dbName.".font_master")->select(DB::raw('DISTINCT font_name'))->where('publish','1')->get();
                 if($fontMasterData){
                    $checkArray=array();
                    foreach ($fontMasterData as $readData) {
                      
                        if(!in_array($readData->font_name, $checkArray)){
                        
                        $key =$this->searchForId($readData->font_name, $fontData,'font_name');

                        if($key!=-1){
                       
                         $fontData[$key]->total_instances=$fontData[$key]->total_instances+1;

                         array_push($checkArray, $readData->font_name);
                         }

                        }
                    }
                 }

                //exit;
            }


            if($fontData){

                foreach ($fontData as $readData) {
                     DB::connection($mysql2)->table("seqr_demo.super_font_master")->where(['id'=>$readData->id])->update(['total_instances'=>$readData->total_instances]);
                }

            }

        }
             return response()->json(['success'=>true,'msg'=>'Login Successfully']);
    	}
    	else
    	{
    		return response()->json(['success'=>false,'msg'=>'These credentials do not match our records.']);
    	}
    }
    public function dashboard()
    {
       return view('superadmin.dashboard.index');
    }
    public function logout(Request $request){
       
       Auth::guard('superadmin')->logout();
       return redirect()->route('superadmin.index');
    }

    public function searchForId($id, $array,$keyCheck) {
   foreach ($array as $key => $val) {
//    print_r($val);

       if (preg_replace('/\s+/', '',$val->$keyCheck) === preg_replace('/\s+/', '',$id)) {
           return $key;
       }
   }
   return -1;
}
}

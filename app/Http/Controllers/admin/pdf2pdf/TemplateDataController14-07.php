<?php

namespace App\Http\Controllers\admin\pdf2pdf;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\pdf2pdf\TemplateMaster;
use App\models\SystemConfig;
use DB;
use Auth;
class TemplateDataController extends Controller
{   

      public function index(Request $request)
    {
        
        //if we are in index page then make create_refresh field inside template 0
       // TemplateMaster::where('create_refresh',1)->update(['create_refresh'=>0]);

        if($request->ajax()){
            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and (template_name like \"%{$search}%\""
                . ")";
            }

            $status=$request->get('status');

            if($status==1)
            {
                $status=1;
                $where_str.= " and (uploaded_pdfs.publish =$status)";
            }
            else if($status==0)
            {
                $status=0;
                $where_str.=" and (uploaded_pdfs.publish= $status)";
            }                                    
            $auth_site_id=Auth::guard('admin')->user()->site_id;
            //for serial number
            DB::statement(DB::raw('set @rownum=0'));

            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'template_name','pdf_page','id','file_name','publish','publish'];
            
            $categorie_columns_count = TemplateMaster::select($columns)
            ->whereRaw($where_str, $where_params)
             
            ->count();

            $categorie_list = TemplateMaster::select($columns)
            ->whereRaw($where_str, $where_params);

            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $categorie_list = $categorie_list->take($request->input('iDisplayLength'))
                ->skip($request->input('iDisplayStart'));
            }          

            if($request->input('iSortCol_0')){
                $sql_order='';
                for ( $i = 0; $i < $request->input('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->input('iSortCol_' . $i)];
                    if(false !== ($index = strpos($column, ' as '))){
                        $column = substr($column, 0, $index);
                    }
                    $categorie_list = $categorie_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            }
            $categorie_list = $categorie_list->get();

            $response['iTotalDisplayRecords'] = $categorie_columns_count;
            $response['iTotalRecords'] = $categorie_columns_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $categorie_list->toArray();

            return $response;
        }
        return view('admin.pdf2pdf.templateMaster.index');
    }

    public function templateMaker(Request $request)
    {   
   
         $template_data=array("action"=>"add");
        return view('admin.pdf2pdf.addtemplate.index',compact('template_data'));
    }

       public function edit(){
        $id=$_POST['id'];
            $templateData = TemplateMaster::select('template_name','file_name','pdf_page')->where(['id'=>$id])->first();
           $template_data=array("action"=>"edit","id"=>$id,"template_name"=>$templateData->template_name,"file_name"=>$templateData->file_name,"pdf_page"=>$templateData->pdf_page);
        return view('admin.pdf2pdf.addtemplate.index',compact('template_data'));
       }


       public function storeFile(Request $request)
    {
      $pdf_path=$request->get('pdf_path');
      $site_id=$request->get('site_id');
    if(!empty($pdf_path)&&!empty($site_id)){
        //Path of the file stored under pathinfo
        $myFile = pathinfo($pdf_path);
  
        //Show the file name
        $certName=$myFile['basename'];
        
      if(!empty($certName)){
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $siteData=SystemConfig::select('sandboxing','file_aws_local')->where('site_id',$site_id)->first();

        if($siteData){
      if($siteData->file_aws_local == '1') {
            $s3=\Storage::disk('s3');
            $pdf_file_directory = $subdomain[0].'/backend/pdf_file/';
            $sandbox_directory = $subdomain[0].'/backend/pdf_file/sandbox/';

            if(!$s3->exists($pdf_file_directory))
            {
             $s3->makeDirectory($pdf_file_directory, 0777);  
            }
            if(!$s3->exists($sandbox_directory))
            {
             $s3->makeDirectory($sandbox_directory, 0777);  
            }
        }
        else{
            $pdf_file_directory = public_path().'/'.$subdomain[0].'/backend/pdf_file/';
            $sandbox_directory = public_path().'/'.$subdomain[0].'/backend/pdf_file/sandbox/';


            if(!is_dir($pdf_file_directory)){

                mkdir($pdf_file_directory, 0777);
            }

            if(!is_dir($sandbox_directory)){

                mkdir($sandbox_directory, 0777);
            }

        }


        if($siteData->file_aws_local == '1'){

                $aws_qr = \Storage::disk('s3')->put('/'.$subdomain[0].'/backend/pdf_file/'.$certName, file_get_contents($pdf_path), 'public');
                $filename1 = \Storage::disk('s3')->url($certName);
               
        }
        else{

                $aws_qr = \File::copy($pdf_path,public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName);
                
        }
         return  response()->json(['status'=>200,'message'=>'File uploaded successfully.']);
         }else{
        return  response()->json(['status'=>422,'message'=>'Site id not found.']);
    } 
    }else{
        return  response()->json(['status'=>422,'message'=>'Certificate name not found.']);
    } 
    }else{
         return  response()->json(['status'=>422,'message'=>'Pdf path or site id not found.']);
    }
    }
}

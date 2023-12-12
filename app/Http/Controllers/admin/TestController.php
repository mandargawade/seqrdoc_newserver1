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
use App\Imports\TemplateMapImport;
use App\Imports\TemplateMasterImport;
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
//use Illuminate\Support\Facades\Storage;
use App\Helpers\CoreHelper;
use Helper;
class TestController extends Controller
{
    public function index(Request $request)
    {
           return true;
    }

    public function kewiTest(){
       /*$print_data=PrintingDetail::select('i')
                           ->get()
                           ->toArray();*/

         /*$student_data=StudentTable::select(['id','serial_no','certificate_filename','status'])
         ->get()
         ->toArray();*/
         //$dbName="seqr_d_kewi";
         /*$source=\Config::get('constant.directoryPathBackward')."kewi\\backend\\pdf_file\\Inactive_PDF";
         if(!is_dir($source)){
    
                        mkdir($source, 0777);
                    }

                   
         exit;*/
         $dbName="seqr_d_po";
         $student_data=DB::connection($mysql2)->table($dbName.".student_table")->where('template_id','187')->orWhere('template_id','188')->get()
         ->toArray();
         //$student_data=DB::connection($mysql2)->table($dbName.".excelupload_history")->where('template_name','187')->orWhere('template_name','188')->get()
        // ->toArray();
         $i=1;
         //print_r($student_data);
         //exit;
         foreach ($student_data as $readData) {
         // print_r($readData);


           // $source=\Config::get('constant.directoryPathBackward')."po\\backend\\tcpdf\\examples\\".$readData->pdf_file;
           /* $destination=\Config::get('constant.directoryPathBackward')."kewi\\backend\\tcpdf\\examples\\".$readData->pdf_file;
            \File::copy($source,$destination);*/
            // echo '<br>';
          

         /* echo $readData->id.'--------------------'.$readData->certificate_filename.'--------------------'.$readData->status;
          echo '<br>';*/

          if($readData->status==1){
          
           // $source=\Config::get('constant.directoryPathBackward')."po\\backend\\pdf_file\\".$readData->certificate_filename;
           // $destination=\Config::get('constant.directoryPathBackward')."kewi\\backend\\pdf_file\\".$readData->certificate_filename;
            //\File::copy($source,$destination);
          
          }else{
          
         //   $source=\Config::get('constant.directoryPathBackward')."po\\backend\\pdf_file\\Inactive_PDF\\".$readData->id."_".$readData->certificate_filename;
            //$destination=\Config::get('constant.directoryPathBackward')."kewi\\backend\\pdf_file\\Inactive_PDF\\".$i."_".$readData->certificate_filename;
            //\File::copy($source,$destination);
          }

          //@unlink($source);

/*
          kewi\backend\tcpdf\examples*/
          $i++;
          //exit;
         // 
         }
        // 
      exit;
      return true;
    }

}

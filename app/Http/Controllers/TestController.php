<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ApiTracker;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ApiTrakerExport;
use Mail;
use Session;
//use Illuminate\Support\Facades\Mail;
use App\Exports\TemplateMasterExport;
use App\Jobs\SendMailJob;

class TestController extends Controller
{
    
    public function mailcheck()
    {
        $hostUrl = explode('.', $_SERVER['HTTP_HOST']);
        $iname = ucwords($hostUrl[0]);
        $columns = ['id','request_url','client_ip','created','request_method','header_parameters','response_parameters','status'];
        $date = date('Y-m-d') . ' 17:59:00';
        $previousDate = date('Y-m-d', strtotime("-1 days")) . ' 18:00:00';

 

        $apiData = ApiTracker::select($columns)->where("status","failed")->get()->toArray();
       

        if(count($apiData))
        {
            try{
                
                $contents = Excel::raw(new ApiTrakerExport(), \Maatwebsite\Excel\Excel::XLSX);

                Mail::send('mail.apitracker', ['today' => $date, 'previousDate' => $previousDate, 'instance' => $iname], function ($m) use ($contents, $apiData, $iname, $date){
                    $m->from('info@seqrdoc.com', 'SeQR');
                    $m->to('dev12@scube.net.in', 'Mandar')->subject($iname.' SeQR Docs //'.count($apiData).' Failed API Log // '.date('Y-m-d').'.')->cc('deve12@scube.net.in');
                    $m->attachData($contents, $iname.'_SeQR_Failed_API_'.date('Y-m-d').'.xlsx');
                });
                
            }catch(\Exception $e){
               echo 'Message: ' .$e->getMessage();
            }
        }else{
            echo "no record";
        }
    



    }

    public function curl_test(){
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,"https://seqronline.com/iitj/easypaywebapp.php");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,"key=8354A18176EA05B14FB479DE0F73D2FC&device_type=webapp&user_id=1&scan_id=0");

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);

        curl_close ($ch);
        dd($server_output);
    }


    public function testAWS()
    {
        $disk = \Storage::disk('s3');
        $list = $disk->allFiles('bmcc');
        //$list = $disk->allFiles('public/demo/backend/pdf_file/Inactive_PDF');
        $size = 0;
        //foreach ($list as $file) {/
           //  $size+= $disk->size($file);
        //}
        //echo number_format($size / 1048576,2)." MB";
        //echo "<br>";

        // $list = $disk->allFiles('');

        echo "<pre>";
        print_r($list);
        echo "</pre>";

        die();

        // $aws_directory_pdf_file = 'public/demo/backend/pdf_file/inactive_PDF';
        // $size = array_sum(array_map(function($file) {
        //     return (int)$file['size'];
        // }, array_filter($disk->listContents($aws_directory_pdf_file, true /*<- recursive*/), function($file) {
        //     return $file['type'] == 'file';
        // })));
        // echo $size;
        // die();
        /*27/07/2023*/

        dd(\Storage::disk('s3')->allFiles(''));

        $s3=\Storage::disk('s3');    

        $testFile = "public/test/backend/pdf_file/test123.pdf";
        // $test_directory1 = 'test/backend/canvas/images/qr';
        $test_directory1 = 'public/test/backend/pdf_file/testRohit1';
        $test_directory2 = 'public/test/backend/pdf_file/testRohit2';
        if(!$s3->exists($test_directory1)) {
           // $s3->makeDirectory($test_directory1, 0777);  
            echo "not exist";
            if($test_directory1.'/MA010.pdf') {
                 echo "file name is exits";
                if(!$s3->exists($test_directory2)){
                    echo "create directory";
                    $s3->makeDirectory($test_directory2, 0777);
                    $s3 = \Storage::disk('s3')->move($test_directory1.'/MA010.pdf', $test_directory2.'/MA010.pdf');    
                } else {
                    echo "not create directory but file moved";
                    $s3 = \Storage::disk('s3')->move('public/test/backend/pdf_file/MA010.pdf', 'public/test/backend/pdf_file/testRohit1/MA010.pdf');  
                }

            }
        } else {
            echo "exist";
            if($test_directory1.'/MA010.pdf') {

                if(!$s3->exists($test_directory2)){
                    $s3->makeDirectory($test_directory2, 0777);
                    $s3 = \Storage::disk('s3')->move($test_directory1.'/MA010.pdf', $test_directory2.'/MA010.pdf');  
                } else {
                    $s3 = \Storage::disk('s3')->move('public/test/backend/pdf_file/MA010.pdf', 'public/test/backend/pdf_file/testRohit1/MA010.pdf');   
                }
                
            }

        }

        // if(!$s3->exists($
    //))
        // {
        //  $s3->makeDirectory($tcpdf_directory, 0777);  
        // }if(!$s3->exists($examples_directory))
        // {
        //  $s3->makeDirectory($examples_directory, 0777);  
        // }if(!$s3->exists($sandbox_directory))
        // {
        //  $s3->makeDirectory($sandbox_directory, 0777);  
        // }

        //echo 'tesyt';

        //$s3 = \Storage::disk('s3')->copy('public/test/backend/pdf_file/MA010.pdf', 'public/test/backend/pdf_file/testRohit1/MA010.pdf');
        

    }

    
}

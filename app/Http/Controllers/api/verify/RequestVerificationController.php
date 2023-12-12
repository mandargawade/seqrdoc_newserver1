<?php

namespace App\Http\Controllers\api\verify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\User;
use App\models\raisoni\VerificationRequests;
use App\models\raisoni\DocumentRateMaster;
use App\models\raisoni\VerificationDocuments;
use Illuminate\Support\Facades\Hash;
use App\Utility\ApiSecurityLayer;
use DB;

function uploadSingleFile($fieldname, $maxsize, $uploadpath, $extensions = false, $ref_name = false) {
    $upload_field_name = $_FILES[$fieldname]['name'];
    if (empty($upload_field_name) || $upload_field_name == 'NULL') {
        return array('file' => $_FILES[$fieldname]["name"], 'status' => false, 'msg' => 'Please upload a file');
    }
    $file_extension = strtolower(pathinfo($upload_field_name, PATHINFO_EXTENSION));

    if ($extensions !== false && is_array($extensions)) {
        if (!in_array($file_extension, $extensions)) {
            return array('file' => $_FILES[$fieldname]["name"], 'status' => false, 'msg' => 'Please upload valid file ');
        }
    }
    $file_size = @filesize($_FILES[$fieldname]["tmp_name"]);
    if ($file_size > $maxsize) {
        return array('file' => $_FILES[$fieldname]["name"], 'status' => false, 'msg' => 'File Exceeds maximum limit');
    }
    if (isset($upload_field_name)) {
        if ($_FILES[$fieldname]["error"] > 0) {
            return array('file' => $_FILES[$fieldname]["name"], 'status' => false, 'msg' => 'Error: ' . $_FILES[$fieldname]['error']);
        }
    }
    if ($ref_name == false) {
        

        $file_name_without_ext = FileNameWithoutExt($upload_field_name);
        $file_name = time() . '_' . RenameUploadFile($file_name_without_ext) . "." . $file_extension;
    } else {
        $file_name = str_replace(" ", "_", $ref_name) . "." . $file_extension;
    }
    if (!is_dir($uploadpath)) {
        mkdir($uploadpath, 0777, true);
    }
    if (move_uploaded_file($_FILES[$fieldname]["tmp_name"], $uploadpath . $file_name)) {
        return array('file' => $_FILES[$fieldname]["name"], 'status' => true, 'msg' => 'File Uploaded Successfully!', 'filename' => $file_name);
    } else {
        return array('file' => $_FILES[$fieldname]["name"], 'status' => false, 'msg' => 'Sorry unable to upload your file, Please try after some time.');
    }
}

function FileNameWithoutExt($filename) {
    return substr($filename, 0, (strlen($filename)) - (strlen(strrchr($filename, '.'))));
}



function RenameUploadFile($data) {
    $search = array("'", " ", "(", ")", ".", "&", "-", "\"", "\\", "?", ":", "/");
    $replace = array("", "_", "", "", "", "", "", "", "", "", "", "");
    $new_data = str_replace($search, $replace, $data);
    $new_data=preg_replace('/[^a-zA-Z0-9]/', '_', $new_data);
    return strtolower($new_data);
}

function upload_file($file) {
    $target_dir = "../uploads/";
    $target_file = $target_dir . basename($file["image_path"]["name"]);
    $uploadOk = 1;
    $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);

    $upload['message'] = '';
    // Check if image file is a actual image or fake image
    if (isset($_POST)) {
        $check = getimagesize($file["image_path"]["tmp_name"]);
        if ($check !== false) {
           
            $uploadOk = 1;
        } else {
            $upload['message'] .= "File is not an image.";
            $uploadOk = 0;
        }
    }
    // Check if file already exists
    if (file_exists($target_file)) {
        $upload['message'] .= "Sorry, file already exists.";
        $uploadOk = 0;
    }
    // Check file size
    if ($file["image_path"]["size"] > 500000) {
        $upload['message'] .= "Sorry, your file is too large.";
        $uploadOk = 0;
    }
    // Allow certain file formats
    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
        && $imageFileType != "gif") {
        $upload['message'] .= "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        $uploadOk = 0;
    }
    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 0) {
        $upload['message'] .= "Sorry, your file was not uploaded.";
        $upload['status'] = 0;
        $upload['filename'] = '';
        // if everything is ok, try to upload file
    } else {
        $temp = explode(".", $_FILES["image_path"]["name"]);
        $newfilename = round(microtime(true)) . '.' . end($temp);
        if (move_uploaded_file($file["image_path"]["tmp_name"], $target_dir . $newfilename)) {
            $upload['message'] .= "The file " . basename($file["image_path"]["name"]) . " has been uploaded.";
            $upload['status'] = 1;
            $upload['filename'] = $newfilename;
        } else {
            $upload['message'] .= "Sorry, there was an error uploading your file.";
            $upload['status'] = 0;
            $upload['filename'] = '';
        }
    }

    return $upload;
}

function upload_attachment($file) {
    $target_dir = "../uploads/";
    $target_file = $target_dir . basename($file["file"]["name"]);
    $uploadOk = 1;
    $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);

    $upload['message'] = '';
    // Check if file already exists
    if (file_exists($target_file)) {
        $upload['message'] .= "Sorry, file already exists.";
        $uploadOk = 0;
    }
    // Check file size
    if ($file["file"]["size"] > 500000) {
        $upload['message'] .= "Sorry, your file is too large.";
        $uploadOk = 0;
    }
    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 0) {
        $upload['message'] .= "Sorry, your file was not uploaded.";
        $upload['status'] = 0;
        $upload['filename'] = '';
        // if everything is ok, try to upload file
    } else {
        if (move_uploaded_file($file["file"]["tmp_name"], $target_file)) {
            $upload['message'] .= "The file " . basename($file["file"]["name"]) . " has been uploaded.";
            $upload['status'] = 1;
            $upload['filename'] = $file["file"]["name"];
        } else {
            $upload['message'] .= "Sorry, there was an error uploading your file.";
            $upload['status'] = 0;
            $upload['filename'] = '';
        }
    }
    return $upload;
}

function deleteDirectory($dirPath) {
    if (is_dir($dirPath)) {
        $objects = scandir($dirPath);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dirPath . DIRECTORY_SEPARATOR . $object) == "dir") {
                    deleteDirectory($dirPath . DIRECTORY_SEPARATOR . $object);
                } else {
                    unlink($dirPath . DIRECTORY_SEPARATOR . $object);
                }
            }
        }
        reset($objects);
        rmdir($dirPath);
    }
}

class RequestVerificationController extends Controller
{
    //
    public function RequestVerification(Request $request)
    {

        $data = $request->post();
        switch ($request->type) {

            case 'submitRequest':
                if(ApiSecurityLayer::checkAuthorization())
                {
                    $user_id = $request->user_id;
                    $device_type = 'Android';
                    if (isset($data['device_type'])) {
                        $device_type = $data['device_type'];
                    }

                    if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id))
                    {
                        $student_name = (empty($request->student_name)) ? null : $request->student_name;
                        $student_institute = (empty($request->student_institute)) ? null : $request->student_institute;
                        $student_degree = (empty($request->student_degree)) ? null : $request->student_degree;
                        $student_branch = (empty($request->student_branch)) ? null : $request->student_branch;
                        $passout_year = (empty($request->passout_year)) ? null : $request->passout_year;
                        $student_reg_no = (empty($request->student_reg_no)) ? null : $request->student_reg_no;
                        $total_files = (empty($request->total_files)) ? null : $request->total_files;
                        $total_amount = (empty($request->total_amount)) ? null : $request->total_amount;
                        $name_of_recruiter = (empty($request->name_of_recruiter)) ? null : $request->name_of_recruiter;

                        if (!empty($student_name) && !empty($student_institute) && !empty($student_degree) && !empty($student_branch) && !empty($passout_year) && !empty($student_reg_no) && !empty($total_files) && !empty($total_amount)) {
                           
                            if(isset($_FILES['grade_card']['name'][0]) && !empty($_FILES['grade_card']['name'][0])|| isset($_FILES['grade_card']['name'][1]) && !empty($_FILES['grade_card']['name'][1]) || isset($_FILES['grade_card']['name'][2]) && !empty($_FILES['grade_card']['name'][2]) || isset($_FILES['grade_card']['name'][3]) && !empty($_FILES['grade_card']['name'][3]) || isset($_FILES['grade_card']['name'][4]) && !empty($_FILES['grade_card']['name'][4]) || isset($_FILES['provision_degree']['name'][0]) && !empty($_FILES['provision_degree']['name'][0]) || isset($_FILES['provision_degree']['name'][1]) && !empty($_FILES['provision_degree']['name'][1]) || isset($_FILES['original_degree']['name'][0]) && !empty($_FILES['original_degree']['name'][0]) || isset($_FILES['original_degree']['name'][1]) && !empty($_FILES['original_degree']['name'][1]) || isset($_FILES['marksheet']['name'][0]) && !empty($_FILES['marksheet']['name'][0]) || isset($_FILES['marksheet']['name'][1]) && !empty($_FILES['marksheet']['name'][1]) ) {

                                $row = VerificationRequests::select('request_number')->orderBy('id','DESC')->first();

                                if(!empty($row['request_number'])){

                                    $theRest = substr($row['request_number'], 6);
                                    $request_number = "OCV-R-" . ($theRest + 1);
                                }else{

                                    $request_number = "OCV-R-1";
                                }

                                $created_date_time = date('Y-m-d H:i:s');

                                $errorMsg = '';

                                $domain = \Request::getHost();
                                $subdomain = explode('.', $domain);
                                $path = $subdomain[0] . '/backend/uploads/' . $request_number . '/offer_letter/';
                                $pathdb = '/uploads/' . $request_number . '/offer_letter/';

                                if ($_FILES['offer_letter']['name'] && !empty($_FILES['offer_letter']['name'])) {

                                    $uploadPhoto = uploadSingleFile('offer_letter', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));
                                    if ($uploadPhoto['status']) {
                                        $offer_letter = $pathdb . $uploadPhoto['filename'];
                                    } else {
                                        $errorMsg = $uploadPhoto['msg'];
                                    }
                                }
                                
                                if(empty($errorMsg)){

                                    
                                    $verification_requests_save = new VerificationRequests();

                                    $verification_requests_save->request_number = $request_number;
                                    $verification_requests_save->user_id = $user_id;
                                    $verification_requests_save->institute = $student_institute;
                                    $verification_requests_save->degree = $student_degree;
                                    $verification_requests_save->branch = $student_branch;
                                    $verification_requests_save->student_name = $student_name;
                                    $verification_requests_save->registration_no = $student_reg_no;
                                    $verification_requests_save->passout_year = $passout_year;
                                    $verification_requests_save->name_of_recruiter = $name_of_recruiter;
                                    $verification_requests_save->offer_letter = $offer_letter;
                                    $verification_requests_save->no_of_documents = $total_files;
                                    $verification_requests_save->total_amount = $total_amount;
                                    $verification_requests_save->created_date_time = $created_date_time;
                                    $verification_requests_save->device_type = $device_type;
                                    $verification_requests_save->save();

                                    try{

                                        $last_id = $verification_requests_save->id;
                                        if(!empty($last_id)){
                                            
                                            $errorMsg = '';
                                        
                                            try{
                                                $rates = DocumentRateMaster::all();
                                                if($subdomain[0] == "monad"){
                                                    $grade_card_price = 0;
                                                    $provision_degree_price = $rates[0]['amount_per_document'];
                                                    $original_degree_price = 0;
                                                    $marksheet_price = $rates[1]['amount_per_document'];
                                                }else{
                                                    
                                                    $grade_card_price = $rates[0]['amount_per_document'];
                                                    $provision_degree_price = $rates[1]['amount_per_document'];
                                                    $original_degree_price = $rates[2]['amount_per_document'];
                                                    $marksheet_price = $rates[3]['amount_per_document'];
                                                }
                                                
                                            }catch(Exception $e){

                                                $errorMsg = $e->getMessage();

                                            }

                                            $grade_card1 = '';
                                            $grade_card2 = '';
                                            $grade_card3 = '';
                                            $grade_card4 = '';
                                            $grade_card5 = '';
                                            $provision_degree1 = '';
                                            $provision_degree2 = '';
                                            $original_degree1 = '';
                                            $original_degree2 = '';
                                            $marksheet1 = '';
                                            $marksheet2 = '';
                                            $file_count_php = 0;
                                            $total_amount_php = 0;
                                            $domain = \Request::getHost();
                                            $subdomain = explode('.', $domain);
                                            $path = $subdomain[0] . '/backend/uploads/' . $request_number . '/grade_card/';
                                            $pathdb = '/uploads/' . $request_number . '/grade_card/';

                                            if (empty($errorMsg) && isset($_FILES['grade_card']['name'][0]) && !empty($_FILES['grade_card']['name'][0])){

                                                $_FILES['file']['name'] = $_FILES['grade_card']['name'][0];
                                                $_FILES['file']['type'] = $_FILES['grade_card']['type'][0];
                                                $_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][0];
                                                $_FILES['file']['error'] = $_FILES['grade_card']['error'][0];
                                                $_FILES['file']['size'] = $_FILES['grade_card']['size'][0];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $grade_card_price;
                                                    $grade_card1 = $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Grade Card';
                                                        $verification_documents_save->document_path = $grade_card1;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $grade_card_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }


                                            if (empty($errorMsg) && isset($_FILES['grade_card']['name'][1]) && !empty($_FILES['grade_card']['name'][1])){

                                                $_FILES['file']['name'] = $_FILES['grade_card']['name'][1];
                                                $_FILES['file']['type'] = $_FILES['grade_card']['type'][1];
                                                $_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][1];
                                                $_FILES['file']['error'] = $_FILES['grade_card']['error'][1];
                                                $_FILES['file']['size'] = $_FILES['grade_card']['size'][1];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $grade_card_price;
                                                    $grade_card2 = $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Grade Card';
                                                        $verification_documents_save->document_path = $grade_card2;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $grade_card_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }

                                            if (empty($errorMsg) && isset($_FILES['grade_card']['name'][2]) && !empty($_FILES['grade_card']['name'][2])){

                                                $_FILES['file']['name'] = $_FILES['grade_card']['name'][2];
                                                $_FILES['file']['type'] = $_FILES['grade_card']['type'][2];
                                                $_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][2];
                                                $_FILES['file']['error'] = $_FILES['grade_card']['error'][2];
                                                $_FILES['file']['size'] = $_FILES['grade_card']['size'][2];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $grade_card_price;
                                                    $grade_card3 = $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Grade Card';
                                                        $verification_documents_save->document_path = $grade_card3;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $grade_card_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }

                                            if (empty($errorMsg) && isset($_FILES['grade_card']['name'][3]) && !empty($_FILES['grade_card']['name'][3])){

                                                $_FILES['file']['name'] = $_FILES['grade_card']['name'][3];
                                                $_FILES['file']['type'] = $_FILES['grade_card']['type'][3];
                                                $_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][3];
                                                $_FILES['file']['error'] = $_FILES['grade_card']['error'][3];
                                                $_FILES['file']['size'] = $_FILES['grade_card']['size'][3];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $grade_card_price;
                                                    $grade_card4 = $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Grade Card';
                                                        $verification_documents_save->document_path = $grade_card4;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $grade_card_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }

                                            if (empty($errorMsg) && isset($_FILES['grade_card']['name'][4]) && !empty($_FILES['grade_card']['name'][4])){

                                                $_FILES['file']['name'] = $_FILES['grade_card']['name'][4];
                                                $_FILES['file']['type'] = $_FILES['grade_card']['type'][4];
                                                $_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][4];
                                                $_FILES['file']['error'] = $_FILES['grade_card']['error'][4];
                                                $_FILES['file']['size'] = $_FILES['grade_card']['size'][4];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $grade_card_price;
                                                    $grade_card5 = $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Grade Card';
                                                        $verification_documents_save->document_path = $grade_card5;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $grade_card_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }

                                            $path =$subdomain[0] .  '/backend/uploads/' . $request_number . '/provision_degree/';
                                            $pathdb = '/uploads/' . $request_number . '/provision_degree/';

                                            if (empty($errorMsg) && isset($_FILES['provision_degree']['name'][0]) && !empty($_FILES['provision_degree']['name'][0])){

                                                $_FILES['file']['name'] = $_FILES['provision_degree']['name'][0];
                                                $_FILES['file']['type'] = $_FILES['provision_degree']['type'][0];
                                                $_FILES['file']['tmp_name'] = $_FILES['provision_degree']['tmp_name'][0];
                                                $_FILES['file']['error'] = $_FILES['provision_degree']['error'][0];
                                                $_FILES['file']['size'] = $_FILES['provision_degree']['size'][0];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $provision_degree_price;
                                                    $provision_degree1= $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        if($subdomain[0]=="monad"||$subdomain[0]=="demo"){
                                                        $verification_documents_save->document_type = "Degree";
                                                        }else{
                                                        $verification_documents_save->document_type = 'Provisional Degree';
                                                        }
                                                        $verification_documents_save->document_path = $provision_degree1;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $provision_degree_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }

                                            if (empty($errorMsg) && isset($_FILES['provision_degree']['name'][1]) && !empty($_FILES['provision_degree']['name'][1])){

                                                $_FILES['file']['name'] = $_FILES['provision_degree']['name'][1];
                                                $_FILES['file']['type'] = $_FILES['provision_degree']['type'][1];
                                                $_FILES['file']['tmp_name'] = $_FILES['provision_degree']['tmp_name'][1];
                                                $_FILES['file']['error'] = $_FILES['provision_degree']['error'][1];
                                                $_FILES['file']['size'] = $_FILES['provision_degree']['size'][1];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $provision_degree_price;
                                                    $provision_degree2= $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        //$verification_documents_save->document_type = 'Provisional Degree';
                                                        if($subdomain[0]=="monad"||$subdomain[0]=="demo"){
                                                        $verification_documents->document_type = "Degree";
                                                        }else{
                                                        $verification_documents->document_type = "Provisional Degree";  
                                                        }
                                                        $verification_documents_save->document_path = $provision_degree2;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $provision_degree_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }


                                            $path = $subdomain[0] . '/backend/uploads/' . $request_number . '/original_degree/';
                                            $pathdb = '/uploads/' . $request_number . '/original_degree/';

                                            if (empty($errorMsg) && isset($_FILES['original_degree']['name'][0]) && !empty($_FILES['original_degree']['name'][0])){

                                                $_FILES['file']['name'] = $_FILES['original_degree']['name'][0];
                                                $_FILES['file']['type'] = $_FILES['original_degree']['type'][0];
                                                $_FILES['file']['tmp_name'] = $_FILES['original_degree']['tmp_name'][0];
                                                $_FILES['file']['error'] = $_FILES['original_degree']['error'][0];
                                                $_FILES['file']['size'] = $_FILES['original_degree']['size'][0];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $original_degree_price;
                                                    $original_degree1= $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Leaving Certificate';
                                                        $verification_documents_save->document_path = $original_degree1;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $original_degree_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }

                                            if (empty($errorMsg) && isset($_FILES['original_degree']['name'][1]) && !empty($_FILES['original_degree']['name'][1])){

                                                $_FILES['file']['name'] = $_FILES['original_degree']['name'][1];
                                                $_FILES['file']['type'] = $_FILES['original_degree']['type'][1];
                                                $_FILES['file']['tmp_name'] = $_FILES['original_degree']['tmp_name'][1];
                                                $_FILES['file']['error'] = $_FILES['original_degree']['error'][1];
                                                $_FILES['file']['size'] = $_FILES['original_degree']['size'][1];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $original_degree_price;
                                                    $original_degree2= $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Leaving Certificate';
                                                        $verification_documents_save->document_path = $original_degree2;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $original_degree_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }

                                            $path = $subdomain[0] . '/backend/uploads/' . $request_number . '/marksheet/';
                                            $pathdb = '/uploads/' . $request_number . '/marksheet/';


                                            if (empty($errorMsg) && isset($_FILES['marksheet']['name'][0]) && !empty($_FILES['marksheet']['name'][0])){

                                                $_FILES['file']['name'] = $_FILES['marksheet']['name'][0];
                                                $_FILES['file']['type'] = $_FILES['marksheet']['type'][0];
                                                $_FILES['file']['tmp_name'] = $_FILES['marksheet']['tmp_name'][0];
                                                $_FILES['file']['error'] = $_FILES['marksheet']['error'][0];
                                                $_FILES['file']['size'] = $_FILES['marksheet']['size'][0];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $marksheet_price;
                                                    $marksheet1= $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Marksheet';
                                                        $verification_documents_save->document_path = $marksheet1;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $marksheet_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }

                                            if (empty($errorMsg) && isset($_FILES['marksheet']['name'][1]) && !empty($_FILES['marksheet']['name'][1])){

                                                $_FILES['file']['name'] = $_FILES['marksheet']['name'][1];
                                                $_FILES['file']['type'] = $_FILES['marksheet']['type'][1];
                                                $_FILES['file']['tmp_name'] = $_FILES['marksheet']['tmp_name'][1];
                                                $_FILES['file']['error'] = $_FILES['marksheet']['error'][1];
                                                $_FILES['file']['size'] = $_FILES['marksheet']['size'][1];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $marksheet_price;
                                                    $marksheet2= $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Marksheet';
                                                        $verification_documents_save->document_path = $marksheet2;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $marksheet_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }
                                            
                                            if(empty($errorMsg) && $total_amount_php == $total_amount && $total_files == $file_count_php){


                                                $message = array('status' => 200, 'message' => 'success', "request_number" => $request_number, "total_amount" => $total_amount_php, "firstname" => $student_name,'url'=>'https://'.$_SERVER['HTTP_HOST'].'/verify/payment/paytm');
                                            }else{


                                                $dirPath = $subdomain[0] . '/uploads/' . $request_number;
                                                //deleteDirectory($dirPath);
                                                
                                                $message = array('status' => 500, 'message' => 'Some error occured. Please try again.1');
                                            }
                                        }else{

                                            $message = array('status' => 500, 'message' => 'Some error occured. Please try again.');
                                        }
                                    }catch(Exception $e){
                                        $message = array('status' => 500, 'message' => $e->getMessage());
                                    }
                                }else{
                                    $dirPath = $subdomain[0].'/uploads/' . $request_number;
                                    //deleteDirectory($dirPath);
                                    $message = array('status' => 400, 'message' => $errorMsg);
                                }
                            }else{

                                $message = array('status' => 400, 'message' => 'Please upload atleast one document.');
                            }         
                        }else{

                            $message = array('status' => 400, 'message' => 'Required parameters not found.');
                        }
                    }else{

                        $message = array('status' => 403, 'message' => 'User id is missing or You dont have access to this api.');
                    }
                }else{
                    $message = array('status' => 403, 'message' => 'Access forbidden.');
                }
                break;

            case 'getRequestsList':
                if(ApiSecurityLayer::checkAuthorization())
                {
                    $domain = \Request::getHost();
                    $subdomain = explode('.', $domain);
                    $user_id = $request->user_id;
                    if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id)) {
                        /*SELECT vr.*, DATE_FORMAT(vr.created_date_time, '%d %b %Y %h:%i %p') as created_date_time,DATE_FORMAT(vr.updated_date_time, '%d %b %Y %h:%i %p') as updated_date_time,dm.degree_name,bm.branch_name_long,ut.fullname,ut.l_name,tr.trans_id_ref as payment_transaction_id,tr.trans_id_gateway as payment_gateway_id, tr.payment_mode,tr.amount,DATE_FORMAT(tr.transaction_date, '%d %b %Y %h:%i %p') as payment_date_time,a.username FROM verification_requests as vr
                                        INNER JOIN user_table as ut ON vr.user_id=ut.id
                                        INNER JOIN degree_master as dm ON vr.degree=dm.id
                                        INNER JOIN branch_master as bm ON vr.branch=bm.id
                                        LEFT JOIN transactions as tr ON vr.request_number=tr.student_key AND tr.trans_status=1
                                        LEFT JOIN admin_table as a ON vr.updated_by=a.id
                                     WHERE vr.payment_status='Paid' AND  vr.user_id='" . $user_id . "' ORDER BY vr.created_date_time DESC*/
                         $verification_type = $request->verification_type;
                            if($subdomain[0]=="monad"||$subdomain[0]=="demo"){

                               
                         /*   $sql = DB::select(DB::raw("SELECT vr.*, DATE_FORMAT(vr.created_date_time, '%d %b %Y %h:%i %p') as created_date_time,DATE_FORMAT(vr.updated_date_time, '%d %b %Y %h:%i %p') as updated_date_time,dm.degree_name,bm.branch_name_long,ut.fullname,ut.l_name,tr.trans_id_ref as payment_transaction_id,tr.trans_id_gateway as payment_gateway_id, tr.payment_mode,tr.amount,DATE_FORMAT(tr.created_at, '%d %b %Y %h:%i %p') as payment_date_time,a.username FROM verification_requests as vr
                                                INNER JOIN verification_documents as vd ON vd.request_id=vr.id AND vd.document_type='".$verification_type."'
                                                INNER JOIN user_table as ut ON vr.user_id=ut.id
                                                INNER JOIN degree_master as dm ON vr.degree=dm.id
                                                INNER JOIN branch_master as bm ON vr.branch=bm.id
                                                LEFT JOIN transactions as tr ON vr.request_number=tr.student_key AND tr.trans_status=1
                                                LEFT JOIN admin_table as a ON vr.updated_by=a.id
                                             WHERE vr.payment_status='Paid' AND  vr.user_id='" . $user_id . "' GROUP BY vr.id ORDER BY vr.created_date_time DESC"));*/
                                $sql = DB::select(DB::raw("SELECT vr.*, DATE_FORMAT(vr.created_date_time, '%d %b %Y %h:%i %p') as created_date_time,DATE_FORMAT(vr.updated_date_time, '%d %b %Y %h:%i %p') as updated_date_time,dm.degree_name,bm.branch_name_long,ut.fullname,ut.l_name,tr.trans_id_ref as payment_transaction_id,tr.trans_id_gateway as payment_gateway_id, tr.payment_mode,tr.amount,DATE_FORMAT(tr.created_at, '%d %b %Y %h:%i %p') as payment_date_time,a.username FROM verification_requests as vr
                                        INNER JOIN verification_documents as vd ON vd.request_id=vr.id AND vd.document_type='".$verification_type."'
                                        INNER JOIN user_table as ut ON vr.user_id=ut.id
                                        INNER JOIN degree_master as dm ON vr.degree=dm.id
                                        INNER JOIN branch_master as bm ON vr.branch=bm.id
                                        LEFT JOIN transactions as tr ON vr.request_number=tr.student_key AND tr.trans_status=1
                                        LEFT JOIN admin_table as a ON vr.updated_by=a.id
                                     WHERE vr.payment_status='Paid' AND  vr.user_id='" . $user_id . "' ORDER BY vr.created_date_time DESC"));
                            }else{
                        $sql = DB::select(DB::raw("SELECT vr.*, DATE_FORMAT(vr.created_date_time, '%d %b %Y %h:%i %p') as created_date_time,DATE_FORMAT(vr.updated_date_time, '%d %b %Y %h:%i %p') as updated_date_time,dm.degree_name,bm.branch_name_long,ut.fullname,ut.l_name,tr.trans_id_ref as payment_transaction_id,tr.trans_id_gateway as payment_gateway_id, tr.payment_mode,tr.amount,DATE_FORMAT(tr.created_at, '%d %b %Y %h:%i %p') as payment_date_time,a.username FROM verification_requests as vr
                                        INNER JOIN user_table as ut ON vr.user_id=ut.id
                                        INNER JOIN degree_master as dm ON vr.degree=dm.id
                                        INNER JOIN branch_master as bm ON vr.branch=bm.id
                                        LEFT JOIN transactions as tr ON vr.request_number=tr.student_key AND tr.trans_status=1
                                        LEFT JOIN admin_table as a ON vr.updated_by=a.id
                                     WHERE vr.payment_status='Paid' AND  vr.user_id='" . $user_id . "' GROUP BY vr.id ORDER BY vr.created_date_time DESC"));
                        }
                        $result = json_decode(json_encode($sql),true);
                        $message = array('status' => 200,'success' =>true,'message' => 'success', "data" => $result);
                    } else {
                        $message = array('status' => 403, 'message' => 'User id is missing or You dont have access to this api.');
                    }
                } else {
                    $message = array('status' => 403, 'message' => 'Access forbidden.');
                }
                break;

            case 'getRequestDetails':
                if(ApiSecurityLayer::checkAuthorization())
                {
                    $domain = \Request::getHost();
                    $subdomain = explode('.', $domain);
                    $user_id = $request->user_id;
                    $request_id = $request->request_id;
                    if (!empty($user_id) /*&& ApiSecurityLayer::checkAccessToken($user_id)*/) {
                        if (!empty($request_id)) {

                            $sql = DB::select(DB::raw("SELECT vr.*,DATE_FORMAT(vr.created_date_time, '%d %b %Y %h:%i %p') as created_date_time,DATE_FORMAT(vr.updated_date_time, '%d %b %Y %h:%i %p') as updated_date_time,dm.degree_name,bm.branch_name_long,ut.fullname,ut.l_name,tr.trans_id_ref as payment_transaction_id,tr.trans_id_gateway as payment_gateway_id, tr.payment_mode,tr.amount,DATE_FORMAT(tr.created_at, '%d %b %Y %h:%i %p') as payment_date_time,a.username FROM verification_requests as vr
                                        INNER JOIN user_table as ut ON vr.user_id=ut.id
                                        INNER JOIN degree_master as dm ON vr.degree=dm.id
                                        INNER JOIN branch_master as bm ON vr.branch=bm.id
                                        LEFT JOIN transactions as tr ON vr.request_number=tr.student_key AND tr.trans_status=1
                                        LEFT JOIN admin_table as a ON vr.updated_by=a.id
                                     WHERE vr.payment_status='Paid' AND  vr.user_id='" . $user_id . "' AND  vr.id='" . $request_id . "' ORDER BY vr.created_date_time DESC"));
                            $verification_request = json_decode(json_encode($sql),true);
                            
                            $verification_request[0]['offer_letter'] = \Config::get('constant.local_base_path').$subdomain[0].'/backend/'.$verification_request[0]['offer_letter'];

                            $sql1 = DB::select(DB::raw("SELECT vd.*,em.session_name as exam_name_display,sm.semester_name as semester_name_display,rt.transaction_ref_id,rt.request_id as refund_request_id,rt.transaction_id_payu,rt.status,rt.created_date_time as refund_date_time,rt.updated_date_time as refund_updated_date_time FROM verification_documents as vd
                                                  LEFT JOIN exam_master as em ON vd.exam_name = em.session_no
                                                  LEFT JOIN semester_master as sm ON vd.semester = sm.id
                                                   LEFT JOIN refund_transactions as rt ON vd.refund_id = rt.id AND rt.status_code='1' AND vd.is_refunded='1'
                                                  WHERE vd.request_id='" . $request_id . "'"));
                            $verification_documents = json_decode(json_encode($sql1),true);

                            $verification_documents[0]['document_path'] = \Config::get('constant.local_base_path').$subdomain[0].'/backend/'.$verification_documents[0]['document_path'];

                            $message = array('status' => 200, 'message' => 'success', "data" => $verification_documents, "otherData" => $verification_request);
                        } else {
                            $message = array('status' => 422, 'message' => 'Required fields not found.');
                        } 

                    }else {
                        $message = array('status' => 403, 'message' => 'User id is missing or You dont have access to this api.');

                    }
                } else {
                    $message = array('status' => 403, 'message' => 'Access forbidden.');
                }
                break;
            default:
                $message = array('status' => 404, 'message' => 'Request not found.');
                break;
        }

        echo json_encode($message);

        $requestUrl = \Request::Url();
        $requestMethod = \Request::method();
        $requestParameter = $data;

        if ($message['status']==200) {
            
            $status = 'success';
        }
        else
        {
            $status = 'failed';
        }

        ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);

        
    }

    public function RequestVerification_galgotias(Request $request){
        //dd('test');
        //dd($_FILES);
        $data = $request->post();
        switch ($request->type) {
            case 'submitRequest':
                if(ApiSecurityLayer::checkAuthorization()){
                    $user_id = $request->user_id;
                    $device_type = 'Android';
                    if (isset($data['device_type'])) {
                        $device_type = $data['device_type'];
                    }

                    if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id)){
                        $student_name = (empty($request->student_name)) ? null : $request->student_name;
                        $student_institute = (empty($request->student_institute)) ? null : $request->student_institute;
                        $student_degree = (empty($request->student_degree)) ? null : $request->student_degree;
                        $student_branch = (empty($request->student_branch)) ? null : $request->student_branch;
                        $passout_year = (empty($request->passout_year)) ? null : $request->passout_year;
                        $student_reg_no = (empty($request->student_reg_no)) ? null : $request->student_reg_no;
                        $total_files = (empty($request->total_files)) ? null : $request->total_files;
                        $total_amount = (empty($request->total_amount)) ? null : $request->total_amount;
                        $name_of_recruiter = (empty($request->name_of_recruiter)) ? null : $request->name_of_recruiter;

                        if (!empty($student_name) && !empty($student_institute) && !empty($student_degree) && !empty($student_branch) && !empty($passout_year) && !empty($student_reg_no) && !empty($total_files) && !empty($total_amount)) {

                            //dd('entered1');

                            if(isset($_FILES['grade_card']['name'][0]) && !empty($_FILES['grade_card']['name'][0]) || 
                                isset($_FILES['grade_card']['name'][1]) && !empty($_FILES['grade_card']['name'][1]) || 
                                isset($_FILES['grade_card']['name'][2]) && !empty($_FILES['grade_card']['name'][2]) || 
                                isset($_FILES['grade_card']['name'][3]) && !empty($_FILES['grade_card']['name'][3]) ||
                                isset($_FILES['grade_card']['name'][4]) && !empty($_FILES['grade_card']['name'][4]) ||
                                isset($_FILES['grade_card']['name'][5]) && !empty($_FILES['grade_card']['name'][5]) ||
                                isset($_FILES['grade_card']['name'][6]) && !empty($_FILES['grade_card']['name'][6]) ||
                                isset($_FILES['grade_card']['name'][7]) && !empty($_FILES['grade_card']['name'][7]) ||
                                isset($_FILES['grade_card']['name'][8]) && !empty($_FILES['grade_card']['name'][8]) ||
                                isset($_FILES['grade_card']['name'][9]) && !empty($_FILES['grade_card']['name'][9]) ||
                                isset($_FILES['provision_degree']['name'][0]) && !empty($_FILES['provision_degree']['name'][0]) ||isset($_FILES['original_degree']['name'][0]) && !empty($_FILES['original_degree']['name'][0])){
                                //dd('entered2');
                                $row = VerificationRequests::select('request_number')->orderBy('id','DESC')->first();

                                if(!empty($row['request_number'])){

                                    $theRest = substr($row['request_number'], 6);
                                    $request_number = "OCV-R-" . ($theRest + 1);
                                }else{

                                    $request_number = "OCV-R-1";
                                }

                                $created_date_time = date('Y-m-d H:i:s');

                                $errorMsg = '';

                                $domain = \Request::getHost();
                                $subdomain = explode('.', $domain);
                                $path = $subdomain[0] . '/backend/uploads/' . $request_number . '/offer_letter/';
                                $pathdb = '/uploads/' . $request_number . '/offer_letter/';

                                //dd('entered3');

                                if ($_FILES['offer_letter']['name'] && !empty($_FILES['offer_letter']['name'])) {

                                    $uploadPhoto = uploadSingleFile('offer_letter', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));
                                    if ($uploadPhoto['status']) {
                                        $offer_letter = $pathdb . $uploadPhoto['filename'];
                                    } else {
                                        $errorMsg = $uploadPhoto['msg'];
                                    }
                                }

                                //dd('entered4');
                                
                                if(empty($errorMsg)){

                                    
                                    $verification_requests_save = new VerificationRequests();

                                    $verification_requests_save->request_number = $request_number;
                                    $verification_requests_save->user_id = $user_id;
                                    $verification_requests_save->institute = $student_institute;
                                    $verification_requests_save->degree = $student_degree;
                                    $verification_requests_save->branch = $student_branch;
                                    $verification_requests_save->student_name = $student_name;
                                    $verification_requests_save->registration_no = $student_reg_no;
                                    $verification_requests_save->passout_year = $passout_year;
                                    $verification_requests_save->name_of_recruiter = $name_of_recruiter;
                                    $verification_requests_save->offer_letter = $offer_letter;
                                    $verification_requests_save->no_of_documents = $total_files;
                                    $verification_requests_save->total_amount = $total_amount;
                                    $verification_requests_save->created_date_time = $created_date_time;
                                    $verification_requests_save->device_type = $device_type;
                                    $verification_requests_save->save();

                                    try{

                                        $last_id = $verification_requests_save->id;
                                        if(!empty($last_id)){
                                            
                                            $errorMsg = '';
                                        
                                            try{

                                                $rates = DocumentRateMaster::all();
                                                $grade_card_price = $rates[0]['amount_per_document'];
                                                $provision_degree_price = $rates[1]['amount_per_document'];
                                                $original_degree_price = $rates[2]['amount_per_document'];
                                               
                                            }catch(Exception $e){

                                                $errorMsg = $e->getMessage();

                                            }

                                            $grade_card1 = '';
                                            $grade_card2 = '';
                                            $grade_card3 = '';
                                            $grade_card4 = '';
                                            $grade_card5 = '';
                                            $grade_card6 = '';
                                            $grade_card7 = '';
                                            $grade_card8 = '';
                                            $grade_card9 = '';
                                            $grade_card10 = '';
                                            $provision_degree1 = '';
                                            $original_degree1 = '';
                                            $file_count_php = 0;
                                            $total_amount_php = 0;
                                            $domain = \Request::getHost();
                                            $subdomain = explode('.', $domain);
                                            $path = $subdomain[0] . '/backend/uploads/' . $request_number . '/grade_card/';
                                            $pathdb = '/uploads/' . $request_number . '/grade_card/';

                                            //dd('entered');

                                            if (empty($errorMsg) && isset($_FILES['grade_card']['name'][0]) && !empty($_FILES['grade_card']['name'][0])){

                                                $_FILES['file']['name'] = $_FILES['grade_card']['name'][0];
                                                $_FILES['file']['type'] = $_FILES['grade_card']['type'][0];
                                                $_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][0];
                                                $_FILES['file']['error'] = $_FILES['grade_card']['error'][0];
                                                $_FILES['file']['size'] = $_FILES['grade_card']['size'][0];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $grade_card_price;
                                                    $grade_card1 = $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Grade Card';
                                                        $verification_documents_save->document_path = $grade_card1;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $grade_card_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }

                                            if (empty($errorMsg) && isset($_FILES['grade_card']['name'][1]) && !empty($_FILES['grade_card']['name'][1])){

                                                $_FILES['file']['name'] = $_FILES['grade_card']['name'][1];
                                                $_FILES['file']['type'] = $_FILES['grade_card']['type'][1];
                                                $_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][1];
                                                $_FILES['file']['error'] = $_FILES['grade_card']['error'][1];
                                                $_FILES['file']['size'] = $_FILES['grade_card']['size'][1];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $grade_card_price;
                                                    $grade_card2 = $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Grade Card';
                                                        $verification_documents_save->document_path = $grade_card2;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $grade_card_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }

                                            if (empty($errorMsg) && isset($_FILES['grade_card']['name'][2]) && !empty($_FILES['grade_card']['name'][2])){

                                                $_FILES['file']['name'] = $_FILES['grade_card']['name'][2];
                                                $_FILES['file']['type'] = $_FILES['grade_card']['type'][2];
                                                $_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][2];
                                                $_FILES['file']['error'] = $_FILES['grade_card']['error'][2];
                                                $_FILES['file']['size'] = $_FILES['grade_card']['size'][2];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $grade_card_price;
                                                    $grade_card3 = $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Grade Card';
                                                        $verification_documents_save->document_path = $grade_card3;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $grade_card_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }

                                            if (empty($errorMsg) && isset($_FILES['grade_card']['name'][3]) && !empty($_FILES['grade_card']['name'][3])){

                                                $_FILES['file']['name'] = $_FILES['grade_card']['name'][3];
                                                $_FILES['file']['type'] = $_FILES['grade_card']['type'][3];
                                                $_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][3];
                                                $_FILES['file']['error'] = $_FILES['grade_card']['error'][3];
                                                $_FILES['file']['size'] = $_FILES['grade_card']['size'][3];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $grade_card_price;
                                                    $grade_card4 = $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Grade Card';
                                                        $verification_documents_save->document_path = $grade_card4;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $grade_card_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }

                                            if (empty($errorMsg) && isset($_FILES['grade_card']['name'][4]) && !empty($_FILES['grade_card']['name'][4])){

                                                $_FILES['file']['name'] = $_FILES['grade_card']['name'][4];
                                                $_FILES['file']['type'] = $_FILES['grade_card']['type'][4];
                                                $_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][4];
                                                $_FILES['file']['error'] = $_FILES['grade_card']['error'][4];
                                                $_FILES['file']['size'] = $_FILES['grade_card']['size'][4];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $grade_card_price;
                                                    $grade_card5 = $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Grade Card';
                                                        $verification_documents_save->document_path = $grade_card5;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $grade_card_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }

                                            if (empty($errorMsg) && isset($_FILES['grade_card']['name'][5]) && !empty($_FILES['grade_card']['name'][5])){

                                                $_FILES['file']['name'] = $_FILES['grade_card']['name'][5];
                                                $_FILES['file']['type'] = $_FILES['grade_card']['type'][5];
                                                $_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][5];
                                                $_FILES['file']['error'] = $_FILES['grade_card']['error'][5];
                                                $_FILES['file']['size'] = $_FILES['grade_card']['size'][5];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $grade_card_price;
                                                    $grade_card6 = $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Grade Card';
                                                        $verification_documents_save->document_path = $grade_card6;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $grade_card_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }

                                            if (empty($errorMsg) && isset($_FILES['grade_card']['name'][6]) && !empty($_FILES['grade_card']['name'][6])){

                                                $_FILES['file']['name'] = $_FILES['grade_card']['name'][6];
                                                $_FILES['file']['type'] = $_FILES['grade_card']['type'][6];
                                                $_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][6];
                                                $_FILES['file']['error'] = $_FILES['grade_card']['error'][6];
                                                $_FILES['file']['size'] = $_FILES['grade_card']['size'][6];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $grade_card_price;
                                                    $grade_card7 = $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Grade Card';
                                                        $verification_documents_save->document_path = $grade_card7;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $grade_card_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }

                                            if (empty($errorMsg) && isset($_FILES['grade_card']['name'][7]) && !empty($_FILES['grade_card']['name'][7])){

                                                $_FILES['file']['name'] = $_FILES['grade_card']['name'][7];
                                                $_FILES['file']['type'] = $_FILES['grade_card']['type'][7];
                                                $_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][7];
                                                $_FILES['file']['error'] = $_FILES['grade_card']['error'][7];
                                                $_FILES['file']['size'] = $_FILES['grade_card']['size'][7];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $grade_card_price;
                                                    $grade_card8 = $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Grade Card';
                                                        $verification_documents_save->document_path = $grade_card8;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $grade_card_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }

                                            if (empty($errorMsg) && isset($_FILES['grade_card']['name'][8]) && !empty($_FILES['grade_card']['name'][8])){

                                                $_FILES['file']['name'] = $_FILES['grade_card']['name'][8];
                                                $_FILES['file']['type'] = $_FILES['grade_card']['type'][8];
                                                $_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][8];
                                                $_FILES['file']['error'] = $_FILES['grade_card']['error'][8];
                                                $_FILES['file']['size'] = $_FILES['grade_card']['size'][8];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $grade_card_price;
                                                    $grade_card9 = $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Grade Card';
                                                        $verification_documents_save->document_path = $grade_card9;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $grade_card_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }

                                            if (empty($errorMsg) && isset($_FILES['grade_card']['name'][9]) && !empty($_FILES['grade_card']['name'][9])){

                                                $_FILES['file']['name'] = $_FILES['grade_card']['name'][9];
                                                $_FILES['file']['type'] = $_FILES['grade_card']['type'][9];
                                                $_FILES['file']['tmp_name'] = $_FILES['grade_card']['tmp_name'][9];
                                                $_FILES['file']['error'] = $_FILES['grade_card']['error'][9];
                                                $_FILES['file']['size'] = $_FILES['grade_card']['size'][9];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $grade_card_price;
                                                    $grade_card10 = $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Grade Card';
                                                        $verification_documents_save->document_path = $grade_card10;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $grade_card_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }


                                            $path =$subdomain[0] .  '/backend/uploads/' . $request_number . '/provision_degree/';
                                            $pathdb = '/uploads/' . $request_number . '/provision_degree/';

                                            if (empty($errorMsg) && isset($_FILES['provision_degree']['name'][0]) && !empty($_FILES['provision_degree']['name'][0])){

                                                $_FILES['file']['name'] = $_FILES['provision_degree']['name'][0];
                                                $_FILES['file']['type'] = $_FILES['provision_degree']['type'][0];
                                                $_FILES['file']['tmp_name'] = $_FILES['provision_degree']['tmp_name'][0];
                                                $_FILES['file']['error'] = $_FILES['provision_degree']['error'][0];
                                                $_FILES['file']['size'] = $_FILES['provision_degree']['size'][0];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $provision_degree_price;
                                                    $provision_degree1= $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        if($subdomain[0]=="monad"||$subdomain[0]=="demo"){
                                                        $verification_documents->document_type = "Degree";
                                                        }else{
                                                        $verification_documents_save->document_type = 'Provisional Degree';
                                                        }
                                                        $verification_documents_save->document_path = $provision_degree1;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $provision_degree_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }


                                            $path = $subdomain[0] . '/backend/uploads/' . $request_number . '/original_degree/';
                                            $pathdb = '/uploads/' . $request_number . '/original_degree/';

                                            if (empty($errorMsg) && isset($_FILES['original_degree']['name'][0]) && !empty($_FILES['original_degree']['name'][0])){

                                                $_FILES['file']['name'] = $_FILES['original_degree']['name'][0];
                                                $_FILES['file']['type'] = $_FILES['original_degree']['type'][0];
                                                $_FILES['file']['tmp_name'] = $_FILES['original_degree']['tmp_name'][0];
                                                $_FILES['file']['error'] = $_FILES['original_degree']['error'][0];
                                                $_FILES['file']['size'] = $_FILES['original_degree']['size'][0];

                                                $uploadPhoto = uploadSingleFile('file', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));

                                                if($uploadPhoto['status']){

                                                    $file_count_php++;
                                                    $total_amount_php = $total_amount_php + $original_degree_price;
                                                    $original_degree1= $pathdb . $uploadPhoto['filename'];

                                                    try{

                                                        $verification_documents_save = new VerificationDocuments();
                                                        $verification_documents_save->request_id = $last_id;
                                                        $verification_documents_save->document_type = 'Leaving Certificate';
                                                        $verification_documents_save->document_path = $original_degree1;
                                                        $verification_documents_save->result_found_status = 'Pending';
                                                        $verification_documents_save->document_price = $original_degree_price;
                                                        $verification_documents_save->created_date_time = $created_date_time;
                                                        $verification_documents_save->save();
                                                        

                                                    }catch(Exception $e){

                                                        $errorMsg = $e->getMessage();
                                                    }
                                                }else{

                                                    $errorMsg = $uploadPhoto['msg'];
                                                }
                                            }

                                            
                                            if(empty($errorMsg) && $total_amount_php == $total_amount && $total_files == $file_count_php){

                                                $message = array('status' => 200, 'message' => 'success', "request_number" => $request_number, "total_amount" => $total_amount_php, "firstname" => $student_name,'url'=>'https://'.$_SERVER['HTTP_HOST'].'/verify/payment/paytm');
                                            }else{


                                                $dirPath = $subdomain[0] . '/uploads/' . $request_number;
                                                //deleteDirectory($dirPath);
                                                
                                                $message = array('status' => 500, 'message' => 'Some error occured. Please try again.');
                                            }
                                        }else{

                                            $message = array('status' => 500, 'message' => 'Some error occured. Please try again.');
                                        }
                                    }catch(Exception $e){
                                        $message = array('status' => 500, 'message' => $e->getMessage());
                                    }
                                }else{
                                    $dirPath = $subdomain[0].'/uploads/' . $request_number;
                                    //deleteDirectory($dirPath);
                                    $message = array('status' => 400, 'message' => $errorMsg);
                                }

                            }else{
                                $message = array('status' => 400, 'message' => 'Please upload atleast one document.');
                            }

                        }else{
                            $message = array('status' => 400, 'message' => 'Required parameters not found.');
                        }
                    }else{
                        $message = array('status' => 403, 'message' => 'User id is missing or You dont have access to this api.');
                    }
                }else{
                    $message = array('status' => 403, 'message' => 'Access forbidden.');
                }
                break;

                case 'getRequestsList':
                    if(ApiSecurityLayer::checkAuthorization())
                    {   
                        
                        $user_id = $request->user_id;
                        if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id)) {

                           
                            $sql = DB::select(DB::raw("SELECT vr.*, DATE_FORMAT(vr.created_date_time, '%d %b %Y %h:%i %p') as created_date_time,DATE_FORMAT(vr.updated_date_time, '%d %b %Y %h:%i %p') as updated_date_time,dm.degree_name,bm.branch_name_long,ut.fullname,ut.l_name,tr.trans_id_ref as payment_transaction_id,tr.trans_id_gateway as payment_gateway_id, tr.payment_mode,tr.amount,DATE_FORMAT(tr.created_at, '%d %b %Y %h:%i %p') as payment_date_time,a.username FROM verification_requests as vr
                                            INNER JOIN user_table as ut ON vr.user_id=ut.id
                                            INNER JOIN degree_master as dm ON vr.degree=dm.id
                                            INNER JOIN branch_master as bm ON vr.branch=bm.id
                                            LEFT JOIN transactions as tr ON vr.request_number=tr.student_key AND tr.trans_status=1
                                            LEFT JOIN admin_table as a ON vr.updated_by=a.id
                                         WHERE vr.payment_status='Paid' AND  vr.user_id='" . $user_id . "' ORDER BY vr.created_date_time DESC"));
                            
                            $result = json_decode(json_encode($sql),true);
                            $message = array('status' => 200,'success' =>true,'message' => 'success', "data" => $result);
                        } else {
                            $message = array('status' => 403, 'message' => 'User id is missing or You dont have access to this api.');
                        }
                    } else {
                        $message = array('status' => 403, 'message' => 'Access forbidden.');
                    }
                    break;

            case 'getRequestDetails':
                if(ApiSecurityLayer::checkAuthorization())
                {
                    $domain = \Request::getHost();
                    $subdomain = explode('.', $domain);
                    $user_id = $request->user_id;
                    $request_id = $request->request_id;
                    if (!empty($user_id) /*&& ApiSecurityLayer::checkAccessToken($user_id)*/) {
                        if (!empty($request_id)) {

                            
                            
                            $sql = DB::select(DB::raw("SELECT vr.*,DATE_FORMAT(vr.created_date_time, '%d %b %Y %h:%i %p') as created_date_time,DATE_FORMAT(vr.updated_date_time, '%d %b %Y %h:%i %p') as updated_date_time,dm.degree_name,bm.branch_name_long,ut.fullname,ut.l_name,tr.trans_id_ref as payment_transaction_id,tr.trans_id_gateway as payment_gateway_id, tr.payment_mode,tr.amount,DATE_FORMAT(tr.created_at, '%d %b %Y %h:%i %p') as payment_date_time,a.username FROM verification_requests as vr
                                        INNER JOIN user_table as ut ON vr.user_id=ut.id
                                        INNER JOIN degree_master as dm ON vr.degree=dm.id
                                        INNER JOIN branch_master as bm ON vr.branch=bm.id
                                        LEFT JOIN transactions as tr ON vr.request_number=tr.student_key AND tr.trans_status=1
                                        LEFT JOIN admin_table as a ON vr.updated_by=a.id
                                     WHERE vr.payment_status='Paid' AND  vr.user_id='" . $user_id . "' AND  vr.id='" . $request_id . "' ORDER BY vr.created_date_time DESC"));
                            $verification_request = json_decode(json_encode($sql),true);
                            
                            $verification_request[0]['offer_letter'] = \Config::get('constant.local_base_path').$subdomain[0].'/backend/'.$verification_request[0]['offer_letter'];

                            $sql1 = DB::select(DB::raw("SELECT vd.*,em.session_name as exam_name_display,sm.semester_name as semester_name_display,rt.transaction_ref_id,rt.request_id as refund_request_id,rt.transaction_id_payu,rt.status,rt.created_date_time as refund_date_time,rt.updated_date_time as refund_updated_date_time FROM verification_documents as vd
                                                  LEFT JOIN exam_master as em ON vd.exam_name = em.session_no
                                                  LEFT JOIN semester_master as sm ON vd.semester = sm.id
                                                   LEFT JOIN refund_transactions as rt ON vd.refund_id = rt.id AND rt.status_code='1' AND vd.is_refunded='1'
                                                  WHERE vd.request_id='" . $request_id . "'"));
                            $verification_documents = json_decode(json_encode($sql1),true);

                            $verification_documents[0]['document_path'] = \Config::get('constant.local_base_path').$subdomain[0].'/backend/'.$verification_documents[0]['document_path'];

                            

                            $message = array('status' => 200, 'message' => 'success', "data" => $verification_documents, "otherData" => $verification_request);
                        } else {
                            $message = array('status' => 422, 'message' => 'Required fields not found.');
                        } 

                    }else {
                        $message = array('status' => 403, 'message' => 'User id is missing or You dont have access to this api.');

                    }
                } else {
                    $message = array('status' => 403, 'message' => 'Access forbidden.');
                }
                break;
            default:
                $message = array('status' => 404, 'message' => 'Request not found.');
                break;
        }

    echo json_encode($message);

    $requestUrl = \Request::Url();
    $requestMethod = \Request::method();
    $requestParameter = $data;

    if ($message['status']==200) {
        
        $status = 'success';
    }
    else
    {
        $status = 'failed';
    }

    ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);    
}


}

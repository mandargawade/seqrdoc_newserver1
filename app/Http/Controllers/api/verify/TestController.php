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



class TestController extends Controller
{
    //
    public function uploadTest(Request $request)
    {

        //print_r($_POST);
       // print_r($_FILES);

        $data = $request->post();
        

        $errorMsg = '';

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $path = $subdomain[0] . '/backend/uploads/testfolder/';
        $pathdb = '/uploads/testfolder/';
        $uploadPhoto="Not Found";
        if (isset($_FILES['document']['name']) && !empty($_FILES['document']['name'])) {
            //$uploadPhoto="Found";
            $uploadPhoto = uploadSingleFile('document', '50000000', $path, array('jpg', 'JPG', 'png', 'PNG', 'bmp', 'BMP', 'jpeg', 'JPEG', 'pdf', 'PDF'));
            
            if ($uploadPhoto['status']) {
                $offer_letter = $pathdb . $uploadPhoto['filename'];
            } else {
                $errorMsg = $uploadPhoto['msg'];
            }
        }
          
        echo json_encode(["sucess"=>200,"message"=>"Testing","data"=>$data,"files"=>$_FILES['document']])  ;                   
    }   


}

<?php

//Author :: Aakashi Modi
//Date : 15-11-2019
namespace App\Http\Controllers\admin;

use App\Events\DynamicImageEvent;
use App\Http\Controllers\Controller;
use App\models\ImageDeleteHistory;
use App\models\TemplateMaster;
use App\models\FieldMaster;
use Config,File;
use Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Auth;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use App\models\SystemConfig;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
class DynamicImageManagementController extends Controller
{
	//displaying template folder left hand side
    public function index(CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){

    	$domain = \Request::getHost();
        $subdomain = explode('.', $domain);

    	$get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
    	//get data from template master for displaying folder name
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

    	$get_template_data = TemplateMaster::select('template_name','id','actual_template_name')->where('status',1)->orderBy('template_name','asc')->where('site_id',$auth_site_id)->get()->toArray();
    	$template_data = [];
    	foreach ($get_template_data as $template_key => $template_value) {
    		//store template name in variable
    		$template_data[$template_key]['template_name'] = $template_value['actual_template_name'];
    		$template_data[$template_key]['template_id'] = $template_value['id'];
    		
	    		if($get_file_aws_local_flag->file_aws_local == '1'){
	    			$all_files = Storage::disk('s3')->allFiles($subdomain[0].'/'.\Config::get('constant.template').'/'.$template_value['id']);
	    			$files = [];
	    			$ext_array = ['jpg','png','JPG','PNG','JPEG','jpeg','gif'];
	    			foreach ($all_files as $all_fileskey => $all_filesvalue) {
	    				$explode_file = explode('.',$all_filesvalue);
	    				if(in_array($explode_file[1], $ext_array)){
	    					$files[] = $all_filesvalue;
	    				}
	    			}
	    			$get_template_folder_image_count = count($files);
	    			
	    		}
	    		else{
	    			$get_template_folder_image_count = count(glob(public_path().'/'.$subdomain[0].'/backend/templates/'.$template_value['id']."/{*.jpg,*.png,*.JPG,*.PNG,*.JPEG,*.jpeg}", GLOB_BRACE));

	    		}
	    	
    		//store count in variable
    		$template_data[$template_key]['count'] = $get_template_folder_image_count;
    	}
    	
    	//UASB Changes
    	if($subdomain[0]=="uasb"){
    	if(!isset($template_key)){$template_key=-1;}	
    	$get_template_folder_image_count = count(glob(public_path()."/".$subdomain[0]."/backend/DC_Photo/*.{jpg,png,JPG,PNG,JPEG,jpeg,gif}", GLOB_BRACE));
    	$template_data[$template_key+1]['template_name']='DC_Photo';
    	$template_data[$template_key+1]['template_id']='DC_Photo';
    	$template_data[$template_key+1]['count']=$get_template_folder_image_count;
    	}


    	//KESSC Changes
    	if($subdomain[0]=="kessc"){
    		if(!isset($template_key)){$template_key=-1;}
    	$get_template_folder_image_count = count(glob(public_path()."/".$subdomain[0]."/backend/templates/100/*.{jpg,png,JPG,PNG,JPEG,jpeg,gif}", GLOB_BRACE));
    	$template_data[$template_key+1]['template_name']='KESSC-GC';
    	$template_data[$template_key+1]['template_id']=100;
    	$template_data[$template_key+1]['count']=$get_template_folder_image_count;
    	}

    	//WOXSEN
    	if($subdomain[0]=="woxsen"){
    		if(!isset($template_key)){$template_key=-1;}
    	$get_template_folder_image_count = count(glob(public_path()."/".$subdomain[0]."/backend/templates/100/*.{jpg,png,JPG,PNG,JPEG,jpeg,gif}", GLOB_BRACE));
    	$template_data[$template_key+1]['template_name']='Woxsen Custom';
    	$template_data[$template_key+1]['template_id']=100;
    	$template_data[$template_key+1]['count']=$get_template_folder_image_count;
    	}

    	//MONAD Changes
    	if($subdomain[0]=="monad"){
    		if(!isset($template_key)){$template_key=-1;}
    	$get_template_folder_image_count = count(glob(public_path()."/".$subdomain[0]."/backend/templates/100/*.{jpg,png,JPG,PNG,JPEG,jpeg,gif}", GLOB_BRACE));
    	$template_data[$template_key+1]['template_name']='MONAD';
    	$template_data[$template_key+1]['template_id']=100;
    	$template_data[$template_key+1]['count']=$get_template_folder_image_count;
    	}
        
        //icat Changes
    	if($subdomain[0]=="icat" || $subdomain[0]=="demo" || $subdomain[0]=="ghribmjal"){
    		if(!isset($template_key)){$template_key=-1;}
    	$get_template_folder_image_count = count(glob(public_path()."/".$subdomain[0]."/backend/templates/pdf2pdf_images/*.{jpg,png,JPG,PNG,JPEG,jpeg,gif}", GLOB_BRACE));
    	$template_data[$template_key+1]['template_name']='pdf2pdf_images';
    	$template_data[$template_key+1]['template_id']='pdf2pdf_images';
    	$template_data[$template_key+1]['count']=$get_template_folder_image_count;
    	}
        
		//VBIT Changes
    	if($subdomain[0]=="vbit"){
    		if(!isset($template_key)){$template_key=-1;}
    	$get_template_folder_image_count = count(glob(public_path()."/".$subdomain[0]."/backend/templates/100/*.{jpg,png,JPG,PNG,JPEG,jpeg,gif}", GLOB_BRACE));
    	$template_data[$template_key+1]['template_name']='VBIT_CUSTOM';
    	$template_data[$template_key+1]['template_id']=100;
    	$template_data[$template_key+1]['count']=$get_template_folder_image_count;
    	}

    	//ISCN Changes
    	if($subdomain[0]=="iscnagpur"){
    	if(!isset($template_key)){$template_key=-1;}
    	$get_template_folder_image_count = count(glob(public_path()."/".$subdomain[0]."/backend/templates/100/*.{jpg,png,JPG,PNG,JPEG,jpeg,gif}", GLOB_BRACE));
    	$template_data[$template_key+1]['template_name']='ISCN_CUSTOM';
    	$template_data[$template_key+1]['template_id']=100;
    	$template_data[$template_key+1]['count']=$get_template_folder_image_count;
    	}
		
		//BESTIU Changes
    	if($subdomain[0]=="bestiu"){
    		if(!isset($template_key)){$template_key=-1;}
			$get_template_folder_image_count = count(glob(public_path()."/".$subdomain[0]."/backend/templates/100/*.{jpg,png,JPG,PNG,JPEG,jpeg,gif}", GLOB_BRACE));
			$template_data[$template_key+1]['template_name']='BESTIU_CUSTOM';
			$template_data[$template_key+1]['template_id']=100;
			$template_data[$template_key+1]['count']=$get_template_folder_image_count;
    	}
		
		//SDM Changes
    	if($subdomain[0]=="sdm"){
    		if(!isset($template_key)){$template_key=-1;}
			$get_template_folder_image_count = count(glob(public_path()."/".$subdomain[0]."/backend/templates/100/*.{jpg,png,JPG,PNG,JPEG,jpeg,gif}", GLOB_BRACE));
			$template_data[$template_key+1]['template_name']='SDM_CUSTOM';
			$template_data[$template_key+1]['template_id']=100;
			$template_data[$template_key+1]['count']=$get_template_folder_image_count;
    	}
		
		//ANU Changes
    	if($subdomain[0]=="anu"){
    		if(!isset($template_key)){$template_key=-1;}
			$get_template_folder_image_count = count(glob(public_path()."/".$subdomain[0]."/backend/templates/100/*.{jpg,png,JPG,PNG,JPEG,jpeg,gif}", GLOB_BRACE));
			$template_data[$template_key+1]['template_name']='SDM_CUSTOM';
			$template_data[$template_key+1]['template_id']=100;
			$template_data[$template_key+1]['count']=$get_template_folder_image_count;
    	}
		//Surana Changes
    	if($subdomain[0]=="surana"){
    		if(!isset($template_key)){$template_key=-1;}
			$get_template_folder_image_count = count(glob(public_path()."/".$subdomain[0]."/backend/templates/100/*.{jpg,png,JPG,PNG,JPEG,jpeg,gif}", GLOB_BRACE));
			$template_data[$template_key+1]['template_name']='SURANA_CUSTOM';
			$template_data[$template_key+1]['template_id']=100;
			$template_data[$template_key+1]['count']=$get_template_folder_image_count;
    	}
		//Sangamuni Changes
    	if($subdomain[0]=="sangamuni"){
    		if(!isset($template_key)){$template_key=-1;}
			$get_template_folder_image_count = count(glob(public_path()."/".$subdomain[0]."/backend/templates/100/*.{jpg,png,JPG,PNG,JPEG,jpeg,gif}", GLOB_BRACE));
			$template_data[$template_key+1]['template_name']='SANGAM_CUSTOM';
			$template_data[$template_key+1]['template_id']=100;
			$template_data[$template_key+1]['count']=$get_template_folder_image_count;
    	}
        //Auro Changes
    	if($subdomain[0]=="auro"){
    		if(!isset($template_key)){$template_key=-1;}
			$get_template_folder_image_count = count(glob(public_path()."/".$subdomain[0]."/backend/templates/100/*.{jpg,png,JPG,PNG,JPEG,jpeg,gif}", GLOB_BRACE));
			$template_data[$template_key+1]['template_name']='AURO_CUSTOM';
			$template_data[$template_key+1]['template_id']=100;
			$template_data[$template_key+1]['count']=$get_template_folder_image_count;
    	}
		//bnmit Changes
    	if($subdomain[0]=="bnmit"){
    		if(!isset($template_key)){$template_key=-1;}
			$get_template_folder_image_count = count(glob(public_path()."/".$subdomain[0]."/backend/templates/100/*.{jpg,png,JPG,PNG,JPEG,jpeg,gif}", GLOB_BRACE));
			$template_data[$template_key+1]['template_name']='BNMIT_CUSTOM';
			$template_data[$template_key+1]['template_id']=100;
			$template_data[$template_key+1]['count']=$get_template_folder_image_count;
    	}
		//uneb Changes
    	if($subdomain[0]=="uneb"){
    		if(!isset($template_key)){$template_key=-1;}
			$get_template_folder_image_count = count(glob(public_path()."/".$subdomain[0]."/backend/templates/100/*.{jpg,png,JPG,PNG,JPEG,jpeg,gif}", GLOB_BRACE));
			$template_data[$template_key+1]['template_name']='UNEB_CUSTOM';
			$template_data[$template_key+1]['template_id']=100;
			$template_data[$template_key+1]['count']=$get_template_folder_image_count;
    	}
    	return view('admin.dynamicImageManagement.index',compact('template_data'));
    }

    //after selecting image upload in folder and save to db
    public function store(Request $request){
    	//get all data
    	$dynamic_image_data = $request->all();
    	//call event for uploading image in folder and get response from listener that is coming from job
    	$uploaded_image_response = Event::dispatch(new DynamicImageEvent($dynamic_image_data));
    	return response()->json(['data'=>$uploaded_image_response]);
    }


    //on selecting folder displaying image
    public function displayImage($sortBy,$value,$searchkey='',CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){

    	$get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
    	$auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
    	$domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $template_name = TemplateMaster::where('id',$value)->value('template_name');
        

    	$folder_name = $value;
        
    	//arrray of image name that is inside folder
    	$filenameArray = [];
        if($folder_name=="DC_Photo"&&$subdomain[0]=="uasb"){
        	$template_name ='All';

        	$filenameArray=glob(public_path().'/'.$subdomain[0]."/backend/DC_Photo/*");

        	  // remove  path on array only get file name
        foreach ($filenameArray as $key => $value) {

        	$filenameArray[$key]=str_replace(public_path().'/'.$subdomain[0].'/backend/DC_Photo/','', $value);
        	

         }
        }else if($folder_name=="100"&&$subdomain[0]=="kessc"){
        	$template_name ='KESSC-GC';
        	$filenameArray=glob(public_path().'/'.$subdomain[0]."/backend/templates/100/*");

        	  // remove  path on array only get file name
        foreach ($filenameArray as $key => $value) {

        	$filenameArray[$key]=str_replace(public_path().'/'.$subdomain[0].'/backend/templates/100/','', $value);
        	

         }
        }else if($folder_name=="100"&&$subdomain[0]=="monad"){
        	$template_name ='MONAD';
        	$filenameArray=glob(public_path().'/'.$subdomain[0]."/backend/templates/100/*");

        	  // remove  path on array only get file name
        foreach ($filenameArray as $key => $value) {

        	$filenameArray[$key]=str_replace(public_path().'/'.$subdomain[0].'/backend/templates/100/','', $value);
        	

         }
        }else if($folder_name=="100"&&$subdomain[0]=="woxsen"){
        	$template_name ='Woxsen Custom';
        	$filenameArray=glob(public_path().'/'.$subdomain[0]."/backend/templates/100/*");

        	  // remove  path on array only get file name
        foreach ($filenameArray as $key => $value) {

        	$filenameArray[$key]=str_replace(public_path().'/'.$subdomain[0].'/backend/templates/100/','', $value);
        	

         }
        }else if($folder_name=="100"&&$subdomain[0]=="iscnagpur"){
        	$template_name ='ISCN_CUSTOM';
        	$filenameArray=glob(public_path().'/'.$subdomain[0]."/backend/templates/100/*");

        	  // remove  path on array only get file name
        foreach ($filenameArray as $key => $value) {

        	$filenameArray[$key]=str_replace(public_path().'/'.$subdomain[0].'/backend/templates/100/','', $value);
        	

         }
        }else if($folder_name=="100"&&$subdomain[0]=="uneb"){
        	$template_name ='UNEB_CUSTOM';
        	//$filenameArray=glob(public_path().'/'.$subdomain[0]."/backend/templates/100/*");
        	$folder_name=100;
        	if(!empty($searchkey)){
    		$filenameArray=glob(public_path().'/'.$subdomain[0].'/backend/templates/'.$folder_name."/".$searchkey."*.{jpg,JPG,png,PNG,jpeg,JPEG}",GLOB_BRACE);
    		}else{
    		$filenameArray=glob(public_path().'/'.$subdomain[0].'/backend/templates/'.$folder_name."/*.{jpg,JPG,png,PNG,jpeg,JPEG}",GLOB_BRACE);	
    		}

        	  // remove  path on array only get file name
        foreach ($filenameArray as $key => $value) {

        	$filenameArray[$key]=str_replace(public_path().'/'.$subdomain[0].'/backend/templates/100/','', $value);
        	

         }
        }else{
  
	    	if($get_file_aws_local_flag->file_aws_local == '1'){
	         	// get all file to Array
	         	$filenameArray=Storage::disk('s3')->allFiles($subdomain[0].'/backend/templates/'.$folder_name);
	        }
	        else{
	        	

	        	if($subdomain[0]=="tpsdiaaa"){
	        		$filenameArray=array();
	        		$directory=public_path().'/'.$subdomain[0].'/backend/templates/'.$folder_name."/";
	        		$directoryIterator = new RecursiveDirectoryIterator($directory);
					$iteratorIterator = new RecursiveIteratorIterator($directoryIterator);
					$fileList = new RegexIterator($iteratorIterator, '/^16031B15376010163.*\.(jpg|JPG|png|PNG|jpeg|JPEG)$/', \RecursiveRegexIterator::GET_MATCH);
					foreach($fileList as $file) {
					    //$filenameArray[]= $file;
					    $fileName= basename($file);

					    if(!empty($searchkey)){
		            		if (strpos($fileName, $searchkey) !== FALSE) {
								  $filenameArray[] = $fileName;
								}
		            	}else{
		            		$filenameArray[]= $fileName;
		            	}
					}
	        	}else{

	        		if(!empty($searchkey)){
	        		$filenameArray=glob(public_path().'/'.$subdomain[0].'/backend/templates/'.$folder_name."/".$searchkey."*.{jpg,JPG,png,PNG,jpeg,JPEG}",GLOB_BRACE);
	        		}else{
	        		$filenameArray=glob(public_path().'/'.$subdomain[0].'/backend/templates/'.$folder_name."/*.{jpg,JPG,png,PNG,jpeg,JPEG}",GLOB_BRACE);	
	        		}
	        	}

	        } 
	    
         // remove  path on array only get file name
        foreach ($filenameArray as $key => $value) {

	        	if($get_file_aws_local_flag->file_aws_local == '1'){
	         		$filenameArray[$key]=str_replace($subdomain[0].'/backend/templates/'.$folder_name.'/','', $value);
	         	}
	         	else{
	         		$filenameArray[$key]=str_replace(public_path().'/'.$subdomain[0].'/backend/templates/'.$folder_name.'/','', $value);
	         	}
	        

        }
     }
     
		if($sortBy == 'atoz'){
			$sort = natcasesort($filenameArray);    
		    $sortArray = [];
		  	foreach($filenameArray as $x=>$x_value)
		   	{

			   	array_push($sortArray, $x_value);
		   	}
		   //	print_r($sortArray);
		  
		   	/*$imagesArray = [];
		   	$file_display = [ 'jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'];
		   	foreach ($sortArray as $file) {
		  	$file_type = pathinfo($file, PATHINFO_EXTENSION);
            if (in_array($file_type, $file_display) == true) {
                if(!empty($searchkey)){
            		if (strpos($file, $searchkey) !== FALSE) {
						  $imagesArray[] = $file;
						}
            	}else{
            		$imagesArray[] = $file;
            	}
            }
        	}
        	print_r($imagesArray);*/
		   	return response()->json(['data'=>$sortArray,'get_file_aws_local_flag'=>$get_file_aws_local_flag,'systemConfig'=>$systemConfig['sandboxing'],'template_name'=>$template_name]);
		}else{
			//if date wise sorting
			$dateArray = [];
			foreach ($filenameArray as $key => $value) {

	            	if($get_file_aws_local_flag->file_aws_local == '1'){
	            		$gettime = Storage::disk('s3')->lastModified($subdomain[0].'/backend/templates/'.$folder_name.'/'.$value);
	             	}
	             	else{
	             		$gettime = filemtime(public_path().'/'.$subdomain[0].'/backend/templates/'.$folder_name.'/'.$value);
	             	}
	            
				$created_date = date('F d Y H:i:s',$gettime);
				$dateArray[$key]['created_date'] = date("Y-m-d H:i:s", strtotime($created_date));
				$dateArray[$key]['image_name'] = $value;
			}
			//get images by comparing time
			usort($dateArray, array($this, "compareByTimeStamp"));
			//revers image array
			$array_reverse = array_reverse($dateArray);
			
			$sorting_images = [];
			//push data in variable
			foreach ($array_reverse as $key => $sort_array) {
				
				array_push($sorting_images, $sort_array['image_name']);
			}
			/*$imagesArray = [];
		   	$file_display = [ 'jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'];
		   	foreach ($sorting_images as $file) {
		  	$file_type = pathinfo($file, PATHINFO_EXTENSION);
            if (in_array($file_type, $file_display) == true) {
                if(!empty($searchkey)){
            		if (strpos($file, $searchkey) !== FALSE) {
						  $imagesArray[] = $file;
						}
            	}else{
            		$imagesArray[] = $file;
            	}
            }
        	}*/
		   	return response()->json(['data'=>$sorting_images,'get_file_aws_local_flag'=>$get_file_aws_local_flag,'systemConfig'=>$systemConfig['sandboxing'],'template_name'=>$template_name]);
	    }
	}

	//when selecting datewise sorting for image
	function compareByTimeStamp($time1, $time2) 
	{
	    $datetime1 = strtotime($time1['created_date']); 
	    $datetime2 = strtotime($time2['created_date']); 
	   
	    return $datetime1 - $datetime2; 
	} 

	//replace image name
	public function dynamicImageEdit(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
		//get folder name
		$get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

		$auth_site_id=\Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

		$domain = \Request::getHost();
        $subdomain = explode('.', $domain);

		$folder_name = $request->folder_name;
		//get old image name
		$old_image_name = $request->old_image_name;
		//seperate extension from image name
		$extc = explode('.', $old_image_name);

		$image_name = $request->image_name;

		//go to target directory of folder name
		if($folder_name=="DC_Photo"&&$subdomain[0]=="uasb"){
			$targetDir =$subdomain[0].'/backend/'.$folder_name;
		}else{
		if($get_file_aws_local_flag->file_aws_local == '1'){

	        $targetDir =$subdomain[0].'/backend/templates/'.$folder_name;
	        
	    }
	    else{
	    	
	        	$targetDir =public_path().'/'.$subdomain[0].'/backend/templates/'.$folder_name;
	       
	    	}
		}
        $old_image_name = $targetDir.'/'.$old_image_name;
		$image_name = $image_name.'.'.$extc[1];
		$new_name = $targetDir.'/'.$image_name;
		//compare old image name with new image name
		if($old_image_name == $new_name){
		
			$message = Array('success' => 'true', 'message' => 'rename successfully','image'=>$image_name);
       	
       	    return $message;	        
		}else{
			if($get_file_aws_local_flag->file_aws_local == '1'){
				if(!Storage::disk('s3')->exists($new_name)){
	                
			        //copy new file name
					$data = Storage::disk('s3')->copy($old_image_name,$new_name);
					//delete old file name
					Storage::disk('s3')->delete($old_image_name);
	                
					$message = Array('success' => 'true', 'message' => 'rename successfully','image'=>$image_name);
	       	
	       	    	return $message;
				}else{
					$message = Array('success' => 'false', 'message' => 'image name already exists');
	       	
	       	    	return $message;
				}
			}
			else{
				if(!file_exists($new_name)){
	                
			        //copy new file name
			        \File::copy($old_image_name,$new_name);
					//delete old file name
					unlink($old_image_name);
	                
					$message = Array('success' => 'true', 'message' => 'rename successfully','image'=>$image_name);
	       	
	       	    	return $message;
				}else{
					$message = Array('success' => 'false', 'message' => 'image name already exists');
	       	
	       	    	return $message;
				}
			}
		}
	}

	//deleting image
	public function delete(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
		$get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

		$auth_site_id=\Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

		$domain = \Request::getHost();
        $subdomain = explode('.', $domain);


		$folder_name = $request->folder_name;
		$image_name = $request->image_name;
		if($folder_name=="DC_Photo"&&$subdomain[0]=="uasb"){
			$targetDir =$subdomain[0].'/backend/';
		}else{
		if($get_file_aws_local_flag->file_aws_local == '1'){

	        	$targetDir =$subdomain[0].'/backend/templates/'.$folder_name.'/'.$image_name;
				$targetDirImage =$subdomain[0].'/backend/templates/'.$folder_name;
	        
	    }
	    else{

	        	$targetDir =$subdomain[0].'/backend/templates/';
	        
	    }
		}
		//get login user id
		$admin_id = \Auth::guard('admin')->user()->toArray();
		if($get_file_aws_local_flag->file_aws_local == '1'){

			if(Storage::disk('s3')->exists($targetDir)){
				
				Storage::disk('s3')->delete($targetDir);
				//store delete image data in delete image history table
				ImageDeleteHistory::create(['admin_id'=>$admin_id['id'],'image_name'=>$image_name,'template_name'=>$folder_name]);

				//get image count
				$all_files = Storage::disk('s3')->allFiles($targetDirImage);
    			$files = [];
    			$ext_array = ['jpg','png','JPG','PNG','JPEG','jpeg','gif'];
    			foreach ($all_files as $all_fileskey => $all_filesvalue) {
    				$explode_file = explode('.',$all_filesvalue);
    				if(in_array($explode_file[1], $ext_array)){
    					$files[] = $all_filesvalue;
    				}
    			}
				$imageCounts = count($files);
				
				$message = Array('success' => 'true', 'message' => 'image delete successfully','imageCounts'=>$imageCounts);
	       		return $message;
			}
		}
		else{
			if(file_exists(public_path().'/'.$targetDir.$folder_name.'/'.$image_name)){
				unlink(public_path().'/'.$targetDir.$folder_name.'/'.$image_name);
				//store delete image data in delete image history table
				ImageDeleteHistory::create(['admin_id'=>$admin_id['id'],'image_name'=>$image_name,'template_name'=>$folder_name]);

				//get image count

				$imageCounts = count(glob(public_path().'/'.$targetDir.$folder_name."/*.{jpg,png,JPG,PNG,JPEG,'jpeg','gif'}", GLOB_BRACE));
				
				$message = Array('success' => 'true', 'message' => 'image delete successfully','imageCounts'=>$imageCounts);
	       		return $message;
			}
		}
	}
}

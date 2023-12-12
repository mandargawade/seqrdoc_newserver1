<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\IdCard;
use App\models\TemplateMaster;
use App\models\BackgroundTemplateMaster;
use App\models\IDCardStatus;
use App\models\StudentTable;
use App\models\SystemConfig;
use App\models\FieldMaster;
use App\models\FontMaster;
use App\Http\Requests\ExcelValidationRequest;
use App\Imports\IDcardImport;
use Excel;
use File;
use App\Jobs\SendMailJob;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell;
use Illuminate\Support\Collection;
use Auth,Storage;
use TCPDF;
use QrCode;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use App\models\SbStudentTable;
use App\models\Config;
use App\models\PrintingDetail;
use App\Helpers\CoreHelper;
class IdCardController extends Controller
{
    public function index(Request $request)
    {
    	if($request->ajax())
        {
            $data = $request->all();
            $where_str = '1 = ?';
            $where_params = [1];

            if($request->has('sSearch'))
            {
                $search = $request->get('sSearch');
                $where_str .= " and ( template_name like \"%{$search}%\""
                           . ")";
            }

            $columns = ['id','template_name'];
            $auth_site_id=Auth::guard('admin')->user()->site_id;

            $idcards = TemplateMaster::select($columns)
            					->whereIn('id',['1','2','3','4','5','6','7'])
                               	->whereRaw($where_str, $where_params)
                               	->where('site_id',$auth_site_id)
                               	->orderBy('id','asc');

            $idcards_count = TemplateMaster::select('id')
            						->whereIn('id',['1','2','3','4','5','6','7'])
                                    ->whereRaw($where_str, $where_params)
                                    ->where('site_id',$auth_site_id)
                                    ->orderBy('id','asc')
                                    ->count();
           
            

            if($request->has('iDisplayStart') && $request->get('iDisplayLength') !='-1'){
                $idcards = $idcards->take($request->get('iDisplayLength'))->skip($request->get('iDisplayStart'));
            }

            if($request->has('iSortCol_0')){
                for ( $i = 0; $i < $request->get('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->get('iSortCol_' . $i)];
                    if (false !== ($index = strpos($column, ' as '))) {
                        $column = substr($column, 0, $index);
                    }
                    $idcards = $idcards->orderBy($column,$request->get('sSortDir_'.$i));
                }
            }

            $idcards = $idcards->get();
            

            $response['iTotalDisplayRecords'] = $idcards_count;
            $response['iTotalRecords'] = $idcards_count;

            $response['sEcho'] = intval($request->get('sEcho'));

            $response['aaData'] = $idcards;

            return $response;
        }
    	return view('admin.idCards.index');
    }

    public function excelvalidation(ExcelValidationRequest $request){
        if($request['field_file'] != 'undefined'){
            $file_name = $request['field_file']->getClientOriginalName();
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);
            
            if($ext != 'xls' && $ext != 'xlsx'){    
                return response()->json(['success'=>false,'message'=>'Please enter valid excel sheet']);
            }
        }
        else{
            return response()->json(['success'=>false,'message'=>'Please upload file with .xls or .xlsx extension!','type'=>'toaster']);
        }
        return response()->json(['success'=>true]);
    }

    public function excelcheck(Request $request){
        //excel process
        $template_id = $request->id;
        //get template name from template master from template id
        $template_name = TemplateMaster::select('template_name')->where('id',$template_id)->first();
        //get template name count from student table

        $auth_site_id=\Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
        	$counts = SbStudentTable::select('id')->where('template_id',$template_id)->count();
        }
        else{
        	$counts = StudentTable::select('id')->where('template_id',$template_id)->count();
        }

        if($counts < 2){
            $msg = array('type'=> 'counts', 'message' => 'no excel history found');
            return json_encode($msg);
        }else{

            $msg = array('type'=> 'duplicate', 'message' => 'excel history founded.','old_rows'=>$counts);
                return json_encode($msg);
        }
    }

    public function manageExcel(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
	{	

		//check upload to aws/local (var(get_file_aws_local_flag) coming through provider)
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

		$data = $request->all();
		
		$template_id = $data['id'];

		$auth_site_id=\Auth::guard('admin')->user()->site_id;
		$auth_id=\Auth::guard('admin')->user()->id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
		// Get Unique serial no and template name
		$template_data = TemplateMaster::where('id',$template_id)
											->first()->toArray();
		
		$background_template_id = $template_data['bg_template_id'];
		// Get the background template data

		$bg_template_data = BackgroundTemplateMaster::where('id',$background_template_id)
														->first();
		// Get fields data
		$field_data = FieldMaster::where('template_id',$template_id)->get()->toArray();
	

		$FID = [];
		foreach ($field_data as $frow) {
			
			$FID['mapped_name'][] = $frow['mapped_name'];
			$FID['mapped_excel_col'][] = '';
			$FID['data'][] = '';
			$FID['security_type'][] = $frow['security_type'];
			$FID['field_position'][] = $frow['field_position'];
			$FID['text_justification'][] = $frow['text_justification'];
			$FID['x_pos'][] = $frow['x_pos'];
			$FID['y_pos'][] = $frow['y_pos'];
			$FID['width'][] = $frow['width'];
			$FID['height'][] = $frow['height'];
			$FID['font_style'][] = $frow['font_style'];
			$FID['font_id'][] = $frow['font_id'];
			$FID['font_size'][] = $frow['font_size'];
			$FID['font_color'][] = $frow['font_color'];
			$FID['font_color_extra'][] = $frow['font_color_extra'];

			$FID['sample_image'][] = $frow['sample_image'];
			$FID['angle'][] = $frow['angle'];
			$FID['sample_text'][] = $frow['sample_text'];
			$FID['line_gap'][] = $frow['line_gap'];
			$FID['length'][] = $frow['length'];
			$FID['uv_percentage'][] = $frow['uv_percentage'];
			$FID['is_repeat'][] = $frow['is_repeat'];
			$FID['field_sample_text_width'][] = $frow['field_sample_text_width'];
			$FID['field_sample_text_vertical_width'][] = $frow['field_sample_text_vertical_width'];
			$FID['field_sample_text_horizontal_width'][] = $frow['field_sample_text_horizontal_width'];
			// end get data from db and store in array
			$FID['is_mapped'][] = $frow['is_mapped'];
			$FID['infinite_height'][] = $frow['infinite_height'];
			$FID['include_image'][] = $frow['include_image'];
			$FID['grey_scale'][] = $frow['grey_scale'];
		}
		if($request->hasfile('field_file'))
	    {
			// append excelfile name with current datetime
			$file = $request->file('field_file');
	        $extension = $file->getClientOriginalExtension();
	        $filename = basename($file->getClientOriginalName(), '.'.$file->getClientOriginalExtension());
	        $filename = str_replace(' ', '_', $filename);
			$filename =  $newflname =$filename."_".date("YmdHis").".".$extension;
			
			$template_name = str_replace(' ','_',$template_data['template_name']);


			if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
				$path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'];
			}
			else{
				$path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'];
			}
			if($get_file_aws_local_flag->file_aws_local == '1'){
	    		if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
					$aws_path = '/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'];
				}
				else{
					$aws_path = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'];
				}
			}

			$template_filename = $path.'/'.$filename;
				
			if($file->move($path,$filename))
			{
				$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($template_filename);
				$reader->setReadDataOnly(true);
				$spreadsheet = $reader->load($template_filename);
				$sheet = $spreadsheet->getSheet(0);
				$highestColumn = $sheet->getHighestColumn();
				$highestRow = $sheet->getHighestDataRow();
				
				$rowData = $sheet->rangeToArray('A1:' . $highestColumn . '1', NULL, TRUE, FALSE);



				if($get_file_aws_local_flag->file_aws_local == '1'){
					 $aws_storage = \Storage::disk('s3')->put($aws_path.'/'.$filename, file_get_contents($template_filename), 'public');
					$filename = \Storage::disk('s3')->url($filename);
				}
				
			   	$formula = [];
                foreach ($sheet->getCellCollection() as $cellId) {
                    foreach ($cellId as $key1 => $value1) {
                        $checkFormula = $sheet->getCell($cellId)->isFormula();
                        if($checkFormula == 1){
                            $formula[] = $cellId;       
                        }   
                    }  
                };

				if(!empty($formula)){

					if (file_exists($target_path)) {
			    		unlink($target_path);
			    	}
			    	return response()->json(['success'=>false,'message'=>"Please remove formula from column','cell'=>$formula",'type'=>'toaster']);
				}
				$oldRows = 0;
				$newRows = 0;
				if(file_exists(public_path().'/'.$subdomain[0].'/test.txt')){
									unlink(public_path().'/'.$subdomain[0].'/test.txt');
				}
				//$required_data_not_exists = [];
				$file_not_exists = [];
				for($excel_row = 2; $excel_row <= $highestRow; $excel_row++)
				{
					$rowData1 = $sheet->rangeToArray('A'. $excel_row . ':' . $highestColumn . $excel_row, NULL, TRUE, FALSE);
					$serial_no = $rowData1[0][2];

					//if($subdomain[0]=="test"){
						/*print_r($rowData1);
						exit;*/
						if(empty($rowData1[0][0])||empty($rowData1[0][1])||empty($rowData1[0][2])){
							//$required_data_not_exists = [];
							 return response()->json(['success'=>false,'message'=>'In some of records Unique no, Candidate name or enrollment no are missing.','type'=>'toster']);
						}
					//}

					if($get_file_aws_local_flag->file_aws_local == '1'){
		        		if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
				        	$file_location_jpg = '/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$serial_no.'.jpg';

				        	$file_location_png ='/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$serial_no.'.png';
				        }
				        else{
				        	$file_location_jpg = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$serial_no.'.jpg';

				        	$file_location_png = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$serial_no.'.png';
				        }
			        }
			        else{
		        	
			        	if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
				        	$file_location_jpg = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$serial_no.'.jpg';

				        	$file_location_png = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$serial_no.'.png';
				        }
				        else{

				        	/*$file_location_jpg = url('/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$serial_no.'.jpg');
				        	$file_location_png = url('/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$serial_no.'.png');
				        	*/
				        	$file_location_jpg= 'https://'.$subdomain[0].'.seqrdoc.com/'.$subdomain[0].'/backend/templates/'.$template_id.'/'.$serial_no.'.jpg';
                    		$file_location_png = 'https://'.$subdomain[0].'.seqrdoc.com/'.$subdomain[0].'/backend/templates/'.$template_id.'/'.$serial_no.'.png';
				        }
			        }

			        if(!$this->check_file_exist($file_location_jpg)){
			        	if(!$this->check_file_exist($file_location_png)){
			        		
			        		$file = public_path().'/'.$subdomain[0].'/test.txt';
							        		
							file::append($file,$serial_no.PHP_EOL);
							if($serial_no != ''){

								array_push($file_not_exists, $serial_no);
							}

			        	}
			        }
					
			        
				}
				
				
				if(count($file_not_exists) > 0){
					$path = 'https://'.$subdomain[0].'.seqrdoc.com/';
			    	$msg = "<b>Click <a href='".$path.$subdomain[0].'/test.txt'."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
		            return response()->json(['success'=>false,'message'=>'Please add images in folder of your template name','type'=>'toster','msg'=>$msg]);
				}

		        for($excel_row = 2; $excel_row <= $highestRow; $excel_row++)
				{
					$rowData1 = $sheet->rangeToArray('A'. $excel_row . ':' . $highestColumn . $excel_row, NULL, TRUE, FALSE);
					
		        	$count =  count($FID['mapped_name']);
		        	$serial_no = $rowData1[0][2];
		        	if($get_file_aws_local_flag->file_aws_local == '1'){
		        		if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
				        	$file_location_jpg = '/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$serial_no.'.jpg';

				        	$file_location_png ='/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$serial_no.'.png';
				        }
				        else{
				        	$file_location_jpg = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$serial_no.'.jpg';

				        	$file_location_png = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$serial_no.'.png';
				        }
			        }
			        else{
		        	
			        	if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
				        	$file_location_jpg = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$serial_no.'.jpg';

				        	$file_location_png = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$serial_no.'.png';
				        }
				        else{

				        	$file_location_jpg = url('/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$serial_no.'.jpg');
				        	$file_location_png = url('/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$serial_no.'.png');
				        }
			        }
			        
		        	$target_path = $path.'/'.$newflname;

		        	if($get_file_aws_local_flag->file_aws_local == '1'){
						if (!Storage::disk('s3')->exists($file_location_png) || !Storage::disk('s3')->exists($file_location_jpg)) {
			        		if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
								if (Storage::disk('s3')->exists('/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$newflname)) {
						    		Storage::disk('s3')->delete($subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$newflname);
						    	}
					            return response()->json(['success'=>false,'message'=>'Please add images in folder of your template name','type'=>'toster']);
							}
							else{
								if (Storage::disk('s3')->exists('/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$newflname)) {
						    		Storage::disk('s3')->delete($subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$newflname);
						    	}
					            return response()->json(['success'=>false,'message'=>'Please add images in folder of your template name','type'=>'toster']);
							}
				        
				        }
					}
					else{
						$exists = $this->check_file_exist($file_location_jpg);
						
						if($exists){
						    
						} else {

							$exists = $this->check_file_exist($file_location_png);
							if($exists){
							   
							}else {

								for($excel_row = 2; $excel_row <= $highestRow; $excel_row++)
								{
									$rowData1 = $sheet->rangeToArray('A'. $excel_row . ':' . $highestColumn . $excel_row, NULL, TRUE, FALSE);
									
						        	$count =  count($FID['mapped_name']);
						        	$serial_no = $rowData1[0][2];
						        	

						        	
						        	if($get_file_aws_local_flag->file_aws_local == '1'){
						        		if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
								        	$file_location_jpg = '/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$serial_no.'.jpg';

								        	$file_location_png ='/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$serial_no.'.png';
								        }
								        else{
								        	$file_location_jpg = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$serial_no.'.jpg';

								        	$file_location_png = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$serial_no.'.png';
								        }
							        }
							        else{
							        	if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
								        	$file_location_jpg = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$serial_no.'.jpg';

								        	$file_location_png = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$serial_no.'.png';
								        }
								        else{
								        	$file_location_jpg = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$serial_no.'.jpg';

								        	$file_location_png = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$serial_no.'.png';
								        }
							        }

						        	

						        	$target_path = $path.'/'.$newflname;

						        	// dd($file_location_png);
									if($get_file_aws_local_flag->file_aws_local == '1'){
										if (!Storage::disk('s3')->exists($file_location_png) || !Storage::disk('s3')->exists($file_location_jpg)) {
							        		if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
												if (Storage::disk('s3')->exists('/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$newflname)) {
										    		Storage::disk('s3')->delete($subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$newflname);
										    	}
									            return response()->json(['success'=>false,'message'=>'Please add images in folder of your template name','type'=>'toster']);
											}
											else{
												if (Storage::disk('s3')->exists('/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$newflname)) {
										    		Storage::disk('s3')->delete($subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$newflname);
										    	}
									            return response()->json(['success'=>false,'message'=>'Please add images in folder of your template name','type'=>'toster']);
											}
								        
								        }
									}

									
								}

						    	if (file_exists($target_path)) {
						    		unlink($target_path);
						    	}
						    	
							}
						}
					}
			        
			        for($extra_fields = 1; $extra_fields < $count; $extra_fields++)
					{
						if($FID['security_type'][$extra_fields ] == "Static Microtext Border" || $FID['security_type'][$extra_fields] == "Static Text")
						{
							$str = $FID['sample_text'][$extra_fields];
							
						}else{
							if (isset($rowData1[0][2])) {
								if(isset($rowData1[0][$extra_fields])){
									$str = $rowData1[0][$extra_fields];
								}
								else{
									$str = '';
								}
							}else{

								if (file_exists($target_path)) {
						    		unlink($target_path);
						    	}
								return response()->json(['success'=>false,'message'=>'Enrollment no is require','type'=>'toster']);	
							}	
						}
					}
					$rowData2 = $sheet->rangeToArray('B'. $excel_row . ':B' . $excel_row, NULL, TRUE, FALSE);

					$columnValue = $rowData2[0][0];
					if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
						$rowCount = SbStudentTable::where('serial_no',$columnValue)->count();
					}
					else{
						$rowCount = StudentTable::where('serial_no',$columnValue)->count();
					}
						
						if($rowCount > 0){

							$oldRows++;
						}else{
							$newRows++;
					}	
					
					if(isset($data['varified']) && $data['varified'] != 1){
						return response()->json(['success'=>'false','type'=>'toster', 'message' => 'excel history founded','old_rows'=>$oldRows,'new_rows'=>$newRows,'template_id'=>$template_id]);
					}
					$userQuery =  \Auth::guard('admin')->user()->toArray();
					$user_fullname= $userQuery['fullname'];
					$user_email= $userQuery['email'];
					
					$created_on = date('Y-m-d H:i:s');
					$highRow = $highestRow - 1;

					$fecth_request = IDCardStatus::orderBy('id','DESC')->first();
					$count_request = IDCardStatus::orderBy('id','DESC')->count();
					
					$year = date('y');

					if ($count_request < 1 || empty($count_request)) 
					{
						// R/19/1
						$request_number = 'R/'.$year.'/1';
					
					}
					else
					{
						$get_request_number = $fecth_request['request_number'];
						$get_last_no = explode('/', $fecth_request['request_number']);
						$get_last_no[2]++;
						$request_number = 'R/'.$year.'/'.$get_last_no[2];
					}
					$id_card_status = new IDCardStatus();
					$id_card_status['request_number'] = $request_number;
					$id_card_status['template_name'] = $template_name;
					$id_card_status['rows'] = $highRow;
					$id_card_status['excel_sheet'] = $newflname;
					$id_card_status['status'] = 'Received';
					$id_card_status['site_id'] = $auth_site_id;
					$id_card_status['uploaded_by'] = $auth_id;
					$id_card_status['created_on'] = $created_on;
					$id_card_status->save();
					$mail_view = 'mail.idcard';
	                $user_email = ['dtp@scube.net.in','ankit@scube.net.in','asdawson@tatapower.com'];
	                $mail_subject = "Test - TPSDI ".$request_number." Request for ".$highRow." ".$template_name." is received.";
	                $user_data = ['name'=>$user_fullname,'request_number'=>$request_number,'created_on'=>$created_on,'template_name'=>$template_name,'highRow'=>$highRow];

	                $this->dispatch(new SendMailJob($mail_view,$user_email,$mail_subject,$user_data));

	                $message = 'The Excel is successfully received for '.$highRow.' data rows. Request Number '.$request_number.'. Soon, these ID cards will be submitted for print';

	                if ($get_file_aws_local_flag->file_aws_local == '1') {
			    		unlink($template_filename);
			    	}


	                return response()->json(['success'=>'true','message'=>$message,'type'=>'toaster']);
				}

			}
			else
			{
				return response()->json(['success'=>false,'message'=>'File Not Move In Folder','type'=>'toaster']);
			}
		}
		else
		{
			return response()->json(['success'=>false,'message'=>'File not uploaded','type'=>'toaster']);
		}
	}
	public function generateSoftcopy(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){

		//check upload to aws/local (var(get_file_aws_local_flag) coming through provider)
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

		$data = $request->all();
		$template_id = $data['id'];

		$auth_site_id=\Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
		// Get Unique serial no and template name
		$template_data = TemplateMaster::where('id',$template_id)
											->first()->toArray();
		$background_template_id = $template_data['bg_template_id'];
		// Get the background template data

		$bg_template_data = BackgroundTemplateMaster::where('id',$background_template_id)
														->first();
		// Get fields data
		$field_data = FieldMaster::where('template_id',$template_id)->get()->toArray();
		$FID = [];
		foreach ($field_data as $frow) {
			$FID['mapped_name'][] = $frow['mapped_name'];
			$FID['mapped_excel_col'][] = '';
			$FID['data'][] = '';
			$FID['security_type'][] = $frow['security_type'];
			$FID['field_position'][] = $frow['field_position'];
			$FID['text_justification'][] = $frow['text_justification'];
			$FID['x_pos'][] = $frow['x_pos'];
			$FID['y_pos'][] = $frow['y_pos'];
			$FID['width'][] = $frow['width'];
			$FID['height'][] = $frow['height'];
			$FID['font_style'][] = $frow['font_style'];
			$FID['font_id'][] = $frow['font_id'];
			$FID['font_size'][] = $frow['font_size'];
			$FID['font_color'][] = $frow['font_color'];
			$FID['font_color_extra'][] = $frow['font_color_extra'];

			$FID['sample_image'][] = $frow['sample_image'];
			$FID['angle'][] = $frow['angle'];
			$FID['sample_text'][] = $frow['sample_text'];
			$FID['line_gap'][] = $frow['line_gap'];
			$FID['length'][] = $frow['length'];
			$FID['uv_percentage'][] = $frow['uv_percentage'];
			$FID['is_repeat'][] = $frow['is_repeat'];
			$FID['field_sample_text_width'][] = $frow['field_sample_text_width'];
			$FID['field_sample_text_vertical_width'][] = $frow['field_sample_text_vertical_width'];
			$FID['field_sample_text_horizontal_width'][] = $frow['field_sample_text_horizontal_width'];
			// end get data from db and store in array
			$FID['is_mapped'][] = $frow['is_mapped'];
			$FID['infinite_height'][] = $frow['infinite_height'];
			$FID['include_image'][] = $frow['include_image'];
			$FID['grey_scale'][] = $frow['grey_scale'];
		}
		if($request->hasfile('field_file_excel'))
	    {
	    	$file = $request->file('field_file_excel');
	        $extension = $file->getClientOriginalExtension();
	        $filename = basename($file->getClientOriginalName(), '.'.$file->getClientOriginalExtension());
	        $filename = str_replace(' ', '_', $filename);
			$filename =  $newflname =$filename."_".date("YmdHis").".".$extension;
			
			$template_name = str_replace(' ','_',$template_data['template_name']);


			if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
				$path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'];
			}
			else{
				$path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'];
			}
			if($get_file_aws_local_flag->file_aws_local == '1'){
	    		if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
					$aws_path = '/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'];
				}
				else{
					$aws_path = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'];
				}
			}
			$template_filename = $path.'/'.$filename;
				
			if($file->move($path,$filename))
			{
				$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($template_filename);
				$reader->setReadDataOnly(true);
				$spreadsheet = $reader->load($template_filename);
				$sheet = $spreadsheet->getSheet(0);
				$highestColumn = $sheet->getHighestColumn();
				$highestRow = $sheet->getHighestRow();
				
				$rowData = $sheet->rangeToArray('A1:' . $highestColumn . '1', NULL, TRUE, FALSE);



				if($get_file_aws_local_flag->file_aws_local == '1'){
					 $aws_storage = \Storage::disk('s3')->put($aws_path.'/'.$filename, file_get_contents($template_filename), 'public');
					$filename = \Storage::disk('s3')->url($filename);
				}
				$formula = [];
                foreach ($sheet->getCellCollection() as $cellId) {
                    foreach ($cellId as $key1 => $value1) {
                        $checkFormula = $sheet->getCell($cellId)->isFormula();
                        if($checkFormula == 1){
                            $formula[] = $cellId;       
                        }   
                    }  
                };

				if(!empty($formula)){

					if (file_exists($target_path)) {
			    		unlink($target_path);
			    	}
			    	return response()->json(['success'=>false,'message'=>"Please remove formula from column','cell'=>$formula",'type'=>'toaster']);
				}
				$oldRows = 0;
				$newRows = 0;
				if(file_exists(public_path().'/'.$subdomain[0].'/test.txt')){
									unlink(public_path().'/'.$subdomain[0].'/test.txt');
				}
				$file_not_exists = [];
		        for($excel_row = 2; $excel_row <= $highestRow; $excel_row++)
				{
					$rowData1 = $sheet->rangeToArray('A'. $excel_row . ':' . $highestColumn . $excel_row, NULL, TRUE, FALSE);
					$serial_no = $rowData1[0][2];
					if($get_file_aws_local_flag->file_aws_local == '1'){
		        		if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
				        	$file_location_jpg = '/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$serial_no.'.jpg';

				        	$file_location_png ='/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$serial_no.'.png';
				        }
				        else{
				        	$file_location_jpg = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$serial_no.'.jpg';

				        	$file_location_png = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$serial_no.'.png';
				        }
			        }
			        else{
		        	
			        	if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
				        	$file_location_jpg = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$serial_no.'.jpg';

				        	$file_location_png = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$serial_no.'.png';
				        }
				        else{

				        	/*$file_location_jpg = url('/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$serial_no.'.jpg');
				        	$file_location_png = url('/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$serial_no.'.png');
				        	*/
				        	$file_location_jpg= 'https://'.$subdomain[0].'.seqrdoc.com/'.$subdomain[0].'/backend/templates/'.$template_id.'/'.$serial_no.'.jpg';
                    		$file_location_png = 'https://'.$subdomain[0].'.seqrdoc.com/'.$subdomain[0].'/backend/templates/'.$template_id.'/'.$serial_no.'.png';
				        }
			        }

			        if(!$this->check_file_exist($file_location_jpg)){
			        	if(!$this->check_file_exist($file_location_png)){
			        		
			        		$file = public_path().'/'.$subdomain[0].'/test.txt';
							        		
							file::append($file,$serial_no.PHP_EOL);
							if($serial_no != ''){

								array_push($file_not_exists, $serial_no);
							}

			        	}
			        }
					
			        
				}
				if(count($file_not_exists) > 0){
					$path = 'https://'.$subdomain[0].'.seqrdoc.com/';
			    	$msg = "<b>Click <a href='".$path.$subdomain[0].'/test.txt'."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
		            return response()->json(['success'=>false,'message'=>'Please add images in folder of your template name','type'=>'toster','msg'=>$msg]);
				}
				

				$message = "success";
				return response()->json(['success'=>'true','message'=>$message,'type'=>'toaster']);
			}else
			{
				return response()->json(['success'=>false,'message'=>'File Not Move In Folder','type'=>'toaster']);
			}
	    }else
		{
			return response()->json(['success'=>false,'message'=>'File not uploaded','type'=>'toaster']);
		}
	}
	public function processPdfSoftCopy(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){

		$get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        $systemConfig = SystemConfig::first();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $template_id = $request['id'];

        $templateMaster = TemplateMaster::select('id','unique_serial_no','template_name','bg_template_id','width','background_template_status')->where('id',
            $template_id)->first();

        $template_id = $templateMaster['id'];
        $unique_serial_no = $templateMaster['unique_serial_no'];
        $template_name = $templateMaster['template_name'];
        $background_template_id = $templateMaster['bg_template_id'];
        $template_width = $templateMaster['width'];
        $template_height = $templateMaster['height'];
        $backgound_template_status = $templateMaster['background_template_status'];

        if($background_template_id != 0) {

            $backgroundMaster = BackgroundTemplateMaster::select('width','height')
                            ->where('id',$background_template_id)
                            ->first();

            $template_width = $backgroundMaster['width'];
            $template_height = $backgroundMaster['height'];
        }
        $FID = [];
        $FID['template_id']  = $template_id;
        $FID['bg_template_id']  = $background_template_id;
        $FID['template_width']  = $template_width;
        $FID['template_height'] = $template_height;
        $FID['template_name']  =  $template_name;
        $FID['background_template_status']  =  $backgound_template_status;
        $FID['printing_type'] = 'pdfGenerate';
        $fields_master = FieldMaster::where('template_id',$template_id)
                        ->orderBy('field_position','asc')
                        ->get();
                        
        $fields =  collect($fields_master);

        $check_mapped = array();
        foreach ($fields as $key => $value) {
            
            $FID['mapped_name'][] = $value['mapped_name'];
            $FID['mapped_excel_col'][] = '';
            $FID['data'][] = '';
            $FID['security_type'][] = $value['security_type'];
            $FID['field_position'][] = $value['field_position'];
            $FID['text_justification'][] = $value['text_justification'];
            $FID['x_pos'][] = $value['x_pos'];
            $FID['y_pos'][] = $value['y_pos'];
            $FID['width'][] = $value['width'];
            $FID['height'][] = $value['height'];
            $FID['font_style'][] = $value['font_style'];
            $FID['font_id'][] = $value['font_id'];
            $FID['font_size'][] = $value['font_size'];
            $FID['font_color'][] = $value['font_color'];
            $FID['font_color_extra'][] = $value['font_color_extra'];
            
            // created by Rushik 
            // start get data from db and store in array 
            $FID['sample_image'][] = $value['sample_image'];
            $FID['angle'][] = $value['angle'];
            $FID['sample_text'][] = $value['sample_text'];
            $FID['line_gap'][] = $value['line_gap'];
            $FID['length'][] = $value['length'];
            $FID['uv_percentage'][] = $value['uv_percentage'];
            $FID['print_type'] = 'pdf';
            $FID['is_repeat'][] = $value['is_repeat'];
            $FID['field_sample_text_width'][] = $value['field_sample_text_width'];
            $FID['field_sample_text_vertical_width'][] = $value['field_sample_text_vertical_width'];
            $FID['field_sample_text_horizontal_width'][] = $value['field_sample_text_horizontal_width'];
            // end get data from db and store in array
            $FID['is_mapped'][] = $value['is_mapped'];
            $FID['infinite_height'][] = $value['infinite_height'];
            $FID['include_image'][] = $value['include_image'];
            $FID['grey_scale'][] = $value['grey_scale'];
        }
        if($request->hasfile('field_file_excel'))
	    {
	    	$file = $request->file('field_file_excel');
	        $extension = $file->getClientOriginalExtension();
	        $filename = basename($file->getClientOriginalName(), '.'.$file->getClientOriginalExtension());
	        $filename = str_replace(' ', '_', $filename);
			$filename =  $newflname =$filename."_".date("YmdHis").".".$extension;
			
			$template_name = str_replace(' ','_',$templateMaster['template_name']);

			if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
				$path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$templateMaster['id'];
			}
			else{
				$path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$templateMaster['id'];
			}
			
			if($get_file_aws_local_flag->file_aws_local == '1'){
	    		if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
					$aws_path = '/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$templateMaster['id'];
				}
				else{
					$aws_path = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$templateMaster['id'];
				}
			}
			$template_filename = $path.'/'.$filename;
			if($file->move($path,$filename))
			{
				$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($template_filename);
				$reader->setReadDataOnly(true);
				$spreadsheet = $reader->load($template_filename);
				$sheet = $spreadsheet->getSheet(0);
				$fonts_array = array('' => '');
				$excelfile = $filename;
		        foreach ($FID['font_id'] as $key => $font) {
		                

		            if(($font != '' && $font != 'null' && !empty($font)) || ($font == '0')){
		          
		                if($font != '0')
		                {
		                    $fontMaster = FontMaster::select('font_name','font_filename','font_filename_N','font_filename_B','font_filename_I','font_filename_BI')
		                    ->where('id',$font)
		                    ->first();
		                }
		                else{
		                    $fontMaster = FontMaster::select('font_name','font_filename','font_filename_N','font_filename_B','font_filename_I','font_filename_BI')
		                    ->first();
		                }   

		                if($FID['font_style'][$key] == ''){
		                    if($get_file_aws_local_flag->file_aws_local == '1'){ 
		                            $font_filename = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/fonts/' . $fontMaster['font_filename_N'];

		                            $filename = $subdomain[0].'/backend/canvas/fonts/'.$fontMaster['font_filename_N'];

		                            $font_name[$key] = $fontMaster['font_filename'];
		                            if(!\Storage::disk('s3')->has($filename))
		                            {
		                                if (!file_exists($font_filename)) {
		                                

		                                    $message = Array('type' => 'error', 'message' => $font['font_filename_N'].' font not found');
		                                    echo json_encode($message);
		                                    exit;
		                                }
		                            }
		                        }
		                        else{
		                            $font_filename = public_path().'/'.$subdomain[0].'/backend/canvas/fonts/'.$fontMaster['font_filename_N'];
		                            $font_name[$key] = $fontMaster['font_filename'];

		                            if (!file_exists($font_filename)) {
		                                

		                                $message = Array('type' => 'error', 'message' => $font['font_filename_N'].' font not found');
		                                echo json_encode($message);
		                                exit;
		                            }
		                        }
		                }else if($FID['font_style'][$key] == 'B'){
		                    if($get_file_aws_local_flag->file_aws_local == '1'){ 
		                        $font_filename = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/fonts/'.$fontMaster['font_filename_B'];
		                    }
		                    else{
		                        $font_filename = public_path().'/'.$subdomain[0].'/backend/canvas/fonts/'.$fontMaster['font_filename_B'];
		                    }
		               
		                }else if($FID['font_style'][$key] == 'I'){
		                    if($get_file_aws_local_flag->file_aws_local == '1'){ 
		                        $font_filename = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/fonts/' . $fontMaster['font_filename_I'];
		                    }
		                    else{
		                        $font_filename = public_path().'/'.$subdomain[0].'/backend/canvas/fonts/' . $fontMaster['font_filename_I'];  
		                    }   
		                }else if($FID['font_style'][$key] == 'BI'){
		                    if($get_file_aws_local_flag->file_aws_local == '1'){ 
		                        $font_filename = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/fonts/' . $fontMaster['font_filename_BI'];
		                    }
		                    else{
		                        $font_filename = public_path().'/'.$subdomain[0].'/backend/canvas/fonts/' . $fontMaster['font_filename_BI'];    
		                    }  
		                }
		                if($get_file_aws_local_flag->file_aws_local == '1'){ 
		                    if(!\Storage::disk('s3')->has($filename))
		                    {
		                        // if other styles are not present then load normal file
		                        $font_filename = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/fonts/' . $get_font_data['font_filename_N'];
		                        $filename = $subdomain[0].'/backend/canvas/fonts/'.$get_font_data['font_filename_N'];
		                  
		                        
		                        if(!\Storage::disk('s3')->has($filename))
		                        {
		                            $message = Array('type' => 'error', 'message' => $font['font_filename_N'].' font not found');
		                            echo json_encode($message);
		                            exit;
		                        }
		                    }
		                }
		                else{

		                    if(!file_exists($font_filename)){

		                        $font_filename = public_path().'/'.$subdomain[0].'/backend/canvas/fonts/' . $fontMaster['font_filename_N'];

		                        if (!file_exists($font_filename)) {
		                            

		                            $message = Array('type' => 'error', 'message' => $fontMaster['font_filename_N'].' font not found');
		                            echo json_encode($message);
		                            exit;
		                        }
		                    }
		                }
		                $fonts_array[$key] = \TCPDF_FONTS::addTTFfont($font_filename, 'TrueTypeUnicode', '', false);
		            }
		        }
		        
		        
		        $printer_name = $systemConfig['printer_name'];
		        $timezone = $systemConfig['timezone'];

		        $style2D = array(
		            'border' => false,
		            'vpadding' => 0,
		            'hpadding' => 0,
		            'fgcolor' => array(0,0,0),
		            'bgcolor' => false, //array(255,255,255)
		            'module_width' => 1, // width of a single module in points
		            'module_height' => 1 // height of a single module in points
		        );
		        $style1Da = array(
		            'position' => '',
		            'align' => 'C',
		            'stretch' => false,
		            'fitwidth' => true,
		            'cellfitalign' => '',
		            'border' => false,
		            'hpadding' => 'auto',
		            'vpadding' => 'auto',
		            'fgcolor' => array(0,0,0),
		            'bgcolor' => false, //array(255,255,255),
		            'text' => true,
		            'font' => 'helvetica',
		            'fontsize' => 8,
		            'stretchtext' => 3
		        );
		        $style1D = array(
		            'position' => '',
		            'align' => 'C',
		            'stretch' => false,
		            'fitwidth' => true,
		            'cellfitalign' => '',
		            'border' => false,
		            'hpadding' => 'auto',
		            'vpadding' => 'auto',
		            'fgcolor' => array(0,0,0),
		            'bgcolor' => false, //array(255,255,255),
		            'text' => false,
		            'font' => 'helvetica',
		            'fontsize' => 8,
		            'stretchtext' => 4
		        );

		        $ghostImgArr = array();

		        $highestColumn = $sheet->getHighestColumn();
		        $highestRow = $sheet->getHighestRow();

		        $formula = [];
		        foreach ($sheet->getCellCollection() as $cellId) {
		            foreach ($cellId as $key1 => $value1) {
		                $checkFormula = $sheet->getCell($cellId)->isFormula();
		                if($checkFormula == 1){
		                    $formula[] = $cellId;       
		                }   
		            }  
		        };

		        if(!empty($formula)){
		            
		            $message = array('type'=> 'error', 'message' => 'Please remove formula from column','cell'=>$formula);
		            echo json_encode($message);
		            exit;
		        }
				if(isset($FID['bg_template_id']) && $FID['bg_template_id'] != '') {

		            if($FID['bg_template_id'] == 0) {
		                $bg_template_img_generate = '';
		                $bg_template_width_generate = $FID['template_width'];
		                $bg_template_height_generate = $FID['template_height'];
		            } else {
		                $get_bg_template_data =  BackgroundTemplateMaster::select('image_path','width','height')->where('id',$FID['bg_template_id'])->first();
		                if($get_file_aws_local_flag->file_aws_local == '1'){ 
		                    $bg_template_img_generate = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.bg_images').'/'. $get_bg_template_data['image_path'];
		                }
		                else{
		                    $bg_template_img_generate = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.bg_images').'/'. $get_bg_template_data['image_path'];
		                }
		               
		                $bg_template_width_generate = $get_bg_template_data['width'];
		                $bg_template_height_generate = $get_bg_template_data['height'];
		            }
		        } else {
		            $bg_template_img_generate = '';
		            $bg_template_width_generate = 210;
		            $bg_template_height_generate = 297;
		        }
		       // $tmp_path = public_path().'/backend/images/ghosttemp';
		        $tmp_path = public_path().'/backend/images/ghosttemp/temp';
		        
		        $tmpDir = app('App\Http\Controllers\admin\TemplateMasterController')->createTemp($tmp_path);

		        $log_serial_no = 1;
		        $sheet->setCellValue('i1','SeQR code');
		        $enrollImage = [];
		     
		        if(isset($request['excel_row'])){
		        	$excel_row = $request['excel_row'];
		        }else{
		        	$excel_row = 2;
		        }
		        for ($excel_row=$excel_row; $excel_row <= $highestRow; $excel_row++) { 
            
            		$rowData = $sheet->rangeToArray('A'.$excel_row.':'.$highestColumn.$excel_row,NULL,TRUE,FALSE);
            		$pdf = new TCPDF('P', 'mm', array($bg_template_width_generate, $bg_template_height_generate), true, 'UTF-8', false);

		            $pdf->SetCreator(PDF_CREATOR);
		            $pdf->SetAuthor('TCPDF');
		            $pdf->SetTitle('Certificate');
		            $pdf->SetSubject('');

		            // remove default header/footer
		            $pdf->setPrintHeader(false);
		            $pdf->setPrintFooter(false);
		            $pdf->SetAutoPageBreak(false, 0);
		            $pdf->SetCreator('SetCreator');

		            $pdf->AddPage();

		            if(isset($FID['bg_template_id']) && $FID['bg_template_id'] != ''){
		                $pdf->Image($bg_template_img_generate, 0, 0, $bg_template_width_generate, $bg_template_height_generate, "JPG", '', 'R', true);
		            }
		            $serial_no = $rowData[0][2];
		            if($get_file_aws_local_flag->file_aws_local == '1'){
		                if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
		                    $file_pointer_jpg = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.jpg';

		                    $file_pointer_png =\Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.png';
		                }
		                else{
		                    $file_pointer_jpg = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.jpg';

		                    $file_pointer_png = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.png';
		                }
		            }
		            else{
		                if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
		                    $file_pointer_jpg = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.jpg';

		                    $file_pointer_png =\Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.png';
		                }
		                else{
		                    $file_pointer_jpg = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.jpg';

		                    $file_pointer_png = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.png';
		                }
		            }
		           
		            $count = count($FID['mapped_name']);

		            for ($extra_fields=0; $extra_fields < $count; $extra_fields++) { 
                

		                if(isset($FID['security_type'][0]) || isset($FID['security_type'][1])){

		                    array_push($FID['security_type'], $FID['security_type'][0]);
		                    array_push($FID['security_type'], $FID['security_type'][1]);
		                    unset($FID['security_type'][0]);
		                    unset($FID['security_type'][1]);
		                    
		                }
		                $security_type = $FID['security_type'][$extra_fields + 2];
		                
		                if(isset($FID['x_pos'][0]) || isset($FID['x_pos'][1])){

		                    array_push($FID['x_pos'], $FID['x_pos'][0]);
		                    array_push($FID['x_pos'], $FID['x_pos'][1]);
		                    unset($FID['x_pos'][0]);
		                    unset($FID['x_pos'][1]);
		                    
		                }
		                $x = $FID['x_pos'][$extra_fields + 2];

		                if(isset($FID['y_pos'][0]) || isset($FID['y_pos'][1])){

		                    array_push($FID['y_pos'], $FID['y_pos'][0]);
		                    array_push($FID['y_pos'], $FID['y_pos'][1]);
		                    unset($FID['y_pos'][0]);
		                    unset($FID['y_pos'][1]);
		                    
		                }
		                $y = $FID['y_pos'][$extra_fields + 2];




		                

		                $print_serial_no = $this->nextPrintSerial();

		                if(isset($FID['field_position'][0]) || isset($FID['field_position'][1])){

		                    array_push($FID['field_position'], $FID['field_position'][0]);
		                    array_push($FID['field_position'], $FID['field_position'][1]);
		                    unset($FID['field_position'][0]);
		                    unset($FID['field_position'][1]);
		                    
		                }
		                $field_position = $FID['field_position'][$extra_fields + 2];

		                if(isset($FID['font_color'][0]) || isset($FID['font_color'][1])){

		                    array_push($FID['font_color'], $FID['font_color'][0]);
		                    array_push($FID['font_color'], $FID['font_color'][1]);
		                    unset($FID['font_color'][0]);
		                    unset($FID['font_color'][1]);
		                    
		                }                
		                $font_color_hex = $FID['font_color'][$extra_fields + 2];

		                
		                if($font_color_hex != ''){

		                    if($font_color_hex == "0"){

		                        $r = 0;
		                        $g = 0;
		                        $b = 0;

		                    }else{

		                        list($r,$g,$b)  = array($font_color_hex[0].$font_color_hex[1],
		                                        $font_color_hex[2].$font_color_hex[3],
		                                        $font_color_hex[4].$font_color_hex[5],
		                                );
		                        $r = hexdec($r);
		                        $g = hexdec($g);
		                        $b = hexdec($b);    
		                    }
		                };
		                

		                if(isset($fonts_array[$extra_fields + 2])){

		                    $font = $fonts_array[$extra_fields + 2];
		                }

		                if(isset($FID['font_size'][0]) || isset($FID['font_size'][1])){

		                    array_push($FID['font_size'], $FID['font_size'][0]);
		                    array_push($FID['font_size'], $FID['font_size'][1]);
		                    unset($FID['font_size'][0]);
		                    unset($FID['font_size'][1]);
		                    
		                }                
		                $font_size = $FID['font_size'][$extra_fields + 2];


		                if(isset($FID['font_style'][0]) || isset($FID['font_style'][1])){

		                    array_push($FID['font_style'], $FID['font_style'][0]);
		                    array_push($FID['font_style'], $FID['font_style'][1]);
		                    unset($FID['font_style'][0]);
		                    unset($FID['font_style'][1]);
		                    
		                }                
		                $font_style = $FID['font_style'][$extra_fields + 2];


		                if(isset($FID['width'][0]) || isset($FID['width'][1])){

		                    array_push($FID['width'], $FID['width'][0]);
		                    array_push($FID['width'], $FID['width'][1]);
		                    unset($FID['width'][0]);
		                    unset($FID['width'][1]);
		                    
		                }                
		                $width = $FID['width'][$extra_fields + 2];


		                if(isset($FID['height'][0]) || isset($FID['height'][1])){

		                    array_push($FID['height'], $FID['height'][0]);
		                    array_push($FID['height'], $FID['height'][1]);
		                    unset($FID['height'][0]);
		                    unset($FID['height'][1]);
		                    
		                }                
		                $height = $FID['height'][$extra_fields + 2];


		                $str = '';
		                if(isset($rowData[0][$extra_fields]))
		                    $str = $rowData[0][$extra_fields];
		                

		                if(isset($FID['text_justification'][0]) || isset($FID['text_justification'][1])){

		                    array_push($FID['text_justification'], $FID['text_justification'][0]);
		                    array_push($FID['text_justification'], $FID['text_justification'][1]);
		                    unset($FID['text_justification'][0]);
		                    unset($FID['text_justification'][1]);
		                    
		                }                
		                $text_align = $FID['text_justification'][$extra_fields + 2];

		                if($field_position == 3){
		                    $str = $rowData[0][1];
		                }else if($field_position == 4){
		                    
		                    $str = $rowData[0][2];
		                }else if($field_position == 5){
		                    
		                    $str = $rowData[0][3];
		                }else if($field_position == 6){
		                    
		                    $str = $rowData[0][4];
		                }else if($field_position == 7){
		                    $str = $rowData[0][5];
		                }else if($field_position == 8){
		                    
		                    $str = $rowData[0][6];
		                }else if($field_position == 9){
		                    
		                    $str = $rowData[0][7];
		                }else if($field_position == 10){
		                    $str = $rowData[0][8];

		                }else if($field_position == 12){
		                    $str = $rowData[0][11];

		                }
		               	$unique_no = $rowData[0][0];
		                switch ($security_type) {
		                    case 'QR Code':
		                        
		                        $dt = date("_ymdHis");
		                        $excl_column_row = $sheet->getCellByColumnAndRow(3,$excel_row);
		                        $str = $excl_column_row->getValue();

		                        $codeContents = strtoupper(md5($unique_no.$dt));
		                        
		                        $sheet->setCellValue('i'.$excel_row,$codeContents);
		                        $pngAbsoluteFilePath = "$tmpDir/$codeContents.png";

		                        QrCode::format('png')->size(200)->generate($codeContents,$pngAbsoluteFilePath);
		                        $QR = imagecreatefrompng($pngAbsoluteFilePath);

		                        $QR_width = imagesx($QR);
		                        $QR_height = imagesy($QR);

		                        $logo_qr_width = $QR_width/3;

		                        imagepng($QR,$pngAbsoluteFilePath);

		                        $pdf->SetAlpha(1);
		                        $pdf->Image($pngAbsoluteFilePath,$x,$y,19,19,"PNG",'','R',true);
		                        break;

		                    case 'ID Barcode':
		                        break;
		                    case 'Normal':

		                        if($FID['template_id'] == 6){

		                            if($field_position == 8){
		                                $cell = $sheet->getCellByColumnAndRow(9,$excel_row);

		                                $str = $cell->getValue();
		                                if (is_numeric($str) && \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($str)){

		                                    $val = $cell->getValue();
		                                    $xls_date = $val;
		         
		                                    $unix_date = ($xls_date - 25569) * 86400;
		                                     
		                                    $xls_date = 25569 + ($unix_date / 86400);
		                                    $unix_date = ($xls_date - 25569) * 86400;
		                                    $str =  date("d-m-Y", $unix_date);
		                                }
		                            }
		                            if($field_position == 10){
		                                $cell = $sheet->getCellByColumnAndRow(10,$excel_row);
		                               
		                                $str = $cell->getValue();
		                            }
		                        }else if($FID['template_id'] == 1||$FID['template_id'] == 2||$FID['template_id'] == 3||$FID['template_id'] == 4||$FID['template_id'] == 5){

		                            if($field_position == 10){  

		                                $cell = $sheet->getCellByColumnAndRow(9,$excel_row);

		                                $str = $cell->getValue();
		                                if (is_numeric($str) && \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($str)){

		                                    $val = $cell->getValue();
		                                    $xls_date = $val;
		         
		                                    $unix_date = ($xls_date - 25569) * 86400;
		                                     
		                                    $xls_date = 25569 + ($unix_date / 86400);
		                                    $unix_date = ($xls_date - 25569) * 86400;
		                                    $str =  date("d-m-Y", $unix_date);
		                                }
		                            }

		                             if($field_position == 12){
		                                $cell = $sheet->getCellByColumnAndRow(10,$excel_row);
		                               
		                                $str = $cell->getValue();
		                            }
		                        }else{

		                            if($field_position == 10){

		                                $cell = $sheet->getCellByColumnAndRow(9,$excel_row);

		                                $str = $cell->getValue();
		                                if (is_numeric($str) && \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($str)){

		                                    $val = $cell->getValue();
		                                    $xls_date = $val;
		         
		                                    $unix_date = ($xls_date - 25569) * 86400;
		                                     
		                                    $xls_date = 25569 + ($unix_date / 86400);
		                                    $unix_date = ($xls_date - 25569) * 86400;
		                                    $str =  date("d-m-Y", $unix_date);
		                                }
		                            }
		                        }

		                        $pdf->SetAlpha(1);
		                        $pdf->SetTextColor($r,$g,$b);
		                        $pdf->SetFont($font,$font_style,$font_size,'',false);
		                        $pdf->SetXY($x,$y);
		                        $pdf->Cell($width,$height,$str,0,false,$text_align);
		                        break;
		                    
		                    case 'Dynamic Image':

		                        $pdf->SetAlpha(1);
		                        $excel_column_row = $sheet->getCellByColumnAndRow(3,$excel_row);
		                        $enrollValue = $excel_column_row->getValue();

		                        $serial_no = trim($serial_no);
		                        if($get_file_aws_local_flag->file_aws_local == '1'){
		                            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
		                                $image_jpg = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.jpg';

		                                $image_png =\Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.png';
		                            }
		                            else{
		                                $image_jpg = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.jpg';

		                                $image_png = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.png';
		                            }
		                        }
		                        else{
		                            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
		                                $image_jpg = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.jpg';

		                                $image_png =\Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.png';
		                            }
		                            else{
		                                $image_jpg = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.jpg';

		                                $image_png = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.png';
		                            }
		                        }

		
		                        $exists = $this->check_file_exist($image_jpg);
						
								if($exists){
								    $pdf->image($image_jpg,$x,$y,$width / 3,$height / 3,"","",'L',true,3600);
								} else {

									$exists = $this->check_file_exist($image_png);
									if($exists){
									   $pdf->image($image_png,$x,$y,$width / 3,$height / 3,"","",'L',true,3600);
									} 
								}
								
								if ($template_name == 'BronzeCertification-SHE') {
									$exl_column_row = $sheet->getCellByColumnAndRow(10,$excel_row);
									$bronze_color = $exl_column_row->getValue();
									
									if ($bronze_color == 'Blue' || $bronze_color == 'BLUE') {
										$pdf_image = 'blue_bronze.jpg';

									}else if($bronze_color == 'Red' || $bronze_color == 'RED'){
										
										$pdf_image = 'red_bronze.jpg';
									}else if($bronze_color == 'Green' || $bronze_color == 'GREEN'){
										
										$pdf_image = 'green_bronze.jpg';
									}
									//$bronze_image = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$pdf_image;
									$bronze_image = \Config::get('constant.local_base_path').$subdomain[0].'/backend/canvas/images/'.$pdf_image;

									$pdf->image($bronze_image, 5.5, 42,160 / 3,35 / 3, "", '', 'L', true, 3600);
								}
		                        break;
		                    default:
		                        # code...
		                        break;
		                }
		            }

		            //if($subdomain[0]=='test'){
		            	CoreHelper::rrmdir($tmpDir);
		            //}

		            $unique_no = $rowData[0][0];//Overwrite enrolment serial no to unique no
	            	$withoutExt = preg_replace('/\\.[^.\\s]{3,4}$/', '', $excelfile);
		            $admin_id = \Auth::guard('admin')->user()->toArray();
		            $template_name  = $FID['template_name'];
		            $certName = str_replace("/", "_", $serial_no) .".pdf";
		            
		            $myPath = public_path().'/backend/temp_pdf_file';
		            $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');
		            $student_table_id = $this->addCertificate($serial_no, $certName, $dt,$FID['template_id'],$admin_id,$unique_no);
		            
		            
		            
		            $username = $admin_id['username'];
		            date_default_timezone_set('Asia/Kolkata');

		            $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
		            $date = date('Y-m-d H:i:s').PHP_EOL;
		            

		            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
		                $file_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id;
		            }
		            else{
		                $file_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id;
		            }
		            $fp = fopen($file_path.'/'.$withoutExt.".txt","a");
		            fwrite($fp,$content);
		            fwrite($fp,$date);

		            $print_datetime = date("Y-m-d H:i:s");
		            $print_count = $this->getPrintCount($unique_no);
		            $printer_name = /*'HP 1020';*/$printer_name;
		            $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,$template_name,$admin_id,$student_table_id,$unique_no);
		            $log_serial_no++;

		            $excel_column_row = $sheet->getCellByColumnAndRow(2, $excel_row);
		            $enroll_value = $excel_column_row->getValue();
		            
		            $pdf_file_directory = public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName;
		            if(file_exists($pdf_file_directory)){

		            	\Session::push('single_pdf_file', $certName);
		            	
		            }
		           
		            $new_excel_row = (int)$excel_row + 1;
		            $progressbar_array = ['excel_row'=>$new_excel_row,'is_progress'=>'yes','highestRow'=>$highestRow,'msg'=>'','success'=>true];
            		return $progressbar_array;
				}
				$single_pdf_file = \Session::get('single_pdf_file');
				
				$array_filter = array_filter($single_pdf_file);
				$array_unique = array_unique($array_filter);
				

				$zip = new \ZipArchive;
        		$save_path = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id;
        		$zip_file_name = $template_name."_".date('ymdhis').'.zip';
        		
        		if ($zip->open(public_path().$save_path.'/'.$zip_file_name,\ZipArchive::CREATE) === TRUE) {
        			
        			foreach ($array_unique as $key => $value) {
        				

		                	$zip->addfile(public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$value,$value);
        				
		            }
		            $zip->close();
        		}

        		
        		\Session::forget('single_pdf_file');
        		$path = 'https://'.$subdomain[0].'.seqrdoc.com/';
                $msg = "<b>Click <a href='".$path.$save_path."/".$zip_file_name."' class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
        		$progressbar_array_last = ['excel_row'=>$excel_row,'is_progress'=>'no','highestRow'=>$highestRow,'msg'=>$msg];
            	return $progressbar_array_last;
			}
		}
	}
	public function check_file_exist($url){
	    $handle = @fopen($url, 'r');
	    if(!$handle){
	        return false;
	    }else{
	        return true;
	    }
	}
	public function addCertificate($serial_no, $certName, $dt,$template_id,$admin_id,$unique_no)
    {
    	$domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $file1 = public_path().'/backend/temp_pdf_file/'.$certName;
        

        $file2 =public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName;
       

       	if($subdomain[0]=='tpsdi'){
       		$source=\Config::get('constant.directoryPathBackward')."\\backend\\temp_pdf_file\\".$certName;//$file1
            $output=\Config::get('constant.directoryPathBackward').$subdomain[0]."\\backend\\pdf_file\\".$certName;
            CoreHelper::compressPdfFile($source,$output);
       	}else{
        	copy($file1, $file2);
    	}
        @unlink($file1);

        $sts = 1;
        $datetime  = date("Y-m-d H:i:s");
        $ses_id  = $admin_id["id"];
        $certName = str_replace("/", "_", $certName);

        $get_config_data = Config::select('configuration')->first();
     
        $c = explode(", ", $get_config_data['configuration']);
        $key = "";


        $tempDir = public_path().'/backend/qr';
        $key = strtoupper(md5($unique_no.$dt)); 
        
        $codeContents = $key;
        $fileName = $key.'.png'; 
        
        $urlRelativeFilePath = 'qr/'.$fileName; 
        $auth_site_id=\Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();
        // Mark all previous records of same serial no to inactive if any
        if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
            $resultu = SbStudentTable::where('serial_no',$unique_no)->update(['status'=>'0']);
            // Insert the new record

            $result = SbStudentTable::create(['serial_no'=>$unique_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        }
        else{
            $resultu = StudentTable::where('serial_no',$unique_no)->update(['status'=>'0']);
            // Insert the new record

            $result = StudentTable::create(['serial_no'=>$unique_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        }

        return $result['id'];
    }
    public function getPrintCount($serial_no)
    {
        $numCount = PrintingDetail::select('id')->where('sr_no',$serial_no)->count();
        return $numCount + 1;
    }
    public function addPrintDetails($username, $print_datetime, $printer_name, $printer_count, $print_serial_no, $sr_no,$template_name,$admin_id,$student_table_id,$unique_no)
    {
        $sts = 1;
        $datetime = date("Y-m-d H:i:s");
        $ses_id = $admin_id["id"];
        $auth_site_id=\Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();
        if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
            // Insert the new record
            $result = SbPrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$unique_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);
        }
        else{
            // Insert the new record
            $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$unique_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1,'student_table_id'=>$student_table_id]);
        }
    }
    public function nextPrintSerial()
    {
        $current_year = 'PN/' . $this->getFinancialyear() . '/';
        // find max
        $maxNum = 0;

        $result = \DB::select("SELECT COALESCE(MAX(CONVERT(SUBSTR(print_serial_no, 10), UNSIGNED)), 0) AS next_num "
            . "FROM printing_details WHERE SUBSTR(print_serial_no, 1, 9) = '$current_year'");
        
        //get next num
        $maxNum = $result[0]->next_num + 1;

        return $current_year . $maxNum;
    }
    public function getFinancialyear()
    {
        $yy = date('y');
        $mm = date('m');
        $fy = str_pad($yy, 2, "0", STR_PAD_LEFT);
        if($mm > 3)
            $fy = $fy . "-" . ($yy + 1);
        else
            $fy = str_pad($yy - 1, 2, "0", STR_PAD_LEFT) . "-" . $fy;
        return $fy;
    }
}


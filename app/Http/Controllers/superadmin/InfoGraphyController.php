<?php

namespace App\Http\Controllers\superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Site;
use App\models\TemplateMaster;
use App\models\SiteDocuments;
use App\models\Demo\SitesSuperdata;
use DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MonthlyConsumptionDataExport;

class InfoGraphyController extends Controller
{
    //
    public function index(){

    	
        $data = SiteDocuments::get()->toArray();
       //print_r($data);
       //exit;
        $result = [];
        foreach ($data as $key => $value) {
            
            $explode = explode('.', $value['sites_name'])[0];
            $result[$explode] = $value['avg_per'];
        }

    	return view('superadmin.infography.infography',compact('result'));
    }

    public function monthlyConsumption(){

        
        $data = SiteDocuments::orderBy('sites_name', 'ASC')->get()->toArray();
       //print_r($data);
        $sites = [];
        foreach ($data as $key => $value) {
            
            $explode = explode('.', $value['sites_name'])[0];
            $sites[$value['site_id']] = $explode;
        }

       
        return view('superadmin.infography.monthlyconsumption',compact('sites'));
    }


    public function getMonthlyConsumptionData(Request $request){

        $year=$request->yearFilter;
        $month=$request->monthFilter;
        $data = SiteDocuments::orderBy('sites_name', 'ASC')->get()->toArray();

        $totalActive=0;
        $totalInActive=0;
        $result = [];
        foreach ($data as $key => $value) {
            /*print_r($value);
            echo '</br>';*/
        $instance = explode('.', $value['sites_name'])[0];
        if($instance == 'demo')
        {
            $dbName = 'seqr_'.$instance;
        }
        else{
            $dbName = 'seqr_d_'.$instance;
        }

        if(!empty($year)&&!empty($month)){
            $active=DB::connection($mysql2)->table($dbName.".student_table")->where('status','1')->whereYear('created_at', '=', $year)
              ->whereMonth('created_at', '=', $month)->where('status','1')->count();
            $in_active=DB::connection($mysql2)->table($dbName.".student_table")->where('status','0')->whereYear('created_at', '=', $year)
              ->whereMonth('created_at', '=', $month)->count();
        
        }else{
            $active=DB::connection($mysql2)->table($dbName.".student_table")->where('status','1')->count();
            $in_active=DB::connection($mysql2)->table($dbName.".student_table")->where('status','0')->count();
        }

        $totalActive=$totalActive+$active;
        $totalInActive=$totalInActive+$in_active;
        
       // $siteData=DB::select(DB::raw('SELECT site_id FROM `'.$dbName.'`.`sites` WHERE site_url ="'.$domainDest.'"'));
         //   $site_id=$siteData[0]->site_id;

           if(!empty($active)||!empty($in_active)){
            $result[] = array($instance,$active,''.$active,$in_active,''.$in_active);
                }
        }

        $this->array_sort_by_column($result,1,SORT_DESC);
        //print_r($result);
        return response()->json(['success'=>true,'msg'=>'success','data'=>$result,'projectCount'=>count($result),'totalActive'=>$totalActive,'totalInActive'=>$totalInActive]);
    }


     public function monthlyConsumptionDataExport(Request $request){

        $year=$request->yearFilter;
        $month=$request->monthFilter;

        /*print_r($year);
        print_r($month);
        exit;*/
        $data = SiteDocuments::orderBy('sites_name', 'ASC')->get()->toArray();

        $totalActive=0;
        $totalInActive=0;
        $result = [];
        foreach ($data as $key => $value) {
            /*print_r($value);
            echo '</br>';*/
        $instance = explode('.', $value['sites_name'])[0];
        if($instance == 'demo')
        {
            $dbName = 'seqr_'.$instance;
        }
        else{
            $dbName = 'seqr_d_'.$instance;
        }

        if(!empty($year)&&!empty($month)){
            $active=DB::connection($mysql2)->table($dbName.".student_table")->where('status','1')->whereYear('created_at', '=', $year)
              ->whereMonth('created_at', '=', $month)->where('status','1')->count();


            $in_active=DB::connection($mysql2)->table($dbName.".student_table")->where('status','0')->whereYear('created_at', '=', $year)
              ->whereMonth('created_at', '=', $month)->count();
        
        }else{
            $active=DB::connection($mysql2)->table($dbName.".student_table")->where('status','1')->count();
            $in_active=DB::connection($mysql2)->table($dbName.".student_table")->where('status','0')->count();
        }

        $totalActive=$totalActive+$active;
        $totalInActive=$totalInActive+$in_active;
        
       // $siteData=DB::select(DB::raw('SELECT site_id FROM `'.$dbName.'`.`sites` WHERE site_url ="'.$domainDest.'"'));
         //   $site_id=$siteData[0]->site_id;

           if(!empty($active)||!empty($in_active)){
            $result[] = array("instance"=>$instance,"active_documents"=>$active,"inactive_documents"=>$in_active);
                }
        }

        $this->array_sort_by_column($result,"active_documents",SORT_DESC);
       // print_r($result);
       
    //exit;

        $sheet_name = 'MonthlyConsumptionData_'. date('Y_m_d_H_i_s').'.xlsx'; 
        
        return Excel::download(new MonthlyConsumptionDataExport($result),$sheet_name,'Xlsx');

        //return response()->json(['success'=>true,'msg'=>'success','data'=>$result,'projectCount'=>count($result),'totalActive'=>$totalActive,'totalInActive'=>$totalInActive]);
    }


    private function array_sort_by_column(&$arr, $col, $dir = SORT_ASC) {
    $sort_col = array();
    foreach ($arr as $key => $row) {
        $sort_col[$key] = $row[$col];
    }

    array_multisort($sort_col, $dir, $arr);
    }


    public function getDetailData(Request $request)
    {
        
        if($request->ajax()){

            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and ( template_name like \"%{$search}%\""
                . ")";
            }  
           $instance=$request->get('instance');

           $year=$request->get('yearFilter');
        $month=$request->get('monthFilter');
        $subdomain = explode('.', $instance);

       
       if($subdomain[0] == 'demo')
        {
            $dbName = 'seqr_'.$subdomain[0];
        }
        else{
            $dbName = 'seqr_d_'.$subdomain[0];
        }
                     
             //for serial number
            DB::statement(DB::raw('set @rownum=0'));   
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'template_name','active_documents','inactive_documents'];
            
            /******************************** Template Maker ***********************************************************/
            if($subdomain[0]=="demo"){
            $selectColumns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'template_name',DB::raw('@active_documents  :=(select count(*) from '.$dbName.'.student_table where template_master.id=student_table.template_id AND status="1" AND MONTH(created_at)="'.$month.'" AND YEAR(created_at)="'.$year.' AND template_type=0") as active_documents'),DB::raw('@inactive_documents  :=(select count(*) from '.$dbName.'.student_table where template_master.id=student_table.template_id AND status="0" AND MONTH(created_at)="'.$month.'" AND YEAR(created_at)="'.$year.'  AND template_type=0") as inactive_documents'),DB::raw("'Template Maker' as template_type")];
           }else{
             $selectColumns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'template_name',DB::raw('@active_documents  :=(select count(*) from '.$dbName.'.student_table where template_master.id=student_table.template_id AND status="1" AND MONTH(created_at)="'.$month.'" AND YEAR(created_at)="'.$year.'") as active_documents'),DB::raw('@inactive_documents  :=(select count(*) from '.$dbName.'.student_table where template_master.id=student_table.template_id AND status="0" AND MONTH(created_at)="'.$month.'" AND YEAR(created_at)="'.$year.'") as inactive_documents'),DB::raw("'Template Maker' as template_type")];
           }
     
          if($subdomain[0]=="demo"){
            $template_maker_list = DB::table($dbName.'.template_master as template_master')->select($selectColumns)
                                                    ->join($dbName.'.student_table as s', 's.template_id', '=', $dbName.'.template_master.id')
                                                    ->where('s.template_type', 0)
                                                    ->whereRaw($where_str, $where_params)
                                                    ->havingRaw('active_documents > 0 OR inactive_documents > 0')
                                                    ->groupBy($dbName.'.template_master.id',$dbName.'.template_master.template_name');
            }else{
                 $template_maker_list = DB::table($dbName.'.template_master as template_master')->select($selectColumns)
                                                    ->join($dbName.'.student_table as s', 's.template_id', '=', $dbName.'.template_master.id')
                                                    ->where('s.template_type', 0)
                                                    ->whereRaw($where_str, $where_params)
                                                    ->havingRaw('active_documents > 0 OR inactive_documents > 0')
                                                    ->groupBy($dbName.'.template_master.id',$dbName.'.template_master.template_name');
            }
            
            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $template_maker_list = $template_maker_list->take($request->input('iDisplayLength'))
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
                    $template_maker_list = $template_maker_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            } 
            $template_maker_list = $template_maker_list->get();
            $template_maker_list_array=$template_maker_list->toArray();
           /*************************************************************************************/
           
            /************************ PDF2PDF Data ****************************************/

            $table = "uploaded_pdfs";
            $query = "SELECT COUNT(*) as result FROM INFORMATION_SCHEMA.tables WHERE  table_schema = '".$dbName."' AND table_name = '".$table."'";
            $resp = DB::select($query);
           /* print_r($resp);*/
            if ($resp&&!empty($resp[0]->result)) {
               // echo "abc";
             if($subdomain[0]=="demo"){
                $selectColumns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'template_name',DB::raw('@active_documents  :=(select count(*) from '.$dbName.'.student_table where uploaded_pdfs.id=student_table.template_id AND status="1" AND MONTH(created_at)="'.$month.'" AND YEAR(created_at)="'.$year.' AND template_type=1") as active_documents'),DB::raw('@inactive_documents  :=(select count(*) from '.$dbName.'.student_table where uploaded_pdfs.id=student_table.template_id AND status="0" AND MONTH(created_at)="'.$month.'" AND YEAR(created_at)="'.$year.'  AND template_type=1") as inactive_documents'),DB::raw("'PDF2PDF' as template_type")];
             }else{
             $selectColumns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'template_name',DB::raw('@active_documents  :=(select count(*) from '.$dbName.'.student_table where uploaded_pdfs.id=student_table.template_id AND status="1" AND MONTH(created_at)="'.$month.'" AND YEAR(created_at)="'.$year.'") as active_documents'),DB::raw('@inactive_documents  :=(select count(*) from '.$dbName.'.student_table where uploaded_pdfs.id=student_table.template_id AND status="0" AND MONTH(created_at)="'.$month.'" AND YEAR(created_at)="'.$year.'") as inactive_documents'),DB::raw("'PDF2PDF' as template_type")];
             }     
          
            if($subdomain[0]=="demo"){
                $pdf2pdf_list = DB::table($dbName.'.uploaded_pdfs as uploaded_pdfs')->select($selectColumns)
                                                    ->join($dbName.'.student_table as s', 's.template_id', '=', $dbName.'.uploaded_pdfs.id')
                                                    ->where('s.template_type', 1)
                                                    ->whereRaw($where_str, $where_params)
                                                    ->havingRaw('active_documents > 0 OR inactive_documents > 0')
                                                    ->groupBy($dbName.'.uploaded_pdfs.id',$dbName.'.uploaded_pdfs.template_name');
            }else{
                 $pdf2pdf_list = DB::table($dbName.'.uploaded_pdfs as uploaded_pdfs')->select($selectColumns)
                                                    ->join($dbName.'.student_table as s', 's.template_id', '=', $dbName.'.uploaded_pdfs.id')
                                                    ->where('s.template_type', 1)
                                                    ->whereRaw($where_str, $where_params)
                                                    ->havingRaw('active_documents > 0 OR inactive_documents > 0')
                                                    ->groupBy($dbName.'.uploaded_pdfs.id',$dbName.'.uploaded_pdfs.template_name');
            }
            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $pdf2pdf_list = $pdf2pdf_list->take($request->input('iDisplayLength'))
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
                    $pdf2pdf_list = $pdf2pdf_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            } 
            $pdf2pdf_list = $pdf2pdf_list->get();
            $pdf2pdf_list_array=$pdf2pdf_list->toArray();

           // print_r($pdf2pdf_list_array);
           
           }else{
            $pdf2pdf_list_array=array();
           }
            /****************************************************************/


            /************************Custom Templates****************************************/
            if($subdomain[0]=="demo"){
            $query = "SELECT COUNT(*) as activeTemplates FROM ".$dbName.".student_table  WHERE  template_type = '2' AND status = '1' AND MONTH(created_at)='".$month."' AND YEAR(created_at)='".$year."'";
            $resp = DB::select($query);
            $activeTemplates=$resp[0]->activeTemplates;

            $query = "SELECT COUNT(*) as inactiveTemplates FROM ".$dbName.".student_table  WHERE  template_type = '2' AND status = '0' AND MONTH(created_at)='".$month."' AND YEAR(created_at)='".$year."'";
            $resp = DB::select($query);
            $inactiveTemplates=$resp[0]->inactiveTemplates;
            }else{
            $query = "SELECT COUNT(*) as activeTemplates FROM ".$dbName.".student_table  WHERE  template_type = '2' AND template_id = 100 AND status = '1' AND MONTH(created_at)='".$month."' AND YEAR(created_at)='".$year."'";
            $resp = DB::select($query);
           // print_r($resp);
            $activeTemplates=$resp[0]->activeTemplates;

            $query = "SELECT COUNT(*) as inactiveTemplates FROM ".$dbName.".student_table  WHERE  template_type = '2' AND  template_id = 100 AND status = '0' AND MONTH(created_at)='".$month."' AND YEAR(created_at)='".$year."'";
            $resp = DB::select($query);
            $inactiveTemplates=$resp[0]->inactiveTemplates;
            }
            if(!empty($activeTemplates)||!empty($inactiveTemplates)){
                //echo "aa";
                $custom_template_list_array[]=array("rownum"=>"1","template_name"=>"Custom Templates","active_documents"=>$activeTemplates,"inactive_documents"=>$inactiveTemplates,"template_type"=>"Custom Template");
            }else{
                $custom_template_list_array=array();
            }
            /******************************************************************************/

            $template_maker_count=(int)count($template_maker_list_array);

            $pdf2pdf_list_count=(int)count($pdf2pdf_list_array);
            
            $custom_template_list_count=(int)count($custom_template_list_array);

            $template_master_array=$template_maker_list_array;

            if($pdf2pdf_list_count>0){

            $template_master_array=array_merge($template_master_array, $pdf2pdf_list_array);
            }

            if($custom_template_list_count>0){
                
            $template_master_array=array_merge($template_master_array, $custom_template_list_array);
            }

            $template_master_count=$template_maker_count+$pdf2pdf_list_count+$custom_template_list_count;
            $response['iTotalDisplayRecords'] = $template_master_count;
            $response['iTotalRecords'] = $template_master_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $template_master_array;
            

            
            return $response;
        }
     //   return view('admin.adminManagement.index');
    }

    public function consumptionComparison(){

        
        $data = SiteDocuments::orderBy('sites_name', 'ASC')->get()->toArray();
       //print_r($data);
        $sites = [];
        foreach ($data as $key => $value) {
            
            $explode = explode('.', $value['sites_name'])[0];
            $sites[$value['site_id']] = $explode;
        }

       
        return view('superadmin.infography.consumptioncomparison',compact('sites'));
    }

     public function getComparisonData(Request $request)
    {
        
        if($request->ajax()){
        	$instance1=($request->get('cmp1Filter')=='false')?'':$request->get('cmp1Filter');
        	$instance2=($request->get('cmp2Filter')=='false')?'':$request->get('cmp2Filter');
        	$instance3=($request->get('cmp3Filter')=='false')?'':$request->get('cmp3Filter');

        	 $from=($request->get('filterFrom')=='false')?'':$request->get('filterFrom');
        	 $to=($request->get('filterTo')=='false')?'':$request->get('filterTo');

        	$isLifetime=$request->get('isLifetime');
	        if(!empty($instance1)||!empty($instance2)||!empty($instance3)){

	        if($isLifetime==1){
	        $dateValid=true;
	        }elseif(($isLifetime==0&&!empty($from)&&!empty($to))){
	              // echo $from;
	        	 $from = date("m-Y",strtotime($from.'-01'));
				 $to = date("m-Y",strtotime($to.'-01'));
                // exit;
				if(strtotime('01-'.$from)<=strtotime('01-'.$to)){
					$dateValid=true;
				}else{
					$dateValid=false;
					$errorMessage="From month year should be less than To month year";
				}
	        }else{
	        	$dateValid=false;
	        	$errorMessage="Select valid From & To month year";
	        }

	        if($dateValid){
	        	
	        	$instances=array();
	        	if(!empty($instance1)){
	        		array_push($instances, $instance1);
	        	}
	        	if(!empty($instance2)){
	        		array_push($instances, $instance2);
	        	}
	        	if(!empty($instance3)){
	        		array_push($instances, $instance3);
	        	}

	        	$newArr=array_unique($instances);

	        	if(count($instances)==count($newArr)){

                    

	        	$result = array();
		       	$monthArr=array();
		       	if($isLifetime!=1&&!empty($from)&&!empty($to)){
		       		if($from==$to){
		       			$currMonth=$from;
		       			
		       			do{
		       			 	//$monthName=str_replace('_', '-', $currMonth);
		       			 	$varArr=explode('-', $currMonth);
		       				$monthArr[]=array("month"=>$varArr[0],"year"=>$varArr[1],"titleX"=>date('M',strtotime('01-'.$currMonth)).'-'.$varArr[1]);
		       			 	$currMonth=date('m-Y',strtotime('+1 Month', strtotime('01-'.$currMonth)));
		       			}while(strtotime('01-'.$currMonth)<=strtotime('01-'.$to));
		       		}else{
		       			 $currMonth=$from;
		       			
		       			do{
		       			 	//$monthName=str_replace('_', '-', $currMonth);
		       			 	$varArr=explode('-', $currMonth);
		       				$monthArr[]=array("month"=>$varArr[0],"year"=>$varArr[1],"titleX"=>date('M',strtotime('01-'.$currMonth)).'-'.$varArr[1]);
		       			 	$currMonth=date('m-Y',strtotime('+1 Month', strtotime('01-'.$currMonth)));
		       			}while(strtotime('01-'.$currMonth)<=strtotime('01-'.$to));
		       		}
		       	}else{

                    $fromLifetime=date('Y-m-d');
                    $toLifetime="2017-01-01";
                    foreach ($instances as $instance) {

                        if($instance == 'demo')
                        {
                            $dbName = 'seqr_'.strtolower($instance);
                        }
                        else{
                            $dbName = 'seqr_d_'.strtolower($instance);
                        }
                        $query = "SELECT created_at FROM ".$dbName.".student_table WHERE created_at IS NOT NULL ORDER BY created_at ASC LIMIT 1";
                        $resp = DB::select($query);
                        if($resp&&isset($resp[0]->created_at)&&!empty($resp[0]->created_at)){

                            if(strtotime($fromLifetime)>strtotime($resp[0]->created_at)){

                            $fromLifetime=$resp[0]->created_at;
                            }
                        }
                         
                        $query = "SELECT created_at FROM ".$dbName.".student_table WHERE created_at IS NOT NULL ORDER BY created_at DESC LIMIT 1";
                        $resp = DB::select($query);
                        if($resp&&isset($resp[0]->created_at)&&!empty($resp[0]->created_at)){

                            if(strtotime($toLifetime)<strtotime($resp[0]->created_at)){

                            $toLifetime=$resp[0]->created_at;
                            }
                        }                       
                    }

                    if($toLifetime=="2017-01-01"){
                        $toLifetime=date('Y-m-d');
                    }

                    $from=date('m-Y',strtotime($fromLifetime));
		       		//$from='01-2018';
		       		$currMonth=$from;
		       		//$to=date('m-Y');
                    $to=date('m-Y',strtotime($toLifetime));
		       			do{
		       			 	//$monthName=str_replace('_', '-', $currMonth);
		       			 	$varArr=explode('-', $currMonth);
		       				$monthArr[]=array("month"=>$varArr[0],"year"=>$varArr[1],"titleX"=>date('M',strtotime('01-'.$currMonth)).'-'.$varArr[1]);
		       			 	$currMonth=date('m-Y',strtotime('+1 Month', strtotime('01-'.$currMonth)));
		       			}while(strtotime('01-'.$currMonth)<=strtotime('01-'.$to));
		       	}

		       //	print_r($monthArr);
		       //	exit;

		       	foreach ($monthArr as $readMonth) {
		       		# code...

		       		$rowArr=array($readMonth['titleX']);
		       		foreach ($instances as $instance) {
		       			//print_r($readInstance);
				        //$instance = explode('.', $readInstance);
				        if($instance == 'demo')
				        {
				            $dbName = 'seqr_'.strtolower($instance);
				        }
				        else{
				            $dbName = 'seqr_d_'.strtolower($instance);
				        }

				        $totalGenerated=DB::connection($mysql2)->table($dbName.".student_table")
				        										->whereYear('created_at', '=', $readMonth['year'])
				              									->whereMonth('created_at', '=', $readMonth['month'])->count();
				        array_push($rowArr, $totalGenerated);
				        array_push($rowArr, ''.$totalGenerated);
				       
			        }

			        $result[]=$rowArr;

		       	}
		       

		       // $this->array_sort_by_column($result,1,SORT_DESC);
		        //print_r($result);
		        return response()->json(['success'=>true,'msg'=>'success','data'=>$result,'instances'=>$instances]);
		        }else{
		    	echo json_encode(array("success"=>false,"msg"=>'Please select unique instances.'));
		    	}
		        }else{
		    	echo json_encode(array("success"=>false,"msg"=>$errorMessage));
		    	}
	        }else{
	    	echo json_encode(array("success"=>false,"msg"=>"Please select instance"));
	    	}
        }

    }


}

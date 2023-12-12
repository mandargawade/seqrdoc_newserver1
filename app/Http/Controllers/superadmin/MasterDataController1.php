<?php

namespace App\Http\Controllers\superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\SiteDocuments;
use DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MasterDataExport;

class MasterDataController extends Controller
{
    //

      public function index(Request $request){
        
       if($request->ajax()){

            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and ( sites_name like \"%{$search}%\""
               
                . ")";
            }  
           
                                                    
             //for serial number
            DB::statement(DB::raw('set @rownum=0'));   

            $columns = ['id','sites_name','template_number','active_documents','inactive_documents','total_verifier','total_scanned','last_genration_date'];

            $columnsQuery = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),DB::raw('@sites_name :=SUBSTRING_INDEX(sites_superdata.sites_name, ".", 1) AS sites_name'),DB::raw('(template_number+inactive_template_number+custom_templates+pdf2pdf_active_templates+pdf2pdf_inactive_templates) AS template_number'),'active_documents','inactive_documents','total_verifier','total_scanned','last_genration_date'];

            $font_master_count = SiteDocuments::select($columnsQuery)
                ->whereRaw($where_str, $where_params)
                ->count();
  
            $fontMaster_list = SiteDocuments::select($columnsQuery)
                 ->whereRaw($where_str, $where_params);
      
            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $fontMaster_list = $fontMaster_list->take($request->input('iDisplayLength'))
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
                    $fontMaster_list = $fontMaster_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            } 
            $fontMaster_list = $fontMaster_list->get();
             
            $response['iTotalDisplayRecords'] = $font_master_count;
            $response['iTotalRecords'] = $font_master_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $fontMaster_list->toArray();
            

            
            return $response;
        }
        return view('superadmin.masterdata.master_data');
    }

     public function excelExport()
    {
        $sheet_name = 'MasterData_'. date('Y_m_d_H_i_s').'.xls'; 
        
        return Excel::download(new MasterDataExport(),$sheet_name);
    }
   
}

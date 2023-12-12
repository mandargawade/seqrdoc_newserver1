<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\Pdf2pdfUploadHistory;
use Auth;
use App\models\SystemConfig;


//show printerinformation
class Pdf2pdfReportController extends Controller
{
    public function index(Request $request)
    {
        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

    	if($request->ajax())
        {
            $data = $request->all();
            $where_str = '1 = ?';
            $where_params = [1];
       
            $table_name = 'file_records';
           
            if (!empty($request->input('sSearch')))
            {
                $search = $request->get('sSearch');
                $where_str .= " and ( ".$table_name.".template_name like \"%{$search}%\""
                                /*. " or ".$table_name.".userid like \"%{$search}%\"" */
                                . " or ".$table_name.".source_file like \"%{$search}%\""
                                . " or ".$table_name.".pdf_page like \"%{$search}%\""
                                . " or ".$table_name.".created_at like \"%{$search}%\""
                                . " or ".$table_name.".total_records like \"%{$search}%\""
                               . ")";
            }
 
            $auth_site_id=Auth::guard('admin')->user()->site_id;
            $columns = ['id','template_name', 'record_unique_id', 'total_records', 'source_file', 'pdf_page', 'userid', 'created_at'];
			$printingReport = Pdf2pdfUploadHistory::select($columns)
								->whereRaw($where_str, $where_params);
							

			$printingReport_count = Pdf2pdfUploadHistory::select('id')
									->whereRaw($where_str, $where_params)
									->count();
            
            if($request->has('iDisplayStart') && $request->get('iDisplayLength') !='-1'){
                $printingReport = $printingReport->take($request->get('iDisplayLength'))->skip($request->get('iDisplayStart'));
            }

            if($request->has('iSortCol_0')){
                for ( $i = 0; $i < $request->get('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->get('iSortCol_' . $i)];
                    if (false !== ($index = strpos($column, ' as '))) {
                        $column = substr($column, 0, $index);
                    }
                    $printingReport = $printingReport->orderBy($column,$request->get('sSortDir_'.$i));
                }
            }

            $printingReport = $printingReport->get();
            //foreach ($printingReport as $key => $value) {
               //$value['created_on'] =  date("d M Y h:i A", strtotime($value['created_at']));
            //}
			
            $response['iTotalDisplayRecords'] = $printingReport_count;
            $response['iTotalRecords'] = $printingReport_count;
            $response['sEcho'] = intval($request->get('sEcho'));
            $response['aaData'] = $printingReport;
            
            return $response;
        }
    	return view('admin.pdf2pdfReport.index');
    }
}

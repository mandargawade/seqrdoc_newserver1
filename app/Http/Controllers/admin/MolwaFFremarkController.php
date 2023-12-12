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
use App\Imports\TemplateMapImport
;use App\Imports\TemplateMasterImport;
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
use App\models\FreedomFighterList;
use App\models\FFMolwaRemarkLog;
use App\models\Admin;

use App\Helpers\CoreHelper;
use Helper;
use App\Jobs\ValidateExcelMONADJob;
use App\Jobs\PdfGenerateMONADJob;

class MolwaFFremarkController extends Controller
{        
    public function index(Request $request)
    {    
        if($request->ajax()){
            $where_str    = "1 = ?";
            //$where_str    .= " AND freedom_fighter_list.ff_id=molwa_ff_remarks.ff_id";
            $where_params = array(1); 

            //for seraching the keyword in datatable
            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and ( molwa_ff_remarks.created_at like \"%{$search}%\""
                . " or molwa_ff_remarks.remark like \"%{$search}%\""
                . " or molwa_ff_remarks.ff_id like \"%{$search}%\""
                /*. " or freedom_fighter_list.ff_name like \"%{$search}%\""
                . " or freedom_fighter_list.district like \"%{$search}%\""*/
                . ")";
            } 
            $auth_site_id=Auth::guard('admin')->user()->site_id;
            //for serial number
            $iDisplayStart=$request->input('iDisplayStart'); 
            DB::statement(DB::raw('set @rownum='.$iDisplayStart));
            //DB::statement(DB::raw('set @rownum=0'));
            
            //column that we wants to display in datatable
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'), 'freedom_fighter_list.id', 'molwa_ff_remarks.created_at', 'freedom_fighter_list.ff_photo', 'molwa_ff_remarks.ff_id', 'freedom_fighter_list.ff_name', 'freedom_fighter_list.father_or_husband_name', 'freedom_fighter_list.mother_name', 'freedom_fighter_list.post_office', 'freedom_fighter_list.post_code', 'freedom_fighter_list.district', 'freedom_fighter_list.upazila_or_thana', 'freedom_fighter_list.village_or_ward', 'freedom_fighter_list.nid', 'molwa_ff_remarks.remark'];
            
            $ffl_count = FFMolwaRemarkLog::select($columns)
            ->leftJoin('freedom_fighter_list', function($join) {
              $join->on('freedom_fighter_list.ff_id', '=', 'molwa_ff_remarks.ff_id');
            })
            ->whereRaw($where_str, $where_params)
            ->count();
            $ffl_list = FFMolwaRemarkLog::select($columns)
            ->leftJoin('freedom_fighter_list', function($join) {
              $join->on('freedom_fighter_list.ff_id', '=', 'molwa_ff_remarks.ff_id');
            })
            ->whereRaw($where_str, $where_params);

            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $ffl_list = $ffl_list->take($request->input('iDisplayLength'))
                ->skip($request->input('iDisplayStart'));
            }          

            //sorting the data column wise
            if($request->input('iSortCol_0')){
                $sql_order='';
                for ( $i = 0; $i < $request->input('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->input('iSortCol_' . $i)];
                    if(false !== ($index = strpos($column, ' as '))){
                        $column = substr($column, 0, $index);
                    }
                    $ffl_list = $ffl_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            }
            $ffl_list = $ffl_list->get();

            $response['iTotalDisplayRecords'] = $ffl_count;
            $response['iTotalRecords'] = $ffl_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $ffl_list->toArray();

            return $response;
        }
    	return view('admin.molwa.remarks');
    }




}
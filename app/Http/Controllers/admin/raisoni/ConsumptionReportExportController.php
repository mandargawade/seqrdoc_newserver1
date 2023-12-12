<?php

namespace App\Http\Controllers\admin\raisoni;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\ConsumptionReportRequest;
use App\models\raisoni\BranchMaster;
use App\models\raisoni\DegreeMaster;
use App\Exports\ConsumptionReportExport;
use App\Exports\ExamConsumptionReportExport;
use App\Exports\BranchConsumptionReportExport;
use App\Exports\SemesterConsumptionReportExport;
use App\Exports\AllConsumptionReportExport;
use Excel;


class ConsumptionReportExportController extends Controller
{
     public function index(Request $request)
    {
		return view('admin.raisoni.consumptionreportexport.index');
    }

    public function getBranchName(Request $request)
    {
        $branch_name=BranchMaster::select('id','branch_name_long')
								->where('is_active',1)
								->orderBy('branch_name_long','asc')
								->get()
								->toArray();
          
        return $branch_name;          
    }

    public function getDegreeBranchName(Request $request)
    {

        $degree_name=DegreeMaster::select('id')
                                ->where('degree_name',$request['degree_id'])
                                ->first();
        $branch_name=BranchMaster::select('id','branch_name_long')
                                ->where('degree_id',$degree_name->id)
                                ->where('is_active',1)
                                ->orderBy('branch_name_long','asc')
                                ->get()
                                ->toArray();
          
        return $branch_name;          
    }

    public function generateReportConsumption(ConsumptionReportRequest $request){
    	$data = $request->all();
    	$exam = $data['exam'];
    	$degree = $data['degree'];
        $branch = $data['branch'];
        $scheme = $data['scheme'];
        $term = $data['term'];
        $section = $data['section'];
        $fromDate = $data['fromDate'];
        $toDate = $data['toDate'];
        if(!empty($exam) && !empty($degree) && !empty($branch) && !empty($scheme) && !empty($term) && !empty($section) && !empty($fromDate) && !empty($toDate)){


	        $sheet_name = 'Grade Card Consumption Report_'. date('Y_m_d_H_i_s').'.xls'; 
	        
	        return Excel::download(new ConsumptionReportExport($exam,$degree,$branch,$scheme,$term,$section,$fromDate,$toDate),$sheet_name); 
	    }           
    }

    public function generateReportConsumptionExam(Request $request){
    	$data = $request->all();
    	$exam = $data['exam_filter2'];

    	$request->validate(
    		[
            	'exam_filter2' => 'required'
            ],
            [ 
            	'exam_filter2.required' => 'Exam is required.'
            ]);
        if(!empty($exam)){


	        $sheet_name = 'Grade Card Consumption Report_'. date('Y_m_d_H_i_s').'.xls'; 
	        
	        return Excel::download(new ExamConsumptionReportExport($exam),$sheet_name); 
	    }           
    }

    public function generateReportConsumptionBranch(Request $request){
    	$data = $request->all();
    	$branch = $data['branch_filter3'];

    	$request->validate(
    		[
            	'branch_filter3' => 'required'
            ],
            [ 
            	'branch_filter3.required' => 'Branch is required.'
            ]);
        if(!empty($branch)){


	        $sheet_name = 'Grade Card Consumption Report_'. date('Y_m_d_H_i_s').'.xls'; 
	        
	        return Excel::download(new BranchConsumptionReportExport($branch),$sheet_name); 
	    }           
    }

    public function generateReportConsumptionSemester(Request $request){
    	$data = $request->all();
    	$term = $data['term_filter4'];

    	$request->validate(
    		[
            	'term_filter4' => 'required'
            ],
            [ 
            	'term_filter4.required' => 'Semester is required.'
            ]);
        if(!empty($term)){


	        $sheet_name = 'Grade Card Consumption Report_'. date('Y_m_d_H_i_s').'.xls'; 
	        
	        return Excel::download(new SemesterConsumptionReportExport($term),$sheet_name); 
	    }           
    }

    public function generateReportConsumptionAllCount(Request $request){


        $sheet_name = 'Grade Card Consumption Report_'. date('Y_m_d_H_i_s').'.xls'; 
        
        return Excel::download(new AllConsumptionReportExport(),$sheet_name);        
    }
}

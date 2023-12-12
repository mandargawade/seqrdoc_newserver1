<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use App\models\raisoni\ConsumptionReport;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use App\models\raisoni\BranchMaster;
use DB;

class BranchConsumptionReportExport implements FromCollection, WithCustomStartCell , WithHeadings,WithEvents, ShouldAutoSize

{
    protected $branch; 

    function __construct($branch) {
        $this->branch = $branch;
    }
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        
        $where_str    = "1 = ?";
        $where_params = array(1);   
        DB::statement(DB::raw('set @rownum=0'));   
        $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'examination','programme','department','department as Department'];

        if (!empty($this->branch)) {
            $branch = $this->branch;
            $where_str .= " AND (department = '$branch')";
        }

        $BranchConsumptionReportExport = ConsumptionReport::select($columns)
        		->groupBy('examination','programme','department')
                 ->whereRaw($where_str, $where_params)
                 ->orderBy('examination','asc')
                 ->get()->toArray();

        foreach ($BranchConsumptionReportExport as $key => $value) {


        	$BranchConsumptionReportCount = ConsumptionReport::select('id')
                 ->where('examination',$value['examination'])
                 ->where('programme',$value['programme'])
                 ->where('department',$value['department'])
                 ->count();
            $BranchConsumptionReportExport[$key]['Department'] = $BranchConsumptionReportCount;
        }
     	return collect($BranchConsumptionReportExport);

    }

    public function startCell(): string
    {
        return 'A3';
    }
    
    public function headings(): array{
        $row = ['Sr. No.','Exam Name','Degree','Branch','Total Count'];
        return $row;
    }

    public function registerEvents(): array
    {
        $user_data = \auth::guard('admin')->user()->username;

        
        return [
            AfterSheet::class    => function(AfterSheet $event) use ($user_data){
                $event->sheet->SetCellValue('A1', "Report Generated By : ".$user_data);
                $event->sheet->mergeCells('A1:C1');
                $event->sheet->SetCellValue('D1', "Branch : ".$this->branch);
                $cellRange = 'A1:D1'; 
                $event->sheet->getDelegate()->getStyle($cellRange)->getFont()->setBold(true);
                $cellRange = 'A3:g3'; // All headers
                $event->sheet->getDelegate()->getStyle($cellRange)->getFont()->setBold(true);
            },
        ];
    }
}
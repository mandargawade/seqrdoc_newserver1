<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use App\models\raisoni\DamagedStock;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use DB;

class DamagedStockExport implements FromCollection, WithCustomStartCell , WithHeadings,WithEvents, ShouldAutoSize

{
    protected $type_damaged_filter; 
    protected $card_category;
    protected $serial_no_from;
    protected $serial_no_to;

    function __construct($type_damaged_filter,$card_category,$serial_no_from,$serial_no_to) {
        $this->type_damaged_filter = $type_damaged_filter;
        $this->card_category = $card_category;
        $this->serial_no_from = $serial_no_from;
        $this->serial_no_to = $serial_no_to;
    }
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        
        $where_str    = "1 = ?";
        $where_params = array(1);   
        DB::statement(DB::raw('set @rownum=0'));   
        $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'serial_no','created_at','remark','card_category'];

        if (!empty($this->serial_no_from) && !empty($this->serial_no_to)){
            $fromDate = date('Y-m-d', strtotime($this->serial_no_from));
            $toDate = date('Y-m-d', strtotime($this->serial_no_to));
            $range_condition = " AND (DATE(created_at) >= '$fromDate' && DATE(created_at) <= '$toDate')";
            $where_str .= $range_condition;
        }
        if ($this->card_category != 'All'){
            $card_category = $this->card_category;
            $where_str .= " AND (card_category = '$card_category')";
        }
        if ($this->type_damaged_filter != 'All'){
            $type_damaged_filter = $this->type_damaged_filter;
            $where_str .= " AND (type = '$type_damaged_filter')";
        }

        $DamagedStockExport = DamagedStock::select($columns)
                 ->whereRaw($where_str, $where_params)
                 ->orderBy('id','desc')
                 ->get()->toArray();
     	return collect($DamagedStockExport);

    }

    public function startCell(): string
    {
        return 'A3';
    }
    
    public function headings(): array{
        $row = ['Sr. No.','Serial No.','Date of entry','Remark','Card Category'];
        return $row;
    }

    public function registerEvents(): array
    {
        $user_data = \auth::guard('admin')->user()->username;
        
        return [
            AfterSheet::class    => function(AfterSheet $event) use ($user_data ){
                $event->sheet->SetCellValue('A1', "Report Generated By : ".$user_data);
                $event->sheet->mergeCells('A1:C1');
                $event->sheet->SetCellValue('D1', "Type : ".$this->type_damaged_filter);
                $event->sheet->SetCellValue('E1', "Card Category : ".$this->card_category);
                $event->sheet->SetCellValue('F1', "Start Date : ".$this->serial_no_from);
                $event->sheet->SetCellValue('G1', "To End Date : ".$this->serial_no_to);
                $cellRange = 'A1:g1'; 
                $event->sheet->getDelegate()->getStyle($cellRange)->getFont()->setBold(true);
                $cellRange = 'A3:g3'; // All headers
                $event->sheet->getDelegate()->getStyle($cellRange)->getFont()->setBold(true);
            },
        ];
    }
}

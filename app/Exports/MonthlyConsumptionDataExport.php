<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use App\models\Demo\SitesSuperdata;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use DB;

class MonthlyConsumptionDataExport  implements FromCollection,WithHeadings,WithEvents, ShouldAutoSize
{
    /**
    * @return \Illuminate\Support\Collection
    */

     protected $data;

    function __construct($data) {
        $this->data = $data;
    

    }
    public function collection()
    {
        
        //print_r($this->data);
        //exit;
        $data=$this->data;

       
     	return collect($data);
    }
    public function headings(): array{
    $row = ['Instance Name','Active Documents','Inactive Documents'];
    return $row;
    }
    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $cellRange = 'A1:g1'; // All headers
                $event->sheet->getDelegate()->getStyle($cellRange)->getFont()->setBold(true);
            },
        ];
    }
}

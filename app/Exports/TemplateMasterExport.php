<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use App\models\TemplateMaster;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class TemplateMasterExport implements FromCollection, WithHeadings,WithEvents, ShouldAutoSize

{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $columns = ['template_name','status'];
        

        $templateExport = TemplateMaster::select($columns)
                            ->get()->toArray();
                                  
        foreach ($templateExport as $key => $value) {

            if ($value['status'] == 0 ) {
                $templateExport[$key]['status'] = 'Inactive';
            }

            else
            {
                $templateExport[$key]['status'] = 'Active';
                   
            }
        }
     	return collect($templateExport);

    }
    public function headings(): array{
    $row = ['Template Name','Status'];
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

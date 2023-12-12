<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use App\models\BackgroundTemplateMaster;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class BackgroundTemplateExport implements FromCollection, WithHeadings,WithEvents, ShouldAutoSize

{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $columns = ['background_name','image_path','width','height','status','created_by','updated_by'];
        

        $templateExport = BackgroundTemplateMaster::select($columns)
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
    $row = ['Background Name','Image_path','Width','Height','Status','Created By','Updated By'];
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

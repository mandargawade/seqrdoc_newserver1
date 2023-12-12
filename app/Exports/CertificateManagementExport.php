<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use App\models\StudentTable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;


class CertificateManagementExport implements FromCollection,WithHeadings,WithEvents, ShouldAutoSize
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
         $columns = ['serial_no','certificate_filename','template_name','student_table.status'];
        

        $certificateExport = StudentTable::select($columns)
                                ->leftjoin('template_master','template_master.id','student_table.template_id')
                            ->get()->toArray();
                                  
        foreach ($certificateExport as $key => $value) {

            if ($value['status'] == 1 ) {
                $certificateExport[$key]['status'] = 'Active';
            }

            else
            {
                $certificateExport[$key]['status'] = 'Inctive';
                   
            }
        }
     	return collect($certificateExport);
    }
    public function headings(): array{
    $row = ['Serial No','Certificate Filename','Template Name','Status'];
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

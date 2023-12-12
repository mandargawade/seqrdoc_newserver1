<?php

namespace App\Exports;

use App\models\AnswerBookletData;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class AnswerBookletExport implements FromCollection, WithHeadings,WithEvents, ShouldAutoSize

{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $batchId = $_POST['batchId'];
        
       
        $columns = ['metadata1','serial_no','qr_data','key'];
        

        $templateExport = AnswerBookletData::select($columns)->where('batch_id',$batchId)
                            ->get()->toArray();
                                  
        // foreach ($templateExport as $key => $value) {

        //     if ($value['status'] == 0 ) {
        //         $templateExport[$key]['status'] = 'Inactive';
        //     }

        //     else
        //     {
        //         $templateExport[$key]['status'] = 'Active';
                   
        //     }
        // }
     	return collect($templateExport);

    }
    public function headings(): array{
    $row = ['Page Type','Print Serial Number','QR Serial Number','QR Text'];
    return $row;
    }
    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $cellRange = 'A1:D1'; // All headers
                $event->sheet->getDelegate()->getStyle($cellRange)->getFont()->setBold(true);
            },
        ];
    }
}

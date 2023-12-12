<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
// use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class IDcardImport implements ToCollection
{

    /**
    * @param Collection $collection
    */
    public function collection(Collection $rows)
    {

    }
     
    public function sheets(): array
    {
        return [
            // Select by sheet index
            0 => new IDcardImport(),
        ];
    }
    
    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                // All headers - set font size to 14
                $cellRange = 'A1:W1'; 
                $event->sheet->getDelegate()->getStyle($cellRange)->getFont()->setSize(14);

                // Apply array of styles to B2:G8 cell range
                $styleArray = [
                    'borders' => [
                        'outline' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
                            'color' => ['argb' => 'FFFF0000'],
                        ]
                    ]
                ];
                $event->sheet->getDelegate()->getStyle('B2:G8')->applyFromArray($styleArray);

                // Set first row to height 20
                $event->sheet->getDelegate()->getRowDimension(1)->setRowHeight(20);

                // Set A1:D4 range to wrap text in cells
                $event->sheet->getDelegate()->getStyle('A1:D4')
                    ->getAlignment()->setWrapText(true);
            },
        ];
    }

}

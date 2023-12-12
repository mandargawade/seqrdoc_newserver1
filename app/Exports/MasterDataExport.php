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

class MasterDataExport implements FromCollection,WithHeadings,WithEvents, ShouldAutoSize
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
         $columns = [DB::raw('SUBSTRING_INDEX(sites_name, ".", 1) AS sites_name'),DB::raw('(template_number+inactive_template_number+custom_templates+pdf2pdf_active_templates+pdf2pdf_inactive_templates) AS template_number'),'active_documents','inactive_documents','total_verifier','total_scanned','last_genration_date'];
        

        $certificateExport = SitesSuperdata::select($columns)
                            ->get()->toArray();
                                  
        /*foreach ($certificateExport as $key => $value) {

            if ($value['status'] == 1 ) {
                $certificateExport[$key]['status'] = 'Active';
            }

            else
            {
                $certificateExport[$key]['status'] = 'Inctive';
                   
            }
        }*/
     	return collect($certificateExport);
    }
    public function headings(): array{
    $row = ['Instance Name','Total Templates','Total Active Documents','Total Inactive Documents','Total Verifier','Total Scanned','Last Generation Date'];
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

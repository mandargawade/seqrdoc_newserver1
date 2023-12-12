<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use App\models\TemplateMaster;
use App\models\ApiTracker;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use DB;
// use Maatwebsite\Excel\Concerns\WithStartRow;
// use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;

class ApiTrakerExport implements FromCollection, WithHeadings,WithEvents, ShouldAutoSize, WithCustomStartCell

{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function startCell(): string
    {
        return 'A3';
    }
    /*public function headingRow(): int
    {
        return 3;
    }*/
    /*public function startRow(): int
    {
        return 2;
    }*/
    public function collection()
    {
        DB::statement(DB::raw('set @rownum=0'));   
        $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),  'id','request_url','client_ip','created','request_method','header_parameters','request_parameters','response_parameters','response_time'];

        $date = date('Y-m-d') . ' 17:59:00';
        $previousDate = date('Y-m-d', strtotime("-1 days")) . ' 18:00:00';

        $templateExport = ApiTracker::select($columns)->where("status","failed")->where('created','>=',$previousDate)->where('created','<=',$date)->get()->toArray();
       
       $templateData = [];
       foreach ($templateExport as $key => $value) {
           
           array_push($templateData, $value);
           $templateData_request_url = $templateData[$key]['request_url'];
           $templateData_request_explode = explode("/", $templateData_request_url);
           $api_name = end($templateData_request_explode);
           $templateData[$key]['request_url'] = $api_name;
           
       }
       
     	return collect($templateData);

    }
    
    public function headings(): array{
    
        $row = ['Sr. No.','Internal Id','Request URI','Client IP','Request Date-Time','Request Method','Header Parameters','Input Parameters','Response Parameters','Response Time (Seconds)'];
    return $row;
    }
    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {

               $date = date('Y-m-d') . ' 17:59:00';
               $previousDate = date('Y-m-d', strtotime("-1 days")) . ' 18:00:00';
               $templateExport1 = ApiTracker::select('request_url')->where("status","failed")->get()->toArray();

                foreach ($templateExport1 as $key1 => $value1) {
               
                    $urlData=parse_url($value1['request_url']);

                    $hostData = explode('.', $urlData['host']);
               
                }

                $cellRange = 'A3:J3'; // All headers
                $event->sheet->getDelegate()->mergeCells('A1:J1')->setCellValue('A1','Project Name : '.$hostData[0].'    |    '.'Base URL : http://'.$urlData['host'].'/api/    |    Start Date : '.$previousDate.' To End Date : '.$date)->getStyle($cellRange)->getFont()->setBold(true);
            },
        ];
    }
}

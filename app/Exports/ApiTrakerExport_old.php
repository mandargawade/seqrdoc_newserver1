<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use App\models\ApiTracker;
use App\models\TemplateMaster;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ApiTrakerExport implements FromCollection, WithHeadings,WithEvents, ShouldAutoSize
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

   
}

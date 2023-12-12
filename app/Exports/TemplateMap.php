<?php

namespace App\Exports;

use App\SalesOrder;
use Maatwebsite\Excel\Concerns\FromCollection;

class TemplateMap implements FromCollection
{
    public function collection()
    {   
        return SalesOrder::all();
    }
}

?>
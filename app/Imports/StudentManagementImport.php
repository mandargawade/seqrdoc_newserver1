<?php

namespace App\Imports;
use App\models\StudentDocument;
use App\models\StudentMaster;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class StudentManagementImport implements ToCollection
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function collection(Collection  $rows)
    {
        return $rows;
    }
}

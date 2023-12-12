<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\AnswerBookletData;

use App\models\SystemConfig;
use App\Http\Requests\ExcelValidationRequest;
use Excel;
use File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell;
use Illuminate\Support\Collection;
use Auth,Storage;
use TCPDF;
use QrCode;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use App\models\Config;
use App\Helpers\CoreHelper;
class ImportCustomMandar extends Controller
{
    public function importData(Request $request){

    	 		$exel_filename=public_path()."/jssaher/excels/JSSAHER Anwser Booklet Sample QR codes.xlsx";
    	
    			$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($exel_filename);
				$reader->setReadDataOnly(true);
				$spreadsheet = $reader->load($exel_filename);
				$sheet = $spreadsheet->getSheet(0);
				$highestColumn = $sheet->getHighestColumn();
				$highestRow = $sheet->getHighestRow();
				
				$rowData = $sheet->rangeToArray('A1:' . $highestColumn . '1', NULL, TRUE, FALSE);

				for($excel_row = 2; $excel_row <= $highestRow; $excel_row++)
				{
					$dt = date("_ymdHis");
					$datetime = date("Y-m-d H:i:s");
					$rowData1 = $sheet->rangeToArray('A'. $excel_row . ':' . $highestColumn . $excel_row, NULL, TRUE, FALSE);
					$serial_no = $rowData1[0][1];

					$key = strtoupper(md5($serial_no.$dt));

					$recordExist = AnswerBookletData::select('id')->where('serial_no',$serial_no)->first();
					if(!$recordExist){
						print_r($rowData1);

						$result = AnswerBookletData::create(['serial_no'=>$serial_no,'qr_data'=>$serial_no,'key'=>$key,'metadata1'=>$rowData1[0][0],'created_at'=>$datetime,'updated_at'=>$datetime,'status'=>1,'publish'=>1,'site_id'=>303]);
					}
				

				}
    }
   
	
}


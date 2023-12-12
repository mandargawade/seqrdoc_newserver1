<?php

namespace App\Http\Controllers\admin\raisoni;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\raisoni\BranchMaster;
use App\models\raisoni\DegreeMaster;
use DB,Auth;
use App\Http\Requests\BranchRequest;
use App\models\raisoni\ScanningRequests;
use App\models\User;
use App\models\Transactions;
use App\models\raisoni\ScannedDocuments;

class SeqrDocumentRequestController extends Controller
{
	public function index(Request $request)
	{

		//
		if($request->ajax()){
			
			$where_str = '1 = ?';
			$where_params = [1];

			if (!empty($request->input('sSearch')))
			{
			    $search = $request->input('sSearch');
			    
			    if($search != ''){
			        $where_str .= " and (scanning_requests.request_number like \"%{$search}%\""
			         	. " or user_table.fullname like \"%{$search}%\""
				     	. " or scanning_requests.device_type like \"%{$search}%\""
				    	. ")";
			    }
			}
			
			if(!empty($request->fromDate) && !empty($request->toDate))   
			{
				$fromDate = date('Y-m-d', strtotime($request->fromDate));
				$toDate = date('Y-m-d', strtotime($request->toDate));
				if($fromDate != '' && $toDate!= ''){
			        $where_str .= " and (scanning_requests.created_date_time >= \"{$fromDate}\" AND scanning_requests.created_date_time <= \"{$toDate}\" "
			        . ")";
			    }
				
			}                                         
			$where_str .= " and (scanning_requests.payment_status='Paid')";
	    	$columns = ['user_table.fullname', 'scanning_requests.id','scanning_requests.request_number','scanning_requests.device_type','scanning_requests.no_of_documents','scanning_requests.created_date_time'];


			$scanning_requests = ScanningRequests::select($columns)
							->leftjoin('user_table', 'user_table.id', 'scanning_requests.user_id')
		                    ->whereRaw($where_str, $where_params);  

			
		    $scanning_requests_column_count = ScanningRequests::select($columns)
		    				->leftjoin('user_table', 'user_table.id', 'scanning_requests.user_id')
		                    ->whereRaw($where_str, $where_params)
		                    ->count();
		    
		    if($request->has('iDisplayStart') && $request->get('iDisplayLength') !='-1'){
                $scanning_requests = $scanning_requests->take($request->get('iDisplayLength'))->skip($request->get('iDisplayStart'));
            }

            if($request->has('iSortCol_0')){
                for ( $i = 0; $i < $request->get('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->get('iSortCol_' . $i)];
                    if (false !== ($index = strpos($column, ' as '))) {
                        $column = substr($column, 0, $index);
                    }
                    $scanning_requests = $scanning_requests->orderBy($column,$request->get('sSortDir_'.$i));
                }
            }
			$scanning_requests = $scanning_requests->get();
			$response['iTotalDisplayRecords'] = $scanning_requests_column_count;
			$response['iTotalRecords'] = $scanning_requests_column_count;
			$response['sEcho'] = intval($request->input('sEcho'));
			$response['aaData'] = $scanning_requests->toArray();
			

			return $response;
		}
	        
	     
	    return view('admin.raisoni.seqrDocumentRequests.index');
	}

	public function show(Request $request)
	{
		$scan_request_id = $request->id;
		
		if(!empty($scan_request_id))
		{
			$scan_request_data = DB::select(DB::raw("SELECT sr.*,DATE_FORMAT(sr.created_date_time, '%d %b %Y %h:%i %p') as created_date_time,ut.fullname,ut.l_name,tr.trans_id_ref as payment_transaction_id,tr.trans_id_gateway as payment_gateway_id, tr.payment_mode,tr.amount,DATE_FORMAT(tr.created_at, '%d %b %Y %h:%i %p') as payment_date_time FROM scanning_requests as sr
										INNER JOIN user_table as ut ON sr.user_id=ut.id
										INNER JOIN transactions as tr ON sr.request_number=tr.student_key AND tr.trans_status=1
									 WHERE sr.id='" . $scan_request_id . "'"));

							$scanned_documents = DB::select(DB::raw("SELECT sd.*,st.serial_no,tm.template_name,st.status,st.template_type,st.template_id FROM scanned_documents as sd
												  INNER JOIN student_table as st ON sd.document_key = st.key
												  LEFT JOIN template_master as tm ON st.template_id = tm.id
												  WHERE sd.request_id='" . $scan_request_id . "'"));

							
				return response()->json(['type' => 'success', 'requestData' => $scan_request_data[0], 'requestDetails' => $scanned_documents]);	
		}
		
	}
	public function TransactionReport(Request $request){

		$extraWhere = "";
		if (isset($request['fromDate']) && isset($request['toDate']) && !empty($request['fromDate']) && !empty($request['toDate'])) {
			$fromDate = date('Y-m-d', strtotime($request['fromDate']));
			$toDate = date('Y-m-d', strtotime($request['toDate']));
			$extraWhere .= " AND (DATE(sr.created_date_time) >= '" . $fromDate . "' AND DATE(sr.created_date_time) <= '" . $toDate . "')";

		} else {
			$request['fromDate'] = "   -   ";
			$request['toDate'] = "   -   ";
		}

		$gotData = DB::select(DB::raw("SELECT sr.*,DATE_FORMAT(sr.created_date_time, '%d %b %Y %h:%i %p') as created_date_time,ut.fullname,ut.l_name,ut.username as submitted_by,tr.trans_id_ref as payment_transaction_id,tr.trans_id_gateway,st.template_type as payment_gateway_id, tr.payment_mode,tr.amount,DATE_FORMAT(tr.created_at, '%d %b %Y %h:%i %p') as payment_date_time,st.serial_no,tm.template_name,st.status,sd.amount as document_amount FROM scanning_requests as sr INNER JOIN user_table as ut ON sr.user_id=ut.id INNER JOIN scanned_documents as sd ON sr.id = sd.request_id INNER JOIN student_table as st ON sd.document_key = st.key LEFT JOIN template_master as tm ON st.template_id = tm.id INNER JOIN transactions as tr ON sr.request_number=tr.student_key AND tr.trans_status=1 WHERE sr.payment_status='Paid' " . $extraWhere . " ORDER BY sr.created_date_time DESC"));
		
		$filename = 'Report-QR';
		$columnEnd = "O";
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$user_id=Auth::guard('admin')->user()->fullname;
		if (!empty($gotData)) {

			// Rename worksheet
			$spreadsheet->getActiveSheet()->setTitle('Sheet 1');
			$wrsheet = $spreadsheet->getSheet(0);
			//User Details
			$headerIndex = 3;
			$wrsheet->SetCellValueByColumnAndRow(1, 1, "Report Generated By : {$user_id}      |       QR       |       Start Date : {$_POST['fromDate']}      |      To End Date : {$_POST['toDate']}");
			//header
			$wrsheet->SetCellValueByColumnAndRow(1, $headerIndex, 'Sr. No.');
			$wrsheet->SetCellValueByColumnAndRow(2, $headerIndex, 'Request Number');
			$wrsheet->SetCellValueByColumnAndRow(3, $headerIndex, 'User Name');
			$wrsheet->SetCellValueByColumnAndRow(4, $headerIndex, 'No of Documents');
			$wrsheet->SetCellValueByColumnAndRow(5, $headerIndex, 'Submission Date');
			$wrsheet->SetCellValueByColumnAndRow(6, $headerIndex, 'Device Type');
			$wrsheet->SetCellValueByColumnAndRow(7, $headerIndex, 'Payment Transaction Id');
			$wrsheet->SetCellValueByColumnAndRow(8, $headerIndex, 'Payment Gateway Id');
			$wrsheet->SetCellValueByColumnAndRow(9, $headerIndex, 'Payment Mode');
			$wrsheet->SetCellValueByColumnAndRow(10, $headerIndex, 'Amount');
			$wrsheet->SetCellValueByColumnAndRow(11, $headerIndex, 'payment Date Time');
			$wrsheet->SetCellValueByColumnAndRow(12, $headerIndex, 'Unique Document No.');
			$wrsheet->SetCellValueByColumnAndRow(13, $headerIndex, 'Template');
			$wrsheet->SetCellValueByColumnAndRow(14, $headerIndex, 'Status');
			$wrsheet->SetCellValueByColumnAndRow(15, $headerIndex, 'Amount');

			$i = 4;
			$sheetId = 0;
			$recordCount = 1;
			for ($s = 0; $s < sizeof($gotData); $s++) {
				$row = $gotData[$s];
			

				if ($s == 0 || ($gotData[$s]->request_number != $gotData[$s - 1]->request_number)) {
					$wrsheet->SetCellValueByColumnAndRow(1, $i, $recordCount);
					$wrsheet->SetCellValueByColumnAndRow(2, $i, $row->request_number);
					$wrsheet->SetCellValueByColumnAndRow(3, $i, $row->fullname . ' ' . $row->l_name);
					$wrsheet->SetCellValueByColumnAndRow(4, $i, $row->no_of_documents);
					$wrsheet->SetCellValueByColumnAndRow(5, $i, $row->created_date_time);
					$wrsheet->SetCellValueByColumnAndRow(6, $i, $row->device_type);
					$wrsheet->SetCellValueByColumnAndRow(7, $i, $row->payment_transaction_id);
					$wrsheet->SetCellValueByColumnAndRow(8, $i, $row->payment_gateway_id);
					$wrsheet->SetCellValueByColumnAndRow(9, $i, $row->payment_mode);
					$wrsheet->SetCellValueByColumnAndRow(10, $i, $row->amount);
					$wrsheet->SetCellValueByColumnAndRow(11, $i, $row->payment_date_time);
					$recordCount++;
				} else {
					$wrsheet->mergeCells("A" . $i . ":K" . $i);

				}
				$wrsheet->SetCellValueByColumnAndRow(12, $i, $row->serial_no);

				if($row->template_type==2){
					$template_name="Custom Template";
				}else if($row->template_type==1){
					$template_name="PHF2PDF Template";

				}else{
					$template_name=$row->template_name;

				}
				$wrsheet->SetCellValueByColumnAndRow(13, $i, $template_name);
				if ($row->status == 1) {
					$status = "Active";
				} else {
					$status = "Disabled";
				}
				$wrsheet->SetCellValueByColumnAndRow(14, $i, $status);

				if ($row->document_amount == 0) {
					$amount = "Already Paid";
				} else {
					$amount = $row->document_amount;
				}
				$wrsheet->SetCellValueByColumnAndRow(15, $i, $amount);
				$i++;
				if ($i == 65537) {
					$headerIndex = 1;
					
					// Create a new worksheet, after the default sheet
					$spreadsheet->createSheet();
					$sheetId = $sheetId + 1;
					$spreadsheet->setActiveSheetIndex($sheetId);
					$spreadsheet->getActiveSheet()->setTitle('Sheet ' . ($sheetId + 1));
					$wrsheet = $spreadsheet->getSheet($sheetId);
					$wrsheet->SetCellValueByColumnAndRow(1, $headerIndex, 'Sr. No.');
					$wrsheet->SetCellValueByColumnAndRow(2, $headerIndex, 'Request Number');
					$wrsheet->SetCellValueByColumnAndRow(3, $headerIndex, 'User Name');
					$wrsheet->SetCellValueByColumnAndRow(4, $headerIndex, 'No of Documents');
					$wrsheet->SetCellValueByColumnAndRow(5, $headerIndex, 'Submission Date');
					$wrsheet->SetCellValueByColumnAndRow(6, $headerIndex, 'Device Type');
					$wrsheet->SetCellValueByColumnAndRow(7, $headerIndex, 'Payment Transaction Id');
					$wrsheet->SetCellValueByColumnAndRow(8, $headerIndex, 'Payment Gateway Id');
					$wrsheet->SetCellValueByColumnAndRow(9, $headerIndex, 'Payment Mode');
					$wrsheet->SetCellValueByColumnAndRow(10, $headerIndex, 'Amount');
					$wrsheet->SetCellValueByColumnAndRow(11, $headerIndex, 'payment Date Time');
					$wrsheet->SetCellValueByColumnAndRow(12, $headerIndex, 'Unique Document No.');
					$wrsheet->SetCellValueByColumnAndRow(13, $headerIndex, 'Template');
					$wrsheet->SetCellValueByColumnAndRow(14, $headerIndex, 'Status');
					$wrsheet->SetCellValueByColumnAndRow(15, $headerIndex, 'Amount');
					$i = 2;
				}

			}
			for ($z = 0; $z <= $sheetId; $z++) {
				$spreadsheet->setActiveSheetIndex($z);
				// adjust auto column width
				foreach (range('A', $columnEnd) as $columnID) {
					$spreadsheet->getActiveSheet()->getColumnDimension($columnID)
						->setAutoSize(true);

				}
				$wrsheet = $spreadsheet->getSheet($z);

				//Merging cell
				$wrsheet->mergeCells("A1:" . $columnEnd . "1");
				$wrsheet->mergeCells("A2:" . $columnEnd . "2");

				//heading bold
				$wrsheet->getStyle("A" . $headerIndex . ":" . $columnEnd . $headerIndex)->getFont()->setBold(true);

				//creating table structure
				$styleArray = array(
					'borders' => array(
						'allborders' => array(
							'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
							'color' => array('argb' => '000000'),
						),
					),
				);

				if ($z == $sheetId) {
					$totalRecordsInSheet = $i;
				} else {
					$totalRecordsInSheet = 65537;
				}
				$wrsheet->getStyle('A0:' . $columnEnd . ($totalRecordsInSheet - 1))->applyFromArray($styleArray);

				//header colour
				$spreadsheet->getActiveSheet()->getStyle('A' . $headerIndex . ':O' . $headerIndex)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('cdcdcd');

				
			}
			$spreadsheet->setActiveSheetIndex(0);

			// Redirect output to a client’s web browser (Excel2007)
			//	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Type: application/vnd.ms-excel');
			header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
			header('Cache-Control: max-age=0');
			// If you're serving to IE 9, then the following may be needed
			header('Cache-Control: max-age=1');
			// If you're serving to IE over SSL, then the following may be needed
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
			header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
			header('Pragma: public'); // HTTP/1.0

			$objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
			ob_clean();
			$objWriter->save('php://output');
			exit;
		}else{

			// Rename worksheet
			$spreadsheet->getActiveSheet()->setTitle('Sheet 1');
			$wrsheet = $spreadsheet->getSheet(0);
			//User Details
			$headerIndex = 3;
			$wrsheet->SetCellValueByColumnAndRow(1, 1, "Report Generated By : {$user_id}      |       QR       |      Start Date : {$_POST['fromDate']}      |      To End Date : {$_POST['toDate']}");
			$wrsheet->SetCellValueByColumnAndRow(1, 4, "No Records Found !");
			$wrsheet->mergeCells("A1:" . $columnEnd . "1");
			$wrsheet->mergeCells("A2:" . $columnEnd . "2");
			$wrsheet->mergeCells("A4:" . $columnEnd . "4");
			//header
			$wrsheet->SetCellValueByColumnAndRow(1, $headerIndex, 'Sr. No.');
			$wrsheet->SetCellValueByColumnAndRow(2, $headerIndex, 'Request Number');
			$wrsheet->SetCellValueByColumnAndRow(3, $headerIndex, 'User Name');
			$wrsheet->SetCellValueByColumnAndRow(4, $headerIndex, 'No of Documents');
			$wrsheet->SetCellValueByColumnAndRow(5, $headerIndex, 'Submission Date');
			$wrsheet->SetCellValueByColumnAndRow(6, $headerIndex, 'Device Type');
			$wrsheet->SetCellValueByColumnAndRow(7, $headerIndex, 'Payment Transaction Id');
			$wrsheet->SetCellValueByColumnAndRow(8, $headerIndex, 'Payment Gateway Id');
			$wrsheet->SetCellValueByColumnAndRow(9, $headerIndex, 'Payment Mode');
			$wrsheet->SetCellValueByColumnAndRow(10, $headerIndex, 'Amount');
			$wrsheet->SetCellValueByColumnAndRow(11, $headerIndex, 'payment Date Time');
			$wrsheet->SetCellValueByColumnAndRow(12, $headerIndex, 'Unique Document No.');
			$wrsheet->SetCellValueByColumnAndRow(13, $headerIndex, 'Template');
			$wrsheet->SetCellValueByColumnAndRow(14, $headerIndex, 'Status');
			$wrsheet->SetCellValueByColumnAndRow(15, $headerIndex, 'Amount');

			//heading bold
			$wrsheet->getStyle("A" . $headerIndex . ":" . $columnEnd . $headerIndex)->getFont()->setBold(true);

			//creating table structure
			$styleArray = array(
				'borders' => array(
					'allborders' => array(
						'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
						'color' => array('argb' => '000000'),
					),
				),
			);


			//header colour
			$spreadsheet->getActiveSheet()->getStyle('A' . $headerIndex . ':O' . $headerIndex)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('cdcdcd');
			//creating table structure
			
			$wrsheet->getStyle('A0:' . $columnEnd . (4))->applyFromArray($styleArray);
			foreach (range('A', $columnEnd) as $columnID) {
				$spreadsheet->getActiveSheet()->getColumnDimension($columnID)
					->setAutoSize(true);

			}
			$wrsheet = $spreadsheet->getSheet(0);

			// Redirect output to a client’s web browser (Excel2007)
			//	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Type: application/vnd.ms-excel');
			header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
			header('Cache-Control: max-age=0');
			// If you're serving to IE 9, then the following may be needed
			header('Cache-Control: max-age=1');
			// If you're serving to IE over SSL, then the following may be needed
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
			header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
			header('Pragma: public'); // HTTP/1.0

			$objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
			ob_clean();
			$objWriter->save('php://output');
			exit;
		}
		
	}

	
	public function SummaryReport(Request $request){
		
		$extraWhere = "";
		if (isset($request['fromDate']) && isset($request['toDate']) && !empty($request['fromDate']) && !empty($request['toDate'])) {
			$fromDate = date('Y-m-d', strtotime($request['fromDate']));
			$toDate = date('Y-m-d', strtotime($request['toDate']));
			$extraWhere .= " AND (DATE(sr.created_date_time) >= '" . $fromDate . "' AND DATE(sr.created_date_time) <= '" . $toDate . "')";

		}
		
		$gotData = DB::select(DB::raw("SELECT st.template_type,tm.template_name,count(*) as totalCount,sum(sd.amount) as totalAmount FROM scanning_requests as sr
										INNER JOIN scanned_documents as sd ON sr.id=sd.request_id
										INNER JOIN student_table as st ON sd.document_key = st.key
										LEFT JOIN template_master as tm ON st.template_id = tm.id
										INNER JOIN transactions as tr ON sr.request_number=tr.student_key AND tr.trans_status=1
										WHERE sr.payment_status='Paid' " . $extraWhere."group by tm.template_name,st.template_type"));
		
		$filename = 'Report';
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$user_id=Auth::guard('admin')->user()->username;

		if(!empty($gotData)){

			$spreadsheet->getActiveSheet()->setTitle('Sheet 1');
			$wrsheet = $spreadsheet->getSheet(0);
			//User Details
			$headerIndex = 3;
			
			$wrsheet->SetCellValueByColumnAndRow(1, 1, "Report Generated By : {$user_id}      |       QR       |      Start Date : {$request['fromDate']}      |      To End Date : {$request['toDate']}");
			//header
			$wrsheet->SetCellValueByColumnAndRow(1, $headerIndex, 'Sr. No.');
			$wrsheet->SetCellValueByColumnAndRow(2, $headerIndex, 'Branch');
			$wrsheet->SetCellValueByColumnAndRow(3, $headerIndex, 'Total Request Received');
			$wrsheet->SetCellValueByColumnAndRow(4, $headerIndex, 'Total payment Received');

			$i = 4;
			$sheetId = 0;
			$recordCount = 1;
			foreach ($gotData as $row) {
				$wrsheet->SetCellValueByColumnAndRow(1, $i, $recordCount);
				if($row->template_type==2){
					$template_name="Custom Template";
				}else if($row->template_type==1){
					$template_name="PHF2PDF Template";

				}else{
					$template_name=$row->template_name;

				}
				$wrsheet->SetCellValueByColumnAndRow(2, $i, $template_name);
				

				$wrsheet->SetCellValueByColumnAndRow(3, $i, $row->totalCount);
				$wrsheet->SetCellValueByColumnAndRow(4, $i, $row->totalAmount);
				$i++;
				if ($i == 65537) {
					$headerIndex = 1;
					//65537
					// Create a new worksheet, after the default sheet
					$wrobjPHPExcel->createSheet();
					$sheetId = $sheetId + 1;
					$wrobjPHPExcel->setActiveSheetIndex($sheetId);
					$wrobjPHPExcel->getActiveSheet()->setTitle('Sheet ' . ($sheetId + 1));
					$wrsheet = $wrobjPHPExcel->getSheet($sheetId);
					$wrsheet->SetCellValueByColumnAndRow(1, $headerIndex, 'Sr. No.');
					$wrsheet->SetCellValueByColumnAndRow(2, $headerIndex, 'Branch');
					$wrsheet->SetCellValueByColumnAndRow(3, $headerIndex, 'Total Request Received');
					$wrsheet->SetCellValueByColumnAndRow(4, $headerIndex, 'Total payment Received');
					$i = 2;
				}

				$recordCount++;
			}
			for ($z = 0; $z <= $sheetId; $z++) {
				$spreadsheet->setActiveSheetIndex($z);
				// adjust auto column width
				foreach (range('A', 'D') as $columnID) {
					$spreadsheet->getActiveSheet()->getColumnDimension($columnID)
						->setAutoSize(true);

				}
				$wrsheet = $spreadsheet->getSheet($z);

				//Merging cell
				$wrsheet->mergeCells("A1:D1");
				$wrsheet->mergeCells("A2:D2");

				//heading bold
				$wrsheet->getStyle("A" . $headerIndex . ":D" . $headerIndex)->getFont()->setBold(true);

				//creating table structure

				$styleArray = array(
					'borders' => array(
						'allborders' => array(
							'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
							'color' => array('argb' => '000000'),
						),
					),
				);

				if ($z == $sheetId) {
					$totalRecordsInSheet = $i;
				} else {
					$totalRecordsInSheet = 65537;
				}
				$wrsheet->getStyle('A0:D' . ($totalRecordsInSheet - 1))->applyFromArray($styleArray);

				//header colour

				$spreadsheet->getActiveSheet()->getStyle('A' . $headerIndex .':D'. $headerIndex)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('cdcdcd');
			}
			$spreadsheet->setActiveSheetIndex(0);

			header('Content-Type: application/vnd.ms-excel');
			header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
			header('Cache-Control: max-age=0');
			// If you're serving to IE 9, then the following may be needed
			header('Cache-Control: max-age=1');
			// If you're serving to IE over SSL, then the following may be needed
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
			header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
			header('Pragma: public'); // HTTP/1.0

			$objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
			ob_clean();
			$objWriter->save('php://output');
			exit;
			
		}else{

			// Rename worksheet
			$spreadsheet->getActiveSheet()->setTitle('Sheet 1');
			$wrsheet = $spreadsheet->getSheet(0);
			//User Details
			$headerIndex = 3;
			$wrsheet->SetCellValueByColumnAndRow(1, 1, "Report Generated By : {$_SESSION['username']}      |       NON-QR       |      Start Date : {$_POST['fromDate']}      |      To End Date : {$_POST['toDate']}");
			$wrsheet->SetCellValueByColumnAndRow(1, 4, "No Records Found !");
			$wrsheet->mergeCells("A1:S1");
			$wrsheet->mergeCells("A2:S2");
			$wrsheet->mergeCells("A4:S4");
			//header
			$wrsheet->SetCellValueByColumnAndRow(2, $headerIndex, 'Branch');
			$wrsheet->SetCellValueByColumnAndRow(3, $headerIndex, 'Total Request Received');
			$wrsheet->SetCellValueByColumnAndRow(4, $headerIndex, 'Total payment Received');

			//heading bold
			$wrsheet->getStyle("A" . $headerIndex . ":S" . $headerIndex)->getFont()->setBold(true);

			
			//header colour
			$spreadsheet->getActiveSheet()->getStyle('A' . $headerIndex .':S'. $headerIndex)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('cdcdcd');
			//creating table structure
			$styleArray = array(
				'borders' => array(
					'allborders' => array(
						'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
						'color' => array('argb' => '000000'),
					),
				),
			);
			$wrsheet->getStyle('A0:S' . (4))->applyFromArray($styleArray);
			
			foreach (range('A', 'S') as $columnID) {
				$spreadsheet->getActiveSheet()->getColumnDimension($columnID)
					->setAutoSize(true);

			}
			$wrsheet = $spreadsheet->getSheet(0);

			// Redirect output to a client’s web browser (Excel2007)
			//	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Type: application/vnd.ms-excel');
			header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
			header('Cache-Control: max-age=0');
			// If you're serving to IE 9, then the following may be needed
			header('Cache-Control: max-age=1');
			// If you're serving to IE over SSL, then the following may be needed
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
			header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
			header('Pragma: public'); // HTTP/1.0

			$objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
			ob_clean();
			$objWriter->save('php://output');
			exit;
		}
	}
	function getSummuryData($branch_id) {

		$extraWhere = "";
		if (isset($_POST['fromDate']) && isset($_POST['toDate']) && !empty($_POST['fromDate']) && !empty($_POST['toDate'])) {
			$fromDate = date('Y-m-d', strtotime($_POST['fromDate']));
			$toDate = date('Y-m-d', strtotime($_POST['toDate']));
			$extraWhere .= " AND (DATE(created_date_time) >= '" . $fromDate . "' AND DATE(created_date_time) <= '" . $toDate . "')";

		}
		$sql = $DB::select(DB::raw("SELECT count(*) as totalCount,sum(total_amount) as totalAmount FROM verification_requests  WHERE payment_status='Paid' AND branch='" . $branch_id . "'" . $extraWhere));
		
		return $sql;
	}
}


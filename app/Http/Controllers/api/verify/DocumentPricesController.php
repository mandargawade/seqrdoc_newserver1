<?php

namespace App\Http\Controllers\api\verify;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\models\raisoni\DocumentPrices;
use App\models\Site;
use App\Utility\ApiSecurityLayer;

class DocumentPricesController extends Controller
{
    //
    public function index(Request $request)
    {
        $data = $request->post();
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        if($subdomain[0] == "demo"){
            $result = DocumentPrices::orderBy('created_at', 'desc')->limit(2)->get();
        }
        else{
            $result = DocumentPrices::all();
        }    
        
        try {
            $dataHeading = array('Document Type', 'Rate Per Document', 'Maximum Uploads');
            $dataPrice = array();
            if ($result) {
                foreach ($result as $readData) {

                    array_push($dataPrice, array($readData['document_name'], $readData['amount_per_document'] . ' RS', $readData['maximum_no_of_uploads'] . ' Files'));
                }
            }
            $message = array('status' => 200,'success' => true,'message' => 'success', 'dataHeading' => $dataHeading, 'dataPrice' => $dataPrice);

        } catch (exception $e) {

            $message = array('status' => 500, 'message' => $e->getMessage());

        }
        echo json_encode($message);

        $requestUrl = \Request::Url();
        $requestMethod = \Request::method();
        $requestParameter = $data;

        if ($message['status']==200) {
            
            $status = 'success';
        }
        else
        {
            $status = 'failed';
        }

        ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);

        
    }
}

<?php
/**
 *
 *  Author : Mandar Gawade 
 *   Date  : 09/07/2020
 *   Use   : show and update and get information of Documents Rate Master
 *
**/
namespace App\Http\Controllers\admin\raisoni;

use App\Http\Controllers\Controller;
//use App\Http\Requests\DocumentsRateMasterRequest;
use App\models\raisoni\DocumentRateMaster;
use Illuminate\Http\Request;
use Auth;
class DocumentsRateMasterController extends Controller
{
     /**
     * Display a listing of the DocumentsRateMaster Detail.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if($request->ajax())
        {
           
           $document_rate_data= DocumentRateMaster::select('document_name','document_id','maximum_no_of_uploads','amount_per_document','updated_at')->get()->toArray();
           return $document_rate_data;
        }
    	return view('admin.raisoni.paymentGatewayConfig.index');
    }

    
    
    /**
     * Update the specified DocumentsRateMaster in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        
        
        $grade_card_update=DocumentRateMaster::where('document_id','grade_card')->update(['amount_per_document'=>$request['grade_card']]);
        $provisional_degree_update=DocumentRateMaster::where('document_id','provisional_degree')->update(['amount_per_document'=>$request['provisional_degree']]);
        $original_degree_update=DocumentRateMaster::where('document_id','original_degree')->update(['amount_per_document'=>$request['original_degree']]);
        $marksheet_update=DocumentRateMaster::where('document_id','marksheet')->update(['amount_per_document'=>$request['marksheet']]);

       
        return response()->json(['success'=>true]);      	      
    }

   
        
  
}

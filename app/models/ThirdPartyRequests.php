<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class ThirdPartyRequests extends Model
{
    protected $table = 'third_party_requests';

    protected $fillable = [
    	'session_manager_id','template_id','excel_file','generation_type','ip_address','status','total_documents','generated_documents','regenerated_documents','printable_pdf_link','response','call_back_url','input_source','created_at','updated_at'
    ];
}

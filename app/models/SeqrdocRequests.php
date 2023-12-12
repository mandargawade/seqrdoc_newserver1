<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class SeqrdocRequests extends Model
{
    protected $table = 'seqrdoc_requests';

    protected $fillable = [
    	'request_id','template_id','excel_file','generation_type','status','total_documents','generated_documents','regenerated_documents','printable_pdf_link','response','call_back_url','input_source','created_at','updated_at'
    ];
}

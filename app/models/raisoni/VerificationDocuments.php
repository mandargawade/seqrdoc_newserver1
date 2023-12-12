<?php

namespace App\models\raisoni;

use Illuminate\Database\Eloquent\Model;

class VerificationDocuments extends Model
{
    protected $guard = 'admin';
    protected $table = 'verification_documents';

    protected $fillable = [
        'request_id','document_type','document_path', 'result_found_status','remark', 'exam_name','semester','doc_year','document_price','is_refunded','refund_id','last_updated_by'
    ];
    public $timestamps = false;
}

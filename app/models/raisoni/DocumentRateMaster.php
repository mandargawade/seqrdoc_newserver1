<?php

namespace App\models\raisoni;

use Illuminate\Database\Eloquent\Model;

class DocumentRateMaster extends Model
{
    protected $table='documents_rate_master';
    protected $fillable=['document_name','document_id','amount_per_document','maximum_no_of_uploads',];
}

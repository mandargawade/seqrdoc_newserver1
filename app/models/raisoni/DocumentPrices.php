<?php

namespace App\models\raisoni;

use Illuminate\Database\Eloquent\Model;

class DocumentPrices extends Model
{
    //
    protected $guard = 'admin';
    protected $table = 'documents_rate_master';

    protected $fillable = [
        'document_name','document_id', 'amount_per_document','maximum_no_of_uploads','created_date_time', 'updated_date_time'
    ];

}

<?php

namespace App\models\raisoni;

use Illuminate\Database\Eloquent\Model;

class ScannedDocuments extends Model
{
    protected $guard = 'admin';
    protected $table = 'scanned_documents';

    protected $fillable = [
        'request_id','documents_key', 'amount','already_paid'
    ];
    public $timestamps = false;
}

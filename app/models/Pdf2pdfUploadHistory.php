<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Pdf2pdfUploadHistory extends Model
{
    protected $table = 'file_records';

    protected $fillable = ['template_name', 'record_unique_id', 'total_records', 'source_file', 'pdf_page', 'userid', 'created_at'];

}

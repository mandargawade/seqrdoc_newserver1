<?php

namespace App\models\pdf2pdf;

use Illuminate\Database\Eloquent\Model;

class TemplateMaster extends Model
{
    protected $table = 'uploaded_pdfs';

    protected $fillable = [
        'file_name','extractor_details','placer_details', 'ep_details','template_name', 'pdf_page','print_bg_file','print_bg_status','verification_bg_file','verification_bg_status','created_at','generated_by','publish'
    ];


}

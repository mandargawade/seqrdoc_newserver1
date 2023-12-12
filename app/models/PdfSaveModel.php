<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class PdfSaveModel extends Model
{
    protected $table = 'pdf_save_demo';
    protected $fillable = ['id','pdf'];
}

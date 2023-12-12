<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class ImageDeleteHistory extends Model
{
    protected $table = 'image_delete_history';

    protected $fillable = [
        'admin_id','image_name', 'date_time','template_name', 'created_at',
    ];
}

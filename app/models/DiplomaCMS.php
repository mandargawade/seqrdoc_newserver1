<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiplomaCMS extends Model
{
    public $table="diploma_cms";
    public $primaryKey="id";
    public $fillable=['college_number','result'];
}

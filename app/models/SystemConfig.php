<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class SystemConfig extends Model
{
    protected $table="system_config";

    protected $fillable=['printer_name','print_color','timezone','auto_logout','smtp','port','sender_email','password','sandboxing','varification_sandboxing','file_aws_local'];
    
}

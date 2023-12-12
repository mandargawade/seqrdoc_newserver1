<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;


class CardSerialNumbers extends Authenticatable
{
    protected $table = 'card_serial_numbers';

     protected $fillable = [
        'id','template_name','starting_serial_no','ending_serial_no','card_count','next_serial_no','publish','created_at','updated_at'];

    public $timestamps = false;
    
}

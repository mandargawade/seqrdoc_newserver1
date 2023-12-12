<?php

namespace App\models\raisoni;

use Illuminate\Database\Eloquent\Model;

class TemplateRateMaster extends Model
{
    
     protected $table = 'template_master';

    protected $fillable = [
        'template_name','actual_template_name','template_desc', 'bg_template_id','background_template_status', 'template_size','width','height','unique_serial_no','lock_element','created_by','updated_by','status','create_refresh','scanning_fee'];
}
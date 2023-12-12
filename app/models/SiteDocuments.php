<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class SiteDocuments extends Model
{
	protected $table = 'sites_superdata';


	protected $fillable = [
    	'id','site_id','sites_name','template_number','active_documents','inactive_documents','total_verifier','total_scanned','last_genration_date'
    ];


	public $timestamps = true;
}


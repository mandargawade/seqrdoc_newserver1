<?php

namespace App\Models\Demo;

use Illuminate\Database\Eloquent\Model;

class SitesSuperdata extends Model
{
	protected $connection = 'mysql1';
    public $table="sites_superdata";
    public $primaryKey="site_id";
    public $fillable=['sites_name','template_number','inactive_template_number','active_documents','inactive_documents','total_verifier','total_scanned','last_genration_date','avg_per','custom_templates'];
}

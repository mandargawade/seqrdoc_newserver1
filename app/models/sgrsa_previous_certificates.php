<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class sgrsa_previous_certificates extends Model
{
    protected $table = 'previous_certificates';
	public $timestamps = false;
    protected $fillable = [
        'id', 'vehicle_reg_no', 'chassis_no', 'date_of_inspection', 'date_of_expiry', 'certificate_no', 'town', 'unit_sr_no', 'date_of_installation', 'agent_name', 'tele_no', 'date_of_issue', 'model', 'make', 'vehicle_owner', 'business_reg', 'pin_no', 'vat_no', 'company_address', 'certify_by', 'engine_no', 'po_box', 'code', 'email', 'status', 'notification', 'supplier_id', 'admin_id', 'created_date', 'publish'
    ];
}

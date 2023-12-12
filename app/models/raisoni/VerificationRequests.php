<?php

namespace App\models\raisoni;

use Illuminate\Database\Eloquent\Model;

class VerificationRequests extends Model
{
    protected $guard = 'admin';
    protected $table = 'verification_requests';

    protected $fillable = [
        'request_number','user_id','institute', 'degree','branch', 'student_name','registration_no','passout_year','name_of_recruiter','offer_letter','no_of_documents','total_amount','payment_status','device_type','verification_status','updated_by'
    ];
    public $timestamps = false;
}

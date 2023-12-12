<?php

namespace App\models\raisoni;

use Illuminate\Database\Eloquent\Model;

class RefundTransaction extends Model
{
    protected $guard = 'admin';
    protected $table = 'refund_transactions';

    protected $fillable = [
        'transaction_ref_id','request_id','request_no', 'transaction_id_payu','amount', 'status','status_code','pg_error_code','bank_ref_num','bank_arn','settlement_id','utr_no','txn_type','user_id'
    ];
}

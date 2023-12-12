<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class BlockChainMintData extends Model
{
    protected $table = 'bc_mint_data';

    protected $fillable = [
    	'id','txn_hash','gas_fees','token_id','key','created_at'
    ];

    public $timestamps = false;
}

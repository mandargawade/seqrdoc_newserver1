<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class BackgroundTemplateMaster extends Model
{
    protected $table = 'background_template_master';

    protected $fillable = [
        'background_name',
        'image_path', 
        'width',
		'height', 
		'status',
		'created_by',
		'updated_by',
    ];

    public $timestamps = true;

    public static $rules = [
    	'background_name' =>'required | unique:background_template_master',
    	'image_path' =>'required | image | mimes:jpeg,png,jpg',
    	'width' =>'required | numeric|min:50|max:594',
        'height' =>'required | numeric|min:50|max:841',
        'status' =>'required',
    ];

    public static $messages = [
    	'background_name.required' =>"Background Template Name required",
    	'image_path.image' =>'Image must be an Image(jpeg, png, jpg)',
    	'image_path.required' =>'Image required',
    	'width.required' =>'Width required',
    	'height.required' =>'Height required',
    	'status.required' =>'Satus required',
    ];
}

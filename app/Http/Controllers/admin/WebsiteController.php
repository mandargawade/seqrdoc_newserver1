<?php
/**
 *
 *  Author : Mandar Gawade
 *   Date  : 8/03/2022
 *   Use   : update & store Mail Credential of production
 *
**/
namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WebsiteController extends Controller
{
    public function index($url) {
        return $url;
    }
}
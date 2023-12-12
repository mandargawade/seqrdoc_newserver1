<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 27/11/2019
 *   Use   : listing of Profile & Changes Password
 *
**/
namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\models\Admin;
use Auth;
use Illuminate\Http\Request;
use Hash;
class ManualController extends Controller
{
     /**
     * Display a listing of the Profile.
     */
    public function index()
    {
      return view('admin.manual.index');
    }
    
}

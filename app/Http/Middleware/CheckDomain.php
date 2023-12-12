<?php
   
namespace App\Http\Middleware;
   
use Closure;
use  Illuminate\Http\Request;
use App\Models\Site;
//use App\models\Demo\Site as DemoSite;
use Helper;
class CheckDomain
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
    	error_reporting(E_ALL ^ E_NOTICE);
	    $domain = \Request::getHost();
	    //$domain ="demo.seqrdoclocal.com";
	   /* if($domain=='secdoc.seqrdoc.com'){
		    $siteData = DemoSite::select('start_date','end_date')->where('site_url',$domain)->first();

		    if($siteData){
		    	$sitename=date('Y-m-d');
		    	$site_url = explode(".",$_SERVER['SERVER_NAME']);
		      	$sitename = ucfirst($site_url[0]);
		    	return response()->view('site_status',compact('sitename'));
		    }else{
		    	$site_url = explode(".",$_SERVER['SERVER_NAME']);
		      	$sitename = ucfirst($site_url[0]);
		    	return response()->view('site_status',compact('sitename'));
		    }
		}*/

		
		$super_admin = Site::where('site_url',$domain)->value('status');
		
		if($domain!="apponly.seqrdoc.com"&&$domain!="master.seqrdoc.com"){
			
		if($super_admin != 1 || $super_admin != "1"){

		 	$site_id=Helper::GetSiteId($_SERVER['SERVER_NAME']);
	      	$site_url = explode(".",$_SERVER['SERVER_NAME']);
	      	$sitename = ucfirst($site_url[0]);
			return response()->view('site_status',compact('sitename'));
		
		}
		
		}else{
			if($domain=="master.seqrdoc.com"){
			 $actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";	
			if($actual_link=="https://master.seqrdoc.com"||$actual_link=="http://master.seqrdoc.com"||$actual_link=="https://master.seqrdoc.com/"||$actual_link=="http://master.seqrdoc.com/"||$actual_link=="https://master.seqrdoc.com/admin/login"||$actual_link=="http://master.seqrdoc.com/admin/login"){
				return redirect('https://seqrdoc.com');
				//echo "redirect";
				//exit;
			}

			//exit;

			}
		}
		
		$subdomain = explode('.', $domain);
		if($subdomain[0] == 'demo')
		{
			$dbName = 'seqr_'.$subdomain[0];
		}
		else if($subdomain[0] == 'apponly'||$subdomain[0] == 'master')
		{
			$dbName = 'seqr_demo';
		}
		else{
			$dbName = 'seqr_d_'.$subdomain[0];
		}
		// if (\DB::statement('create database ' . $dbName) == true) {
		// }
		// else{
		// 	$dbName = 'seqr_demo';
		// }
		// dd($domain);
		\DB::disconnect('mysql'); 
		\Config::set("database.connections.mysql", [
			'driver'   => 'mysql',
		    'host'     => \Config::get('constant.DB_HOST'),
            "port" => \Config::get('constant.DB_PORT'),
            'database' => $dbName,
            'username' => \Config::get('constant.DB_UN'),
            'password' => \Config::get('constant.DB_PW'),
		    "unix_socket" => "",
		    "charset" => "utf8mb4",
		    "collation" => "utf8mb4_unicode_ci",
		    "prefix" => "",
		    "prefix_indexes" => true,
		    "strict" => true,
		    "engine" => null,
		    "options" => []
		]);
		 \DB::reconnect();
    	// dd(66);
        return $next($request);
    }
}
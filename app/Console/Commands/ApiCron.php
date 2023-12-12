<?php

namespace App\Console\Commands;


use Illuminate\Http\Request;

use App\Models\ApiTracker;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ApiTrakerExport;
use Mail;
use Session;
use App\Exports\TemplateMasterExport;
use App\Jobs\SendMailJob;
use App\models\Site;
use Illuminate\Console\Command;
use DB;

class ApiCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:day';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'API tracker email to software@scube.net.in';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {	

    	$sites = Site::where("status","1")->get()->toArray();
		

		foreach ($sites as $key => $value) {
			
			$subdomain = explode('.', $value['site_url']);
			
			if($subdomain[0] == 'demo')
			{
				$dbName = 'seqr_'.$subdomain[0];
			}
			else{
				$dbName = 'seqr_d_'.$subdomain[0];
			}

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

			
			 
		 	$iname = ucwords($subdomain[0]);
		 	 DB::statement(DB::raw('set @rownum=0'));   
	        
	        $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'id','request_url','client_ip','created','request_method','header_parameters','request_parameters','response_parameters','response_time'];
	        $date = date('Y-m-d') . ' 17:59:00';
	        $previousDate = date('Y-m-d', strtotime("-1 days")) . ' 18:00:00';
	        $apiData = ApiTracker::select($columns)->where("status","failed")->where('created','>=',$previousDate)->where('created','<=',$date)->get()->toArray();

	        if(count($apiData))
	        {
	            try{
	                $contents = Excel::raw(new ApiTrakerExport(), \Maatwebsite\Excel\Excel::XLSX);
	                
	                $emails = ['tester2@scube.net.in', 'dev9@scube.net.in','dev12@scube.net.in'];
	                Mail::send('mail.apitracker', ['today' => $date, 'previousDate' => $previousDate, 'instance' => $iname], function ($m) use ($contents, $apiData, $iname, $date,$emails){
	                    $m->from('info@seqrdoc.com', 'SeQR');
	                     
	                    $m->to($emails)->subject($iname.' SeQR Docs //'.count($apiData).' Failed API Log // '.date('Y-m-d').'.');    
	                    
	                    $m->cc('software@scube.net.in', 'Bhavin Shah');
	                    
	                    $m->attachData($contents, $iname.'_SeQR_Failed_API_'.date('Y-m-d').'.xlsx');
	                });
	                 
	            }catch(\Exception $e){
	               echo 'Message: ' .$e->getMessage();
	            }
	        }else{
	            echo "no record"."<br>";
	        }
	        
		}
		    
    }
}

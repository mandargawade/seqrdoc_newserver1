<?php

namespace App\Http\Controllers\verify;

use App\Http\Controllers\Controller;
use App\models\SessionManager;
use Illuminate\Http\Request;
use Auth;

class SeesionManagerController extends Controller
{
    //

    public function index(){

    	return view('verify.sessionmanager');
    }

    public function getData(Request $request){

    	if($request->type != null){

    		if($request->type == 'getSessions'){

    			$user_id = Auth::guard('webuser')->user()->id;
    			$result = SessionManager::where(['user_id'=>$user_id,'is_logged'=>1])
    			->orderBy('login_time', 'DESC')
    			->get()->toArray();

    			$html = '';

    			if(!empty($result)){

    				$html .= '<div class="list-group">';
					$html .= "<div class='list-group-item list-group-item-info text-center'> <h5><b>Active Logins</b></h5></div>";

					foreach($result as $sess){

						$login_time = date('d.m.y h:ia',strtotime($sess['login_time']));
						if($sess['device_type'] != 'webApp' and $sess['device_type'] != 'webAdmin'){
							$icon = "fa-tablet";
						}else{
							$icon = "fa-tv";
						}
						$html.= "
						<div class='list-group-item'>
							<div class='clearfix'>
								<div class='pull-left' style='padding-top:8px'>
									<i class='fa fa-fw fa-2x theme {$icon}'></i> &nbsp;&nbsp;
								</div>
								<div class='pull-left'>
									<b>{$sess['device_type']}</b> <span style='color:#777'>[ {$sess['ip']} ]</span> 
									<br/>
									<small>Logged in at {$login_time}</small>
								</div>
								<div class='pull-right'>
									<i class='fa fa-fw fa-3x green fa-sign-out logout_device' data-sesskey='{$sess['session_id']}' data-toggle='tooltip' title='Logout' data-placement='right'></i>
								</div>
							</div>
						</div>";
					}

					$html .= "<div class='list-group-item text-center'>
					<button class='btn btn-danger logout_all' data-id='{$sess['user_id']}'><i class='fa fa-fw fa-lg fa-sign-out'></i> Logout from all devices</button>
					</div>";
					$html .= '</div><br><br>';
    			}

    			$result = SessionManager::where(['user_id'=>$user_id,'is_logged'=>0])
    					  ->orderBy('logout_time', 'DESC')->offset(0)->limit(10)
    					  ->get()->toArray();

    			if(!empty($result)){

    				$html .= '<div class="list-group">';
					$html .= "<div class='list-group-item list-group-item-info text-center'> <h5><b>Last 10 Logins</b></h5></div>";

					foreach($result as $sess){
						$login_time = date('d.m.y h:ia',strtotime($sess['login_time']));
						$logout_time = date('d.m.y h:ia',strtotime($sess['logout_time']));
						
						if($sess['device_type'] != 'webApp' and $sess['device_type'] != 'webAdmin'){
							$icon = "fa-tablet";
						}else{
							$icon = "fa-tv";
						}
						$html.= "
						<div class='list-group-item'>
							<div class='clearfix'>
								<div class='pull-left' style='padding-top:8px'>
									<i class='fa fa-fw fa-2x theme {$icon}'></i> &nbsp;&nbsp;
								</div>
								<div class='pull-left'>
									<b>{$sess['device_type']}</b> <span style='color:#777'>[ {$sess['ip']} ]</span> 
									<br/>
									<span style='color:#555'><small>Login: {$login_time}</small> | 
									<small>Logout: {$logout_time}</small></span>
								</div>
								<div class='pull-right'>
									
								</div>
							</div>
						</div>";
					}
					$html .= '</div>';

    			}

    			echo $html;
    			exit();

    		}

    		if($request->type == 'logoutSingle'){

    			$user_id = Auth::guard('webuser')->user()->id;
    			$session_id = $request->sesskey;
    			$logout_time = date('Y-m-d H:i:s');

    			SessionManager::where('session_id',$session_id)
				->where('user_id',$user_id)
				->update(['logout_time'=>$logout_time,'is_logged'=>0]);

				if($session_id == session_id()){
					session_destroy();
					echo '0';
				}else{
					echo '1';
				}
	    	}

	    	if($request->type == 'logoutAll'){

	    		$user_id = auth('webuser')->user()->id;
				$logout_time = date('Y-m-d H:i:s');
				
				SessionManager::where('user_id',$user_id)
					->update(['logout_time'=>$logout_time,'is_logged'=>0]);

				
	    	}
    	}
    }
}

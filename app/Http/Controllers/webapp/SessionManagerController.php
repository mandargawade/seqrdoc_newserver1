<?php

namespace App\Http\Controllers\webapp;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\SessionManager;

class SessionManagerController extends Controller
{
	public function index(Request $request){

		return view('webapp.sessionManager.index');
	}

	public function getSessions(){

		$user_id = auth('admin')->user()->id;

		$sessionManager = SessionManager::where(['user_id'=>$user_id,'is_logged'=>1])->orderBy('login_time','DESC')->get()->toArray();

		$html = '';
		if(!empty($sessionManager)){
			$html .= '<div class="list-group">';
			$html .= "<div class='list-group-item list-group-item-info text-center'> <h5><b>Active Logins</b></h5></div>";
			foreach($sessionManager as $key => $sess){
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
		
	

		$query = SessionManager::where(['user_id'=>$user_id,'is_logged'=>0])->orderBy('logout_time','desc')->get()->toArray();
		
		
		if(!empty($query)){
			$html .= '<div class="list-group">';
			$html .= "<div class='list-group-item list-group-item-info text-center'> <h5><b>Last 10 Logins</b></h5></div>";
			foreach($query as $sess){
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

	public function logoutSingle(){

		$user_id = auth('admin')->user()->id;
		$session_id = $_POST['sesskey'];

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
	public function logoutAll(){

		$user_id = auth('admin')->user()->id;
		$logout_time = date('Y-m-d H:i:s');
		
		SessionManager::where('user_id',$user_id)
			->update(['logout_time'=>$logout_time,'is_logged'=>0]);

		echo '1';
		session_destroy();
		exit();
	}
}

?>
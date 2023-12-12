<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\models\SessionManager;
use Illuminate\Http\Request;
use App\models\InstituteMaster;
use App\models\Admin;
use App\models\User;
use Auth;
use DB;
use Illuminate\Support\Facades\Session;
class SessionManagerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if($request->ajax()){
            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
               
                $search     = $request->input('sSearch');
                $where_str .= " and (user_table.username like \"%{$search}%\""
                . " or user_table.fullname like \"%{$search}%\""
                . " or session_manager.ip like \"%{$search}%\""
                . " or session_manager.login_time like \"%{$search}%\""
                . " or session_manager.logout_time like \"%{$search}%\""
                . " or session_manager.device_type like \"%{$search}%\""
                . " or session_manager.id like \"%{$search}%\""
                . ")";
            }  
           $status=$request->get('status');
           DB::statement(DB::raw('set @rownum=0'));   
            if($status==1)
            {
                $status=1;
                $where_str.= " and (session_manager.is_logged =$status)";
                $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'user_table.username as name','user_table.fullname as fullname','session_manager.ip','session_manager.login_time','session_manager.logout_time','session_manager.device_type','session_manager.id'];
            }
            else if($status==0)
            {
                $status=0;
                $where_str.= " and (session_manager.is_logged =$status)";
                $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'user_table.username as name','user_table.fullname as fullname','session_manager.ip','session_manager.login_time','session_manager.logout_time','session_manager.device_type','session_manager.id'];
            }                                               
 
            $auth_site_id=Auth::guard('admin')->user()->site_id;
            $session_manager_count = SessionManager::select($columns)
            ->leftjoin('user_table','session_manager.user_id','=','user_table.id')
            ->whereRaw($where_str, $where_params)
             ->where('session_manager.site_id',$auth_site_id)
            ->count();

            $session_manager_list = SessionManager::select($columns)
            ->leftjoin('user_table','session_manager.user_id','=','user_table.id')
             ->where('session_manager.site_id',$auth_site_id)
            ->whereRaw($where_str, $where_params);

           
            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $session_manager_list = $session_manager_list->take($request->input('iDisplayLength'))
                ->skip($request->input('iDisplayStart'));
            }          

            if($request->input('iSortCol_0')){
                $sql_order='';
                for ( $i = 0; $i < $request->input('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->input('iSortCol_' . $i)];
                    if(false !== ($index = strpos($column, ' as '))){
                        $column = substr($column, 0, $index);
                    }
                    $session_manager_list = $session_manager_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            } 
            $session_manager_list = $session_manager_list->get();
             
            $response['iTotalDisplayRecords'] = $session_manager_count;
            $response['iTotalRecords'] = $session_manager_count;
            $response['sEcho'] = intval($request->input('sEcho'));

            foreach ($session_manager_list->toArray() as $key => $value) {

                $check_name = $this->checkUserType($value);
                $in_name = $this->getInstituteName($value);
                $session_manager_list[$key]['in_name'] = $in_name;
                $session_manager_list[$key]['name'] = $check_name['username'];
                $session_manager_list[$key]['fullname'] = $check_name['fullname'];
            }
            $response['aaData'] = $session_manager_list;
            
            return $response;
        }

        return view('admin.sessionmanager.index');
    }
    // get insttitute name
    public function getInstituteName($value){
        $institute_name_count = InstituteMaster::where('username',$value['name'])->count();
        if($institute_name_count > 0){
            return "institute admin";
        }else{
            return "student";
        }
    }
    public function checkUserType($value){



        $session_manager = SessionManager::where('id',$value['id'])->value('user_id');
        if($value['device_type'] == "webAdmin"){

            $admin_table = Admin::where('id',$session_manager)->first();
            
            $data = [
                'fullname'=>$admin_table['fullname'],
                'username'=>$admin_table['username'],
            ];

        }else{


            $user_table = User::where('id',$session_manager)->first();

            $data = [
                'fullname'=>$user_table['fullname'],
                'username'=>$user_table['username'],
            ];
        }
        return $data;
    }

     /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    // Session Manager logout user
    public function destroy($id)
    {
        $session_manager = SessionManager::find($id);

        $session_manager->is_logged = 0;
        $session_manager->logout_time = date('Y-m-d H:i:s');
        $session_manager->save();

        return response()->json(['success'=>true]);
    }   
    public function getSessionData(Request $request)
    {
      $status=Session::get('session_id');
     
      $session_val = $request->session()->get('session_id');
      if($session_val){
        return  response()->json(['success'=>true,'session_id'=>$session_val]);
      }else{
        return  response()->json(['success'=>false]);
      }
     
    
    }
} 
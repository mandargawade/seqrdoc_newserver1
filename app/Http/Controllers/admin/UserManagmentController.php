<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 2/11/2019
 *   Use   : listing of Webuser & update and store Webuser data
**/
namespace App\Http\Controllers\admin;

use App\Events\UserManagmentEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagementRequest;
use App\models\SessionManager;
use App\models\User;
use App\models\Role;
use DB;
use Illuminate\Http\Request;
use Auth;
class UserManagmentController extends Controller
{
    /**
     * Display a listing of the Webuser.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain); //$subdomain[0]="icat"   
        if($request->ajax()){
            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and ( username like \"%{$search}%\""
                . " or fullname like \"%{$search}%\""
                . " or email_id like \"%{$search}%\""
                . " or mobile_no like \"%{$search}%\""
                . ")";
            }  
           $status=$request->get('status');
            if($status==1)
            {
                $status='1';
                $where_str.= " and (user_table.status =$status)";
            }
            else if($status==0)
            {
                $status='0';
                $where_str.=" and (user_table.status= $status)";
            }

            $auth_site_id=Auth::guard('admin')->user()->site_id;                                               
              //for serial number
            $iDisplayStart=$request->input('iDisplayStart'); 
            DB::statement(DB::raw('set @rownum='.$iDisplayStart));
            //DB::statement(DB::raw('set @rownum=0'));   
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'id','username','fullname','email_id','mobile_no','device_type','created_at','updated_at','is_verified','verify_by'];

            $font_master_count = User::select($columns)
                 ->whereRaw($where_str, $where_params)
                 ->where('publish',1)
                 ->where('site_id',$auth_site_id)
                 ->count();
  
            $fontMaster_list = User::select($columns)
                   ->where('publish',1)
                   ->where('site_id',$auth_site_id)
                   ->whereRaw($where_str, $where_params);
      
            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $fontMaster_list = $fontMaster_list->take($request->input('iDisplayLength'))
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
                    $fontMaster_list = $fontMaster_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            } 
            $fontMaster_list = $fontMaster_list->get();
             
            $response['iTotalDisplayRecords'] = $font_master_count;
            $response['iTotalRecords'] = $font_master_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $fontMaster_list->toArray();
            
            return $response;
        }
        return view('admin.userManagement.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created User in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    // store user information 
    public function store(UserManagementRequest $request)
    {
        $user_data=$request->all();
        event(new UserManagmentEvent($user_data));
        return response()->json(['success'=>true]);
    }

    /**
     * Display the specified Webuser.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        
      

        $domain =$_SERVER['HTTP_HOST'];
            $subdomain = explode('.', $domain);
            if($subdomain[0] == "demo"||$subdomain[0] == "raisoni"){
                  $user_data=User::select('username','fullname','l_name','email_id','mobile_no','device_type','created_at','registration_no','user_type','working_sector','address','institute','degree','branch','passout_year')->where('id',$id)->get()->toArray();
                 // print_r($user_data);
                   if(!empty($user_data[0]['degree'])){
                        $degree = DB::table('degree_master')->where('id', $user_data[0]['degree'])->first();
                        $user_data[0]['degree']= $degree->degree_name;

                    }

                    if(!empty($user_data[0]['branch'])){
                        $branch = DB::table('branch_master')->where('id', $user_data[0]['branch'])->first();
                        $user_data[0]['branch']= $branch->branch_name_long;

                    }


            }else{
                  $user_data=User::select('username','fullname','email_id','mobile_no','device_type','created_at')->where('id',$id)->get()->toArray();
            }
        $user_data=head($user_data);
        
        $last_login_time=SessionManager::select('login_time')
                          ->where('user_id',$id)
                          ->orderBy('id','desc')
                          ->first();
                          
        
     
        $user_data['login_time']=$last_login_time['login_time']; 

       
        
        return $user_data;
    }

    /**
     * Show the form for editing the specified user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    // get user information specific id
    public function edit($id)
    {
        $user_data=User::where('id',$id)->get()->toArray();
        $user_data=head($user_data);
        return $user_data;
    }

    /**
     * Update the specified user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UserManagementRequest $request, $id)
    {
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);       
        $user_data=$request->all();
        event(new UserManagmentEvent($user_data));
        return response()->json(['success'=>true]);
    }

    /**
     * Remove the specified user from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
    
        $user_data=User::where('id',$id)->delete();
        return $user_data ? response()->json(['success'=>true]) :'false';
    }
    
    // get All RoleName
    public function getRoleName(Request $request)
    {
          $site_id=Auth::guard('admin')->user()->site_id;

          $role_name=Role::select('id','name')
                      ->where('site_id',$site_id)
                      ->get()
                      ->toArray();
          
          return $role_name;          
    }
    

    public function getDegreeMaster(Request $request)
    {
          $degree = DB::table('degree_master')->get()
                      ->toArray();
          
            $optionStr = '<option value="" readonly selected>Select Degree</option>';
            if ($degree) {

                foreach ($degree as $readValue) {
                   /* if (isset($_POST['dropdownType']) && $_POST['dropdownType'] == "value") {
                        $optionStr .= '<option value="' . $readValue['degree_name'] . '">' . $readValue['degree_name'] . '</option>';
                    } else {*/
                        $optionStr .= '<option value="' . $readValue->id . '">' . $readValue->degree_name . '</option>';
                    //}

                }
            }
            $message = array('type' => 'success', 'message' => 'success', 'data' => $optionStr);
          
          
          return response()->json($message);          
    }

    public function getBranchMaster(Request $request)
    {
          $degree = DB::table('branch_master')->where('degree_id', $_POST['degree_id'])->get()
                      ->toArray();
          
            $optionStr = '<option value="" readonly selected>Select Branch</option>';
            if ($degree) {

                foreach ($degree as $readValue) {
                   /* if (isset($_POST['dropdownType']) && $_POST['dropdownType'] == "value") {
                        $optionStr .= '<option value="' . $readValue['degree_name'] . '">' . $readValue['degree_name'] . '</option>';
                    } else {*/
                        $optionStr .= '<option value="' . $readValue->id . '">' . $readValue->branch_name_long . '</option>';
                    //}

                }
            }
            $message = array('type' => 'success', 'message' => 'success', 'data' => $optionStr);
          
          
          return response()->json($message);          
    }

    public function logout(Request $request){
        
        $id = $request['user_id'];
        $user = Auth::guard('webuser')->user();
        
        $userToLogout = User::find($id);
       
        Auth::guard('webuser')->setUser($userToLogout);
        
        Auth::guard('webuser')->logout();

        
      //  Auth::guard('webuser')->setUser($user);
        $date=date('Y-m-d H:i:s');
        SessionManager::where('user_id',$id)->where('is_logged',1)->update(['is_logged'=>0,'logout_time'=>$date]);
        return response()->json(['success'=>true]);

    }
}

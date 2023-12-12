<?php

namespace App\Http\Controllers\superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdminRoleRequest;
use App\Jobs\SuperAdminRolePermissionJob;
use App\Models\Site;
use App\Models\SitePermission;
use App\models\AclPermission;
use App\models\SuperAdmin;
use App\models\SuperAdminLogin;
use App\models\SystemConfig;
use App\models\UserPermission;
use App\models\RolePermission;
use App\models\Role;
use App\models\PaymentGateway;
use App\models\PaymentGatewayConfig;
use App\models\Superapp\InstanceList;
use App\models\SiteDocuments;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Admin;
use Hash;
use App\Helpers\CoreHelper;
use Helper;
use \RecursiveIteratorIterator;
use \RecursiveDirectoryIterator;
class SitePermissionController extends Controller
{
    
    public function index(Request $request){

       if($request->ajax()){

            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and ( site_url like \"%{$search}%\" OR state like \"%{$search}%\" OR country like \"%{$search}%\" OR organization_category like \"%{$search}%\""
               
                . ")";
            }  
           $status=$request->get('status');
            if($status==1)
            {
                $status='1';
                $where_str.= " and (sites.status =$status)";
            }
            else if($status==0)
            {
                $status='0';
                $where_str.=" and (sites.status= $status)";
            } 
                                                    
             //for serial number
            DB::statement(DB::raw('set @rownum=0'));   
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'site_url',DB::raw("CONCAT(state,', ',country) AS location"),'organization_category','updated_at','site_id'];

            $columnsOrder = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'site_url','country','organization_category','updated_at','site_id'];


            $font_master_count = Site::select($columns)
                ->whereRaw($where_str, $where_params)
               
                ->count();
  
            $fontMaster_list = Site::select($columns)
                 
                 ->whereRaw($where_str, $where_params);
      
            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $fontMaster_list = $fontMaster_list->take($request->input('iDisplayLength'))
                ->skip($request->input('iDisplayStart'));
            }          

            if($request->input('iSortCol_0')){
                $sql_order='';
                for ( $i = 0; $i < $request->input('iSortingCols'); $i++ )
                {
                    $column = $columnsOrder[$request->input('iSortCol_' . $i)];
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
        return view('superadmin.sitePermission.index');
    }
     /**
     * Show the form for creating a new Role.
     *
     * @return view response
     */
     public function create()
    {
       // $user_permission = AclPermission::getPermission();
          $user_permission = DB::table("acl_permissions_group")->select('main_route','sub_route','route_name','group_name','visibility_on','step_id','sub_step_id','main_menu_no')->where('visibility_on','1')->where('is_system','0')->where('is_super_admin','0')->
          	  orderBy('main_menu_no','asc')->orderBy('step_id','asc')->orderBy('sub_step_id','asc')->get()->toArray();
        
        /*print_r($user_permission);
        exit;*/
        return view('superadmin.sitePermission.create',compact('user_permission'));
    }

    /**
     * Store a newly created Role in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(SuperAdminRoleRequest $request)
    {   

        $pdf_storage_path = $request->get('pdf_storage_path');
        $start_date = $request['start_date'];
        $end_date = $request['end_date'];

        $start_date_check =strtotime($start_date);
        $end_date_check = strtotime($end_date);
        if($start_date_check > $end_date_check){
            return response()->json(array('success' => false,'action'=>'added','message'=>'Start date must be smaller than end date'),200);
        }
        set_time_limit(240);
       //   exit;
        $site_url = $request->get('site_url');
       // $role_permission = $request->get('permission');
         $role_permission_group = $request->get('permissions');
        //print_r($role_permission_group);
        $user_permission = DB::table("acl_permissions_group")
                               ->select('main_route','sub_route','route_name','group_name','visibility_on','step_id','sub_step_id','main_menu_no')
                               ->where('visibility_on','1')->where('is_system','0')->where('is_super_admin','0')
                               ->orderBy('main_menu_no','asc')->orderBy('step_id','asc')->orderBy('sub_step_id','asc')
                               ->get()->toArray();
        

       // print_r($role_permission_group);

        $main_route_permission = array_column($user_permission, 'main_route');
        //print_r($main_route_permission);

        
        $role_permission_group = array_values(array_intersect($role_permission_group, $main_route_permission));
        
        $permissionIds=array();
        foreach ($role_permission_group as $readPermission) {

//echo $readPermission;
            $acl_permission = DB::table("acl_permissions")->select('id','route_name')->where('route_name',$readPermission)->get()->toArray();
             if(count($acl_permission)>0){
           // print_r($acl_permission);
                if(!in_array($acl_permission[0]->id, $permissionIds)){
                    array_push($permissionIds, $acl_permission[0]->id);
                }

                $acl_permission_rel = DB::table("acl_permissions_group")->select('sub_route')->where('main_route',$readPermission)->where('sub_route','!=',$readPermission)->where('is_super_admin','0')->where('is_system','0')->get()->toArray();

               if(count($acl_permission_rel)>0){
                    foreach ($acl_permission_rel as $readSubPermission) {
                       // print_r($readSubPermission);

                         $acl_permission = DB::table("acl_permissions")->select('id','route_name')->where('route_name',$readSubPermission->sub_route)->get()->toArray();
                            if(!in_array($acl_permission[0]->id, $permissionIds)){
                        array_push($permissionIds, $acl_permission[0]->id);
                    }
                    }
               }
            }
        }

        $acl_permission_sys = DB::table("acl_permissions_group")->select('sub_route')->where('is_super_admin',0)->where('is_system',1)->get()->toArray();
           if(count($acl_permission_sys)>0){
                foreach ($acl_permission_sys as $readSubPermission) {
                     $acl_permission = DB::table("acl_permissions")->select('id','route_name')->where('route_name',$readSubPermission->sub_route)->get()->toArray();
                       if(!in_array($acl_permission[0]->id, $permissionIds)){
                    array_push($permissionIds, $acl_permission[0]->id);
                    }
                }
           }
       /* print_r($permissionIds);
        exit;*/
        $role_permission =$permissionIds;

        $create_instance=$request->get('site_url');
        $create_instance=explode('.',$create_instance);
        $create_instance=$create_instance[0];

        //create the role 
        $role_save = new Site();
        $role_save->start_date = date('Y-m-d',strtotime($request->get('start_date')));
        $role_save->end_date = date('Y-m-d',strtotime($request->get('end_date')));
        $role_save->license_key = $request->get('license_key');
        $role_save->site_url = $site_url;
        $role_save->status = $request->get('status');
        $role_save->apple_app_url = $request->get('apple_app_url');
        $role_save->android_app_url = $request->get('android_app_url');
        $role_save->pdf_storage_path = $request->get('pdf_storage_path');
        $role_save->country = $request->get('country');
        if($request->get('country')=="India"){
            $role_save->state = $request->get('state');    
        }else{
            $role_save->state = "";
        }  
        $role_save->organization_category = $request->get('organization_category');
        $role_save->save();

        $site_id  = $role_save->site_id;
        if(!empty($site_id)){

            $site_superdata = new SiteDocuments();
            $site_superdata->site_id = $site_id;
            $site_superdata->sites_name = $site_url;
            $site_superdata->template_number = 0;
            $site_superdata->active_documents = 0;
            $site_superdata->inactive_documents = 0;
            $site_superdata->total_verifier = 0;
            $site_superdata->total_scanned = 0;
            $site_superdata->save();

        }else{
             return response()->json(array('success' => false,'action'=>'Site not created'),200);
        }

         

        $role_permission_data = ['role_id'=>$site_id,'role_permission'=>$role_permission];
        
        $value = 0;
        if(isset($request['value'])){
          $value = $request['value'];
        }
        $superAdmin = new SuperAdmin;
        $superAdmin->site_id = $site_id;
        $superAdmin->property = 'print_limit';
        $superAdmin->value = $value;
        $superAdmin->save();
        $this->dispatch(new SuperAdminRolePermissionJob($role_permission_data));

        //fetch admin tabnle data from main db table
        $admin_data = Admin::all();
        //password is in hidden so when converting to array password is not coming so make visible it
        $admin_data->makeVisible('password')->toArray();

        //insert data 
        $admin_data_array = $admin_data->toArray();

        //acl permission insert data
        $acl_permission_data = AclPermission::all()->toArray();

        //Updated
        foreach ($acl_permission_data as $key => $value) {
            if(!in_array($value['id'],$role_permission)){
                unset($acl_permission_data[$key]);
            }
        }
        /*print_r($acl_permission_data);
        exit;*/
        //super admin insert data
        $super_admin_data = SuperAdminLogin::all();
        $super_admin_data->makeVisible('password')->toArray();
        $super_admin_data_array = $super_admin_data->toArray();

        //system config insert data
        $system_config_data = SystemConfig::get()->first()->toArray();

       

        //user permission data
        $user_permission_data = UserPermission::where('user_id',1)->get()->toArray();


        //role permission data
        $rolePermissionData = RolePermission::where('role_id',1)->get()->toArray();


         //Updated
        foreach ($rolePermissionData as $key => $value) {
            if(!in_array($value['id'],$role_permission)){
                unset($rolePermissionData[$key]);
            }
        }
       // print_r($rolePermissionData);
        //exit;

        //site permission data
        $site_data = Site::where('site_url',$site_url)->first()->toArray();


        //super admin data
        $super_admin_data = SuperAdmin::where('site_id',$site_id)->first()->toArray();

        //site permission data
        $site_permission_data = SitePermission::where('site_id',$site_id)->get()->toArray();

        //payment gateway data
        $payment_gateway_data = PaymentGateway::first()->toArray();

        //payment gateway config data
        $payment_gateway_config_data = PaymentGatewayConfig::first()->toArray();


        $dbName = 'seqr_d_'.$create_instance;
        if (\DB::statement('create database ' . $dbName) == true) {
            $new_connection = 'new';
            $nc = \Illuminate\Support\Facades\Config::set('database.connections.' . $new_connection, [
                'driver'   => 'mysql',
                'host'     => \Config::get('constant.DB_HOST'),
                "port" => \Config::get('constant.DB_PORT'),
                'database' => $dbName,
                'username' => \Config::get('constant.DB_UN'),
                'password' => \Config::get('constant.DB_PW'),
                /*'username' => 'developer',
                'password' => 'developer',*/
                "unix_socket" => "",
                "charset" => "utf8mb4",
                "collation" => "utf8mb4_unicode_ci",
                "prefix" => "",
                "prefix_indexes" => true,
                "strict" => true,
                "engine" => null,
                "options" => []
            ]);
        }

        //creating tables with structure and data
        $this->createTableDynamically($new_connection,$admin_data_array,$acl_permission_data,$super_admin_data_array,$system_config_data,$user_permission_data,$site_data,$site_id,$super_admin_data,$site_permission_data,$site_url,$rolePermissionData,$payment_gateway_data,$payment_gateway_config_data);

        $instancelist = new InstanceList;
        $instancelist->instance_name = $create_instance;
        $instancelist->base_url = 'https://'.$site_url.'/api/fetchdetail';
        $instancelist->publish = 1;
        $instancelist->created_date_time = date('Y-m-d H:i:s');
        $instancelist->updated_date_time = date('Y-m-d H:i:s');
        $instancelist->save();

        return response()->json(array('success' => true,'action'=>'added'),200);
    }


    public function createTableDynamically($new_connection,$admin_data_array,$acl_permission_data,$super_admin_data_array,$system_config_data,$user_permission_data,$site_data,$site_id,$super_admin_data,$site_permission_data,$site_url,$rolePermissionData,$payment_gateway_data,$payment_gateway_config_data){
        //users table

        
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2014_10_12_000000_create_users_table.php']);

        //password reset table
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2014_10_12_100000_create_password_resets_table.php']);

        //payu payment table
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2016_12_03_000000_create_payu_payments_table.php']);

        //acl permission table
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_09_25_101353_create_acl_permissions_table.php']);

        //role permission table
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_09_25_102322_create_role_permissions_table.php']);

        //roles table
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_09_25_103310_create_roles_table.php']);

        //user permission table
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_09_25_103912_create_user_permissions_table.php']);

        //admin_table table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_02_105404_create_admin_table.php']);

        //user_table table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_02_132122_create_user_table.php']);

        //template master table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_04_122546_create_template_master_table.php']);

        //font master table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_12_085201_create_font_masters_table.php']);

        //student table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_12_105350_create_student_tables_table.php']);

        //image delete history table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_13_131447_image_delete_history.php']);

        //institute table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_14_085615_institute_table.php']);

        //student history table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_14_091055_create_student_history_table.php']);

        //bg template master table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_14_110951_create_background_template_master_table.php']);

        //super admin table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_15_085352_super_admin.php']);

        //payment gateway table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_18_072847_create_payment_gateways_table.php']);

        //payment gateway config table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_18_084227_create_payment_gateway_configs_table.php']);

        //field master table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_19_110901_field_master.php']);

        //transactions table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_21_115922_create_transactions_table.php']);

        //session manager table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_22_070706_create_session_manager_table.php']);

        //student master table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_23_095428_create_student_master_table.php']);

        //student document table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_25_064822_create_student_documents_table.php']);

        //update transactions table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_25_104943_create_table_transaction.php']);

        //printing details table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_25_112321_create_table__printing_details.php']);

        

        //update printing details table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_11_29_093646_create_printing_details_table.php']);

        //scanned history table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_12_02_124352_create_scanned_history_table.php']);

        //system config table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_12_03_091132_create_system_configs_table.php']);

        //config table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_12_05_071805_create_config_table.php']);

        //excel upload history table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_12_05_124427_create_exceluploadhistory_table.php']);

        //api tracker table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_12_09_123958_api_tracker.php']);

        //jobs table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_12_10_164236_create_jobs_table.php']);

        //excel merge logs table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_12_16_151923_create_excel_merge_logs_table.php']);

        //id card status table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_12_20_180525_add_id_card_status_table.php']);

        //failed jobs table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_12_21_112038_create_failed_jobs_table.php']);

        //update id card status table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_12_23_150323_id_card_status.php']);

        //db details table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2019_12_30_134321_db_details.php']);

        //super admin login table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2020_03_12_135444_create_super_admin_login.php']);

        //site table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2020_03_12_135704_create_sites.php']);

        //site permission table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2020_03_12_135731_create_site_permissions.php']);

        //oauth access token table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2014_10_12_000000_create_oauth_access_tokens_table.php']);

        //oauth access codes table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2014_10_12_000000_create_oauth_auth_codes_table.php']);

        //oauth access clients table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2014_10_12_000000_create_oauth_clients_table.php']);

        //oauth personal access client table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2014_10_12_000000_create_oauth_personal_access_clients_table.php']);

        //oauth refresh token table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2014_10_12_000000_create_oauth_refresh_tokens_table.php']);

        //sb excel upload history table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2014_10_12_000000_create_sb_excelupload_history_table.php']);

        //sb printing details table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2014_10_12_000000_create_sb_printing_details_table.php']);

        //sb scanned history table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2014_10_12_000000_create_sb_scanned_history_table.php']);

        //sb student table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2014_10_12_000000_create_sb_student_table_table.php']);

        //sb transaction table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2014_10_12_000000_create_sb_transactions_table.php']);

        //user role permission table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2014_10_12_000000_create_user_role_permisssion_table.php']);

        //uploaded_pdfs table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2021_08_05_135704_create_uploaded_pdfs.php']);
        
        //individual_records table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2021_08_05_135704_create_individual_records.php']);
        
        //file_records table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2021_08_05_135704_create_file_records.php']);

        //duplicate_records table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2021_08_05_135704_create_duplicate_records.php']);

        //blockchain api tracker table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2023_01_13_135704_create_bc_api_tracker.php']);

        //blockchain mint data table 
        \Artisan::call('migrate', ['--database' => $new_connection,'--path'=>'database/migrations/2023_01_13_135704_create_bc_mint_data.php']);

        //make dmain dir
        $subdomain = explode('.', $site_url);  
        $admin_data_array[0]['fullname'] = 'SeQR '.$subdomain[0];
        $admin_data_array[0]['password'] = Hash::make('seqr@'.$subdomain[0]);
        $admin_data_array[0]['site_id'] = $site_id;
        $admin_data_array[0]['role_id'] = 1;
        
        DB::connection($new_connection)->table("admin_table")->insert($admin_data_array[0]);

        DB::connection($new_connection)->table("acl_permissions")->insert($acl_permission_data);

        DB::connection($new_connection)->table("super_admin_login")->insert($super_admin_data_array[0]);
        
        $system_config_data['site_id'] = $site_id;

        DB::connection($new_connection)->table("system_config")->insert($system_config_data);
        DB::connection($new_connection)->table("system_config")->where('id',1)->update(['varification_sandboxing'=>0,'sandboxing'=>0]);

        $role_data = ['name'=>'Admin','description'=>'admin','status'=>'1','created_by'=>1,'updated_by'=>1,'site_id'=>$site_id];
        DB::connection($new_connection)->table("roles")->insert($role_data);

        DB::connection($new_connection)->table("user_permissions")->insert($user_permission_data);
        DB::connection($new_connection)->table("user_permissions")->where('user_id',1)->update(['role_id'=>1]);

        DB::connection($new_connection)->table("role_permissions")->insert($rolePermissionData);

        unset($site_data["pdf_storage_path"]);
       // if(isset($site_data["bc_wallet_address"])){
        unset($site_data["bc_wallet_address"]);
        //}
        
        //if(isset($site_data["bc_private_key"])){
        unset($site_data["bc_private_key"]);
        //}
        DB::connection($new_connection)->table("sites")->insert($site_data);//when login check with site_url so insert current site

        DB::connection($new_connection)->table("super_admin_new")->insert($super_admin_data);

        //print_r($site_permission_data);
        DB::connection($new_connection)->table("site_permissions")->insert($site_permission_data);//when creating site permission added

        $payment_gateway_data['site_id'] = $site_id;
        DB::connection($new_connection)->table("payment_gateway")->insert($payment_gateway_data);

        DB::connection($new_connection)->table("payment_gateway_config")->insert($payment_gateway_config_data);
        
        $get_file_aws_local_flag = SystemConfig::select('file_aws_local')->where('site_id',$site_id)->first();

        
          if(!is_dir(public_path().'/'.$subdomain[0].'/')){
              //Directory does not exist, then create it.
              mkdir(public_path().'/'.$subdomain[0].'/', 0777);
          }

          $backend_directory = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.backend').'/';

          if(!is_dir($backend_directory)){
              //Directory does not exist, then create it.
              mkdir($backend_directory, 0777);
          }

          $canvas_directory = $backend_directory.'canvas/';

          if(!is_dir($canvas_directory)){
              //Directory does not exist, then create it.
              mkdir($canvas_directory, 0777);
          }

          $dir_check_canvas_image_exists=$backend_directory."/canvas/dummy_images";
          if(!is_dir($dir_check_canvas_image_exists)){
  
              mkdir($dir_check_canvas_image_exists, 0777);
          }
            $dir_check_bgimage_example_exists=$backend_directory."/canvas/bg_images";
            if(!is_dir($dir_check_bgimage_example_exists)){
  
              mkdir($dir_check_bgimage_example_exists, 0777);
          }
            $dir_check_ghost_image_exists=$backend_directory."/canvas/ghost_images";  
            if(!is_dir($dir_check_ghost_image_exists)){
  
              mkdir($dir_check_ghost_image_exists, 0777);
          }
            $dir_heck_custome_image=$backend_directory."/canvas/dummy_images/customImages"; 
            if(!is_dir($dir_heck_custome_image)){
  
              mkdir($dir_heck_custome_image, 0777);
          }
            
            $dir_check_canvas_temp=$backend_directory."/canvas/ghost_images/temp";
            if(!is_dir($dir_check_canvas_temp)){
  
              mkdir($dir_check_canvas_temp, 0777);
          }


          $template_directory = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/';
          //if directory not exist make directory
          if(!is_dir($template_directory)){
  
              mkdir($template_directory, 0777);
          }


        

          $customImages_directory = $template_directory.'customImages/';

          if(!is_dir($customImages_directory)){
              //Directory does not exist, then create it.
              mkdir($customImages_directory, 0777);
          }


          //pdf2pdf
          $pdf2pdf_dir = public_path().'/'.$subdomain[0].'/documents/';
          if(!is_dir($pdf2pdf_dir)){
              //Directory does not exist, then create it.
              mkdir($pdf2pdf_dir, 0777);
          }

          $pdf2pdf_dir = public_path().'/'.$subdomain[0].'/multi_pages/';
          if(!is_dir($pdf2pdf_dir)){
              //Directory does not exist, then create it.
              mkdir($pdf2pdf_dir, 0777);
          }

          $pdf2pdf_dir = public_path().'/'.$subdomain[0].'/processed_pdfs/';
          if(!is_dir($pdf2pdf_dir)){
              //Directory does not exist, then create it.
              mkdir($pdf2pdf_dir, 0777);
          }

          $pdf2pdf_dir = public_path().'/'.$subdomain[0].'/uploads/';
          if(!is_dir($pdf2pdf_dir)){
              //Directory does not exist, then create it.
              mkdir($pdf2pdf_dir, 0777);
          }



          $default_image = public_path().'/backend/templates/img/'.\Config::get('constant.default_image');

          \File::copy($default_image,$customImages_directory.'/'.\Config::get('constant.default_image'));        
        

          set_time_limit(240);
          $s3=Storage::disk('s3');
          $create_sub_dir=['canvas','templates'];
          $create_instance=$subdomain[0];
             
          if(!$s3->exists($create_instance))
          {  
            $create_backend="/backend";
            $create_backend=$create_instance.$create_backend;

            $s3->makeDirectory($create_instance, 0777);
            $s3->makeDirectory($create_backend, 0777);

            /*create all sub directory*/
            if($s3->exists($create_backend))
            { 
               foreach ($create_sub_dir as $key => $value) {
                    $s3->makeDirectory($create_backend.'/'.$value, 0777);  
               }
            }



            /*Create third directory new site*/
            $check_canvas_image_exists=$create_backend."/canvas/dummy_images";
            $check_bgimage_example_exists=$create_backend."/canvas/bg_images";
            $check_ghost_image_exists=$create_backend."/canvas/ghost_images";  
            $checke_custome_image=$create_backend."/canvas/dummy_images/customImages"; 
            $checke_template_custome_image=$create_backend."/templates/customImages"; 
            $checke_canvas_temp=$create_backend."/canvas/ghost_images/temp"; 

            if(!$s3->exists($check_canvas_image_exists))
            {
             $s3->makeDirectory($check_canvas_image_exists, 0777);  
            }
            if(!$s3->exists($check_bgimage_example_exists))
            {
             $s3->makeDirectory($check_bgimage_example_exists, 0777);  
            }
            if(!$s3->exists($check_ghost_image_exists))
            {
             $s3->makeDirectory($check_ghost_image_exists, 0777);  
            }
            if(!$s3->exists($checke_custome_image))
            {
             $s3->makeDirectory($checke_custome_image, 0777);  
            }
            if(!$s3->exists($checke_template_custome_image))
            {
             $s3->makeDirectory($checke_template_custome_image, 0777);  
            }
            if(!$s3->exists($checke_canvas_temp))
            {
             $s3->makeDirectory($checke_canvas_temp, 0777);  
            }

            $local_location_default_image=public_path().'/backend/canvas/dummy_images/';

            if($s3->exists($check_canvas_image_exists))
            {
                $default_image=['2dcode.png','barcode.png','copy.png','ghost.png','ID.png','QR.png'];
              
                foreach ($default_image as $key => $value) {
                     
                     $s3->put($check_canvas_image_exists.'/'.$value,file_get_contents($local_location_default_image.$value));
                 } 
            }

            if($s3->exists($checke_custome_image))
            {
               $cutome_image="images.png";  
               $s3->put($checke_custome_image.'/'.$cutome_image,file_get_contents($local_location_default_image.'img/'.$cutome_image));
            }

            if($s3->exists($checke_template_custome_image))
            {
               $cutome_image="images.png";  
               $s3->put($checke_template_custome_image.'/'.$cutome_image,file_get_contents($local_location_default_image.'img/'.$cutome_image));
            }

        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified Role.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        /*if($id == 201){
            $user_permission = AclPermission::getPermission();
        }else{
            $user_permission = AclPermission::getPermissions();
        }*/
        $user_permission = DB::table("acl_permissions_group")
                               ->select('main_route','sub_route','route_name','group_name','visibility_on','step_id','sub_step_id','main_menu_no')
                               ->where('visibility_on','1')->where('is_system','0')->where('is_super_admin','0')
                               ->orderBy('main_menu_no','asc')->orderBy('step_id','asc')->orderBy('sub_step_id','asc')
                               ->get()->toArray();
        //print_r($user_permission);

        $main_route_permission = array_column($user_permission, 'main_route');
        //print_r($main_route_permission);
        //exit;
        //echo json_encode($user_permission);
         /* $user_prems_temp = AclPermission::select('id','route_name','main_module','sub_module','module')->orderBy('main_module','asc')->get()->toArray();
        //  echo json_encode($user_prems_temp);
                      
                            $step_id=0;
                            $prevMainMod="";
                           foreach ($user_prems_temp as  $value) {
                              
                           $arrayInsert=array();

                           if($prevMainMod!=$value['main_module']){
                            $step_id=$step_id+1;
                           }
                           $arrayInsert['main_route']=$value['route_name'];
                           $arrayInsert['sub_route']=$value['route_name'];
                           $arrayInsert['route_name']=$value['module'];
                           $arrayInsert['group_name']=$value['main_module'];
                           $arrayInsert['step_id']=$step_id;
                           $arrayInsert['sub_step_id']=0;
                           $arrayInsert['visibility_on']=1;
                          // DB::table("acl_permissions_group")->insert($arrayInsert);
                           $prevMainMod=$value['main_module'];
                       }*/
                      
        
        $role_details = Site::findOrFail($id);

        $role_details->start_date=date('d-m-Y',strtotime($role_details->start_date));
        $role_details->end_date=date('d-m-Y',strtotime($role_details->end_date));
       // print_r($role_details->start_date);
        //exit;
        $role_current_permissions_all = SitePermission::where('site_id',$id)->pluck('route_name')->toArray();//'permission_id',

       
        $role_current_permissions = array_intersect($main_route_permission, $role_current_permissions_all);
     
         //print_r($role_current_permissions);
         //print_r($role_current_permissions_all);
        // exit; 
        $superAdmin = SuperAdmin::where(['property'=>'print_limit','site_id'=>$id])->first();


        $print_left = $superAdmin['value'] - $superAdmin['current_value'];

        
        $current_print = $superAdmin['current_value']; 
        if($current_print == '' || $current_print < 1){
          $current_print = 0;
        }
        $total_print = $superAdmin['value']; 
        /*
        $subdomain = explode('.', $role_details->site_url); 
       // print_r($subdomain);
        $fileDetailsArr=array();
        
        $directory_pdf_file = public_path().'/'.$subdomain[0].'/backend/pdf_file/';
        $file_count_pdf_file = 0;
        $files = glob($directory_pdf_file . "*.{pdf,PDF}",GLOB_BRACE);
        $file_size_pdf_file=0;
        if ($files){
         $file_count_pdf_file = count($files);
            foreach($files as $path){
                is_file($path) && $file_size_pdf_file += filesize($path);
                //is_dir($path)  && $size += get_dir_size($path);
            }
        }
        $file_size_pdf_file=CoreHelper::formatSizeUnits($file_size_pdf_file);
        $fileDetailsArr["file_count_pdf_file"]=$file_count_pdf_file;
        $fileDetailsArr["file_size_pdf_file"]=$file_size_pdf_file;

        $directory_pdf_file = public_path().'/'.$subdomain[0].'/backend/pdf_file/inactive_PDF/';
        $file_count_pdf_file_inactive = 0;
        $files = glob($directory_pdf_file . "*.{pdf,PDF}",GLOB_BRACE);
        $file_size_pdf_file_inactive=0;
        if ($files){
         $file_count_pdf_file_inactive = count($files);
            foreach($files as $path){
                is_file($path) && $file_size_pdf_file_inactive += filesize($path);
            }
        }
        $file_size_pdf_file_inactive=CoreHelper::formatSizeUnits($file_size_pdf_file_inactive);
        $fileDetailsArr["file_count_pdf_file_inactive"]=$file_count_pdf_file_inactive;
        $fileDetailsArr["file_size_pdf_file_inactive"]=$file_size_pdf_file_inactive;

        $directory_pdf_file = public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/';
        $file_count_pdf_file_live = 0;
        $files = glob($directory_pdf_file . "*.{pdf,PDF}",GLOB_BRACE);
        $file_size_pdf_file_live=0;
        if ($files){
         $file_count_pdf_file_live = count($files);
            foreach($files as $path){
                is_file($path) && $file_size_pdf_file_live += filesize($path);
            }
        }
        $file_size_pdf_file_live=CoreHelper::formatSizeUnits($file_size_pdf_file_live);
        $fileDetailsArr["file_count_pdf_file_live"]=$file_count_pdf_file_live;
        $fileDetailsArr["file_size_pdf_file_live"]=$file_size_pdf_file_live;

        $directory_pdf_file = public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/preview/';
        $file_count_pdf_file_preview= 0;
        $files = glob($directory_pdf_file . "*.{pdf,PDF}",GLOB_BRACE);
        $file_size_pdf_file_preview=0;
        if ($files){
         $file_count_pdf_file_preview = count($files);
            foreach($files as $path){
                is_file($path) && $file_size_pdf_file_preview += filesize($path);
            }
        }
        $file_size_pdf_file_preview=CoreHelper::formatSizeUnits($file_size_pdf_file_preview);
        $fileDetailsArr["file_count_pdf_file_preview"]=$file_count_pdf_file_preview;
        $fileDetailsArr["file_size_pdf_file_preview"]=$file_size_pdf_file_preview;

        $directory_pdf_file = public_path().'/'.$subdomain[0].'/backend/templates/';
        $templates=CoreHelper::getDirContents($directory_pdf_file);

        $file_size_pdf_file_templates=CoreHelper::formatSizeUnits($templates['file_size_pdf_file']);
        $fileDetailsArr["file_count_pdf_file_templates"]=$templates['file_count_pdf_file'];
        $fileDetailsArr["file_size_pdf_file_templates"]=$file_size_pdf_file_templates;*/


        return view('superadmin.sitePermission.edit',compact('user_permission','role_details','role_current_permissions','id','current_print','total_print','print_left'));
    }

    /**
     * Update the specified Role in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(SuperAdminRoleRequest $request)
    {
        $pdf_storage_path = $request->get('pdf_storage_path');
        $get_site_url = Site::select('site_url')->where('site_id',$request['id'])->first();
        $old_instance=explode('.',$get_site_url->site_url);
        $old_instance=$old_instance[0];

        $start_date = $request['start_date'];
        $end_date = $request['end_date'];
        $start_date_check = strtotime($start_date);
        $end_date_check = strtotime($end_date);
        if($start_date_check > $end_date_check){
          return response()->json(array('success' => false,'action'=>'added','message'=>'Start date must be smaller than end date'),200);
        }
        $site_url = $request->get('site_url');

        $new_instance=explode('.',$site_url);
        $new_instance=$new_instance[0];


        $role_permission = $request->get('permission');
        $role_permission_group = $request->get('permissions');

        $user_permission = DB::table("acl_permissions_group")
                               ->select('main_route','sub_route','route_name','group_name','visibility_on','step_id','sub_step_id','main_menu_no')
                               ->where('visibility_on','1')->where('is_system','0')->where('is_super_admin','0')
                               ->orderBy('main_menu_no','asc')->orderBy('step_id','asc')->orderBy('sub_step_id','asc')
                               ->get()->toArray();
        

       // print_r($role_permission_group);

        $main_route_permission = array_column($user_permission, 'main_route');
        //print_r($main_route_permission);

        
        $role_permission_group = array_values(array_intersect($role_permission_group, $main_route_permission));
       /* print_r($role_permissions_post);
        exit;*/
        $permissionIds=array();
        foreach ($role_permission_group as $readPermission) {

//echo $readPermission;
            $acl_permission = DB::table("acl_permissions")->select('id','route_name')->where('route_name',$readPermission)->get()->toArray();
             if(count($acl_permission)>0){
           // print_r($acl_permission);
                if(!in_array($acl_permission[0]->id, $permissionIds)){
                    array_push($permissionIds, $acl_permission[0]->id);
                }

                $acl_permission_rel = DB::table("acl_permissions_group")->select('sub_route')->where('main_route',$readPermission)->where('sub_route','!=',$readPermission)->where('is_super_admin','0')->where('is_system','0')->get()->toArray();

               if(count($acl_permission_rel)>0){
                    foreach ($acl_permission_rel as $readSubPermission) {
                       // print_r($readSubPermission);

                         $acl_permission = DB::table("acl_permissions")->select('id','route_name')->where('route_name',$readSubPermission->sub_route)->get()->toArray();
                            if(!in_array($acl_permission[0]->id, $permissionIds)){
                        array_push($permissionIds, $acl_permission[0]->id);
                    }
                    }
               }
            }
        }

        $acl_permission_sys = DB::table("acl_permissions_group")->select('sub_route')->where('is_super_admin',0)->where('is_system',1)->get()->toArray();
           if(count($acl_permission_sys)>0){
                foreach ($acl_permission_sys as $readSubPermission) {
                     $acl_permission = DB::table("acl_permissions")->select('id','route_name')->where('route_name',$readSubPermission->sub_route)->get()->toArray();
                       if(!in_array($acl_permission[0]->id, $permissionIds)){
                    array_push($permissionIds, $acl_permission[0]->id);
                    }
                }
           }
       /* print_r($permissionIds);
        exit;*/
        $role_permission =$permissionIds;
        $role_id = $request->id;
        
        $role_edit_save = Site::findOrFail($role_id);
        //print_r($role_edit_save);
        $role_edit_save->site_url = $site_url;

        $role_edit_save->start_date = date('Y-m-d',strtotime($request->get('start_date')));
        $role_edit_save->end_date = date('Y-m-d',strtotime($request->get('end_date')));
        $role_edit_save->license_key = $request->get('license_key');
        $role_edit_save->status = $request->get('status');
        $role_edit_save->apple_app_url = $request->get('apple_app_url');
        $role_edit_save->android_app_url = $request->get('android_app_url');
        $role_edit_save->pdf_storage_path = $request->get('pdf_storage_path');
        $role_edit_save->country = $request->get('country');
        if($request->get('country')=="India"){
            $role_edit_save->state = $request->get('state');    
        }else{
            $role_edit_save->state = "";
        }  
        $role_edit_save->organization_category = $request->get('organization_category');
        $role_edit_save->save();
       
        $site_id  = $role_edit_save->site_id;

    
        SuperAdmin::where('site_id',$site_id)->update(['value'=>$request['value']]);

 
        $role_permission_data = ['role_id'=>$role_id,'role_permission'=>$role_permission];
        

        
//print_r($role_permission_data);

        $this->dispatch(new SuperAdminRolePermissionJob($role_permission_data));

        //fetch admin tabnle data from main db table
        $admin_data = Admin::all();
        //password is in hidden so when converting to array password is not coming so make visible it
        $admin_data->makeVisible('password')->toArray();

        //insert data 
        $admin_data_array = $admin_data->toArray();
        //acl permission insert data
        $acl_permission_data = AclPermission::all()->toArray();
        //super admin insert data
        $super_admin_data = SuperAdminLogin::all();
        $super_admin_data->makeVisible('password')->toArray();
        $super_admin_data_array = $super_admin_data->toArray();

        //system config insert data
        $system_config_data = SystemConfig::get()->first()->toArray();
        

        //user permission data
        $user_permission_data = UserPermission::where('user_id',1)->get()->toArray();

        //role permission data
        $rolePermissionData = RolePermission::where('role_id',1)->get()->toArray();
        //site permission data
        $site_data = Site::where('site_url',$site_url)->first()->toArray();
        //super admin data
        $super_admin_data = SuperAdmin::where('site_id',$site_id)->first()->toArray();

        //echo $site_id;
        //site permission data
        $site_permission_data = SitePermission::where('site_id',$site_id)->get()->toArray();
        //print_r($site_permission_data);
        //payment gateway data
        $payment_gateway_data = PaymentGateway::first()->toArray();

        //payment gateway config data
        $payment_gateway_config_data = PaymentGatewayConfig::first()->toArray();

    
        if($old_instance != $new_instance){
            $dbName = 'seqr_d_'.$new_instance;
            //$dbName = 'seqr_demo';

            if (\DB::statement('create database ' . $dbName) == true) {
                $new_connection = 'new';
                $nc = \Illuminate\Support\Facades\Config::set('database.connections.' . $new_connection, [
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
            }

            
            //creating tables with structure and data
            $this->createTableDynamically($new_connection,$admin_data_array,$acl_permission_data,$super_admin_data_array,$system_config_data,$user_permission_data,$site_data,$site_id,$super_admin_data,$site_permission_data,$site_url,$rolePermissionData,$payment_gateway_data,$payment_gateway_config_data);

                

            $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME =  ?";
            $db = DB::select($query, ['seqr_d_'.$old_instance]);
            if (!empty($db)) {
                $s3=Storage::disk('s3');
                if($s3->exists('/'.$old_instance))
                {
                 $s3->deleteDirectory('/'.$old_instance);  
                }


                $domain_directory = public_path().'/'.$old_instance;

                if(is_dir($domain_directory)){
                  //Directory does not exist, then create it.
                    \File::deleteDirectory($domain_directory);
                }
                DB::statement('DROP DATABASE `seqr_d_'.$old_instance.'`');
            }
        }else{
                if($new_instance!='demo'){
                     $dbName = 'seqr_d_'.$new_instance;
                }else{
                    $dbName = 'seqr_demo';
                }

               
                //
                $new_connection = 'new';
                $nc = \Illuminate\Support\Facades\Config::set('database.connections.' . $new_connection, [
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
                $start_date = $request->get('start_date');
                $end_date = $request->get('end_date');
                $license_key = $request->get('license_key');
                $status = $request->get('status');
                
                    
                DB::connection($new_connection)->table("sites")->where('site_id',$site_id)->update(['start_date'=>date('Y-m-d',strtotime($start_date)),'end_date'=>date('Y-m-d',strtotime($end_date)),'license_key'=>$license_key,'status'=>$status]);//
                //exit;
                //Update by mandar
                 if(!empty($role_permission_data['role_id'])&&$dbName!="seqr_demo")
                {
                  DB::connection($new_connection)->table('acl_permissions')->truncate();
                  DB::connection($new_connection)->table('site_permissions')->truncate();
                  
                } 
                //print_r($role_permission);
                if(isset($role_permission)){
                    foreach ($role_permission as $key => $single_permission) {



                        $aclPermissionInsert=$route_name = AclPermission::where('id',$single_permission)->get()->toArray();
                        $route_name = head($route_name);
                        if($route_name){

                        $aclSiteDest=DB::connection($new_connection)->table("acl_permissions")->where('route_name',$route_name['route_name'])->first('id');
                           

                        if($aclSiteDest){
                         $aclSiteDest = (array)head($aclSiteDest);

                        }else{
                            DB::connection($new_connection)->table("acl_permissions")->insert($aclPermissionInsert[0]);
                           // AclPermission::insertGetId($AclPermissionInsert);
                            //print_r($aclPermissionInsert);
                            //exit;
                        
                        }
                         

                         $permissions=DB::connection($new_connection)->table("site_permissions")->where('route_name',$route_name['route_name'])->where('site_id',$site_id)->first();


                        if(!$permissions){
                           
                           $arrayInsert=array();
                           $arrayInsert['site_id']=$role_permission_data['role_id'];
                           $arrayInsert['permission_id']=$single_permission;
                           $arrayInsert['route_name']=$route_name['route_name'];
                           $arrayInsert['main_module']=$route_name['main_module'];
                           $arrayInsert['sub_module']=$route_name['sub_module'];
                           $arrayInsert['description']=$route_name['description'];
                           $arrayInsert['created_at']=date('Y-m-d');
                           DB::connection($new_connection)->table("site_permissions")->insert($arrayInsert);
                            /*echo "Not PERM";
                         print_r($aclSiteDest);*/
                        }


                        }/*else{
                         echo "PERM NOT FOUND IN ACL SOURCE";
                         exit;
                        
                        }*/
                    }
                }
        }

        
        $updateDetails = [
            'instance_name' => $new_instance,
            'base_url' => 'https://'.$new_instance.'/api/fetchdetail'
        ];

        InstanceList::where('instance_name',$old_instance)->update($updateDetails);

        return response()->json(array('success' => true,'action'=>'updated'),200);
    }

    /**
     * Remove the specified Role from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
         if(!empty($id))
        {  
             
           
 
              $get_site_url = Site::select('site_url')->where('site_id',$id)->first();
              if(!empty($get_site_url)){
                $delete_instance=$get_site_url->site_url;
                $delete_instance=explode('.',$delete_instance);
                $delete_instance=$delete_instance[0];
                
                $dbName = 'seqr_d_'.$delete_instance;

                $delete_site=Site::where('site_id',$id)->delete();

                $aws_domain_directory = $delete_instance;
                $s3=Storage::disk('s3');
                if($s3->exists('/'.$aws_domain_directory))
                {
                 $s3->deleteDirectory('/'.$aws_domain_directory);  
                }


                $domain_directory = public_path().'/'.$delete_instance;

                if(is_dir($domain_directory)){
                  //Directory does not exist, then create it.
                    \File::deleteDirectory($domain_directory);
                }

                InstanceList::where('instance_name',$delete_instance)->delete();

                $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME =  ?";
                $db = DB::select($query, ['seqr_d_'.$delete_instance]);
                if (!empty($db)) {
                    //delete db when deleting site
                    DB::statement('DROP DATABASE `'.$dbName.'`');
                }
                $delete_per=SitePermission::where('site_id',$id)->delete(); 
              }
                
            return response()->json(['success'=>true]);
        }
        
    }


    public function getFilesDetails(Request $request){

        $subdomain = explode('.', $request->get('site_url')); 
        // print_r($subdomain);
        $fileDetailsArr=array();

        switch ($request->get('type')) {
            case 'pdf_file':
                           $directory_pdf_file = public_path().'/'.$subdomain[0].'/backend/pdf_file/';
                            $file_count_pdf_file = 0;
                            $files = glob($directory_pdf_file . "*.{pdf,PDF}",GLOB_BRACE);
                            $file_size_pdf_file=0;
                            if ($files){
                             $file_count_pdf_file = count($files);
                                foreach($files as $path){
                                    is_file($path) && $file_size_pdf_file += filesize($path);
                                    //is_dir($path)  && $size += get_dir_size($path);
                                }
                            }
                            $file_size_pdf_file=CoreHelper::formatSizeUnits($file_size_pdf_file);
                            $fileDetailsArr["file_count_pdf_file"]=$file_count_pdf_file;
                            $fileDetailsArr["file_size_pdf_file"]=$file_size_pdf_file;
  
                            /*$file_count_pdf_file = 0;
                            $file_size_pdf_file = 0;
                            foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory_pdf_file)) as $file){
                                $file_size_pdf_file+=$file->getSize();
                                 $file_count_pdf_file =  $file_count_pdf_file +1;
                            }
*/

                           // echo $size;
                            // $file_size_pdf_file=CoreHelper::formatSizeUnits($file_size_pdf_file);
                            // $fileDetailsArr["file_count_pdf_file"]=$file_count_pdf_file;
                            // $fileDetailsArr["file_size_pdf_file"]=$file_size_pdf_file;


                            // AWS COUNT AND SIZE
                            $aws_directory_pdf_file = 'public/'.$subdomain[0].'/backend/pdf_file/';
                            $disk = \Storage::disk('s3');
                            $list = $disk->allFiles($aws_directory_pdf_file);
                            $size = 0;
                            foreach ($list as $file) {
                                $size+= $disk->size($file);
                            }
                            $fileDetailsArr["aws_file_count_pdf_file"]= count($list);
                            $fileDetailsArr["aws_file_size_pdf_file"]=$this->formatSize($size);

                            break;
            
           
            case 'pdf_inactive':
                                $directory_pdf_file = public_path().'/'.$subdomain[0].'/backend/pdf_file/inactive_PDF/';
                                $file_count_pdf_file_inactive = 0;
                                $files = glob($directory_pdf_file . "*.{pdf,PDF}",GLOB_BRACE);
                                $file_size_pdf_file_inactive=0;
                                if ($files){
                                 $file_count_pdf_file_inactive = count($files);
                                    foreach($files as $path){
                                        is_file($path) && $file_size_pdf_file_inactive += filesize($path);
                                    }
                                }
                                $file_size_pdf_file_inactive=CoreHelper::formatSizeUnits($file_size_pdf_file_inactive);
                                $fileDetailsArr["file_count_pdf_file_inactive"]=$file_count_pdf_file_inactive;
                                $fileDetailsArr["file_size_pdf_file_inactive"]=$file_size_pdf_file_inactive;


                                // //AWS COUNT AND SIZE
                                $aws_directory_pdf_file = 'public/'.$subdomain[0].'/backend/pdf_file/Inactive_PDF';
                                $disk = \Storage::disk('s3');
                                $list = $disk->allFiles($aws_directory_pdf_file);
                                $size = 0;
                                foreach ($list as $file) {
                                    $size+= $disk->size($file);
                                }
                                $fileDetailsArr["aws_file_count_pdf_file_inactive"]= count($list);
                                $fileDetailsArr["aws_file_size_pdf_file_inactive"]=$this->formatSize($size);



                                break;

            case 'pdf_live':
                            $directory_pdf_file = public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/';
                            $file_count_pdf_file_live = 0;
                            $files = glob($directory_pdf_file . "*.{pdf,PDF}",GLOB_BRACE);
                            $file_size_pdf_file_live=0;
                            if ($files){
                             $file_count_pdf_file_live = count($files);
                                foreach($files as $path){
                                    is_file($path) && $file_size_pdf_file_live += filesize($path);
                                }
                            }
                            $file_size_pdf_file_live=CoreHelper::formatSizeUnits($file_size_pdf_file_live);
                            $fileDetailsArr["file_count_pdf_file_live"]=$file_count_pdf_file_live;
                            $fileDetailsArr["file_size_pdf_file_live"]=$file_size_pdf_file_live;

                            // AWS COUNT AND SIZE
                            $aws_directory_pdf_file = 'public/'.$subdomain[0].'/backend/tcpdf/examples';
                            $disk = \Storage::disk('s3');
                            $list = $disk->allFiles($aws_directory_pdf_file);
                            $size = 0;
                            foreach ($list as $file) {
                                $size+= $disk->size($file);
                            }
                            $fileDetailsArr["aws_file_count_pdf_file_live"]= count($list);
                            $fileDetailsArr["aws_file_size_pdf_file_live"]=$this->formatSize($size);

                            break;

            case 'pdf_preview':
                                $directory_pdf_file = public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/preview/';
                                $file_count_pdf_file_preview= 0;
                                $files = glob($directory_pdf_file . "*.{pdf,PDF}",GLOB_BRACE);
                                $file_size_pdf_file_preview=0;
                                if ($files){
                                 $file_count_pdf_file_preview = count($files);
                                    foreach($files as $path){
                                        is_file($path) && $file_size_pdf_file_preview += filesize($path);
                                    }
                                }
                                $file_size_pdf_file_preview=CoreHelper::formatSizeUnits($file_size_pdf_file_preview);
                                $fileDetailsArr["file_count_pdf_file_preview"]=$file_count_pdf_file_preview;
                                $fileDetailsArr["file_size_pdf_file_preview"]=$file_size_pdf_file_preview;


                                // AWS COUNT AND SIZE
                                $aws_directory_pdf_file = 'public/'.$subdomain[0].'/backend/tcpdf/examples/preview';
                                $disk = \Storage::disk('s3');
                                $list = $disk->allFiles($aws_directory_pdf_file);
                                $size = 0;
                                foreach ($list as $file) {
                                    $size+= $disk->size($file);
                                }
                                $fileDetailsArr["aws_file_count_pdf_file_preview"]= count($list);
                                $fileDetailsArr["aws_file_size_pdf_file_preview"]=$this->formatSize($size);


                                break;

            case 'templates':
                            $directory_pdf_file = public_path().'/'.$subdomain[0].'/backend/templates/';
                            $templates=CoreHelper::getDirContents($directory_pdf_file);
                           /* $file_count_pdf_file = 0;
                            $file_size_pdf_file = 0;
                            foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory_pdf_file)) as $file){
                                $file_size_pdf_file+=$file->getSize();
                                 $file_count_pdf_file =  $file_count_pdf_file +1;
                            }*/
                            $file_size_pdf_file_templates=CoreHelper::formatSizeUnits($templates['file_size_pdf_file']);
                            $fileDetailsArr["file_count_pdf_file_templates"]=$templates['file_count_pdf_file'];
                            //$file_size_pdf_file_templates=CoreHelper::formatSizeUnits($file_size_pdf_file);
                            //$fileDetailsArr["file_count_pdf_file_templates"]=$file_count_pdf_file;
                            $fileDetailsArr["file_size_pdf_file_templates"]=$file_size_pdf_file_templates;

                            //AWS COUNT AND SIZE
                            $aws_directory_pdf_file = 'public/'.$subdomain[0].'/backend/templates';
                            $disk = \Storage::disk('s3');
                            $list = $disk->allFiles($aws_directory_pdf_file);
                            $size = 0;
                            foreach ($list as $file) {
                                $size+= $disk->size($file);
                            }
                            $fileDetailsArr["aws_file_count_pdf_file_templates"]= count($list);
                            $fileDetailsArr["aws_file_size_pdf_file_templates"]=$this->formatSize($size);

                            break;
            case 'storage_log':
                    $logFilePath = storage_path().'\logs';
                    //$fileSize = fileSize($logFilePath);
                    $file_size = 0;
                    foreach( \File::allFiles($logFilePath) as $file)
                    {
                        $file_size += $file->getSize();
                    }
                    
                    $sizeForLog = $this->formatSize($file_size);
                    $fileDetailsArr['storage_log_size'] = $sizeForLog;

                    // Latest File Name
                    $logFolder = $logFilePath;
                    $filesInFolder = \File::files($logFolder);     
                    foreach($filesInFolder as $path) { 
                        $file = pathinfo($path);
                        if (is_file($file['dirname'].'\\'.$file['basename']) && filemtime($file['dirname'].'\\'.$file['basename']) > $latest_ctime)
                        {
                                $latest_ctime = filemtime($file['dirname'].'\\'.$file['basename']);
                                $latest_filename = $file['basename'];
                                // $latest_filename = $file['dirname'].'\\'.$file['basename'];
                        }

                    } 
                    $fileDetailsArr['storage_log_latest_file'] = $latest_filename;
                    break;
            case 'database_log':
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
                        $result = DB::select(DB::raw('SELECT table_name AS "Table",
                            (data_length + index_length) AS "Size"
                            FROM information_schema.TABLES
                            WHERE table_schema ="'.$dbName. '"'));
                        $array = json_decode(json_encode($result), true);
                        $db_size = 0;
                        $tableCount = 0;
                        foreach($array AS $res) {
                            $tableCount++;
                            $db_size += $res['Size']; 
                        }
                        $databaseSize = $this->formatSize($db_size);
                        
                        $fileDetailsArr['database_size'] = $databaseSize;
                        $fileDetailsArr['database_table_count'] = $tableCount;
                        break;
            default:
                $notfound=true;
                break;
        }
        return response()->json(['success'=>true,'msg'=>'success',"data"=>$fileDetailsArr]);


    }    

    function formatSize($bytes){ 
        $kb = 1024;
        $mb = $kb * 1024;
        $gb = $mb * 1024;
        $tb = $gb * 1024;
        if (($bytes >= 0) && ($bytes < $kb)) {
            // return $bytes . ' B';
            return number_format($bytes,2) . ' B';
        } elseif (($bytes >= $kb) && ($bytes < $mb)) {
            return number_format($bytes / $kb,2) . ' KB';
            // return $bytes / $kb . ' KB';
        } elseif (($bytes >= $mb) && ($bytes < $gb)) {
            // return $bytes / $mb . ' MB';
            return number_format($bytes / $mb,2) . ' MB';
        } elseif (($bytes >= $gb) && ($bytes < $tb)) {
            // return $bytes / $gb . ' GB';
            return number_format($bytes / $gb,2) . ' GB';
        } elseif ($bytes >= $tb) {
            return number_format($bytes / $tb,2) . ' TB';
            // return $bytes / $tb . ' TB';
        } else {
            return number_format($bytes,2) . ' B';
            // return $bytes . ' B';
        }
    }

}

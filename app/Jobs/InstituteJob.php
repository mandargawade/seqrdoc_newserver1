<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;
use App\models\InstituteMaster;


class InstituteJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $institute_data;
    public function __construct($institute_data)
    {
        $this->institute_data = $institute_data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Request $request)
    {
        //get data from listener
        $institute_data = $this->institute_data;
        $id = $request->id;

        $institute_username = [];
        if(isset($institute_data['id'])){
            $id = $institute_data['id'];
        }


        //check id is exist in db or not if exist then update else create
        $save_institute_data = InstituteMaster::firstorNew(['id'=>$id]);
        //fill data in variable
        $save_institute_data->fill($institute_data);

      
        //save with data
        if(isset($institute_data['id'])){            
            if ($institute_data['password']==''){
              echo  $save_institute_data->password = $institute_data['passwordedit'];
            }else{
                $save_institute_data->password = \Hash::make($institute_data['password']);
            }

            
        }else{
            //encrypt password during edit
            $save_institute_data->password = \Hash::make($institute_data['password']);
            

        }
         
      
       // print_r($save_institute_data);
        //get login user id
        $admin_id = \Auth::guard('admin')->user()->toArray();
        $site_id = \Auth::guard('admin')->user()->site_id;
        $save_institute_data->created_by = $admin_id['id'];
        $save_institute_data->updated_by = $admin_id['id'];
        $save_institute_data->site_id =$site_id;
        $save_institute_data->publish = 1;
        $save_institute_data->save();


        if(isset($institute_data['id'])){
            $message = Array('type'=>'success','message'=>'User Updated sucessfully');
            echo json_encode($message);
        }
        else{
            $message = Array('type'=>'success','message'=>'Username added sucessfully');
            echo json_encode($message);
        }
        exit();
    }
}

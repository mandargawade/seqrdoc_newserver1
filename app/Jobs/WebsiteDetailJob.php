<?php

namespace App\Jobs;

use App\models\WebsiteDetail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WebsiteDetailJob  
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($websiteData)
    {
        $this->websiteData=$websiteData;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $websiteData=$this->websiteData;
        $id=null;
        if (!empty($websiteData['user_id'])) {
            
            $id=$websiteData['user_id'];
        }

        $web_obj=WebsiteDetail::firstOrNew(['id'=>$id]);
        $web_obj->fill($websiteData);
        $web_obj->save();
    }
}

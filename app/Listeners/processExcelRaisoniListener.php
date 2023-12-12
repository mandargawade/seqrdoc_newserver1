<?php

namespace App\Listeners;

use App\Events\processExcelRaisoniEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\processExcelJobRaisoni;
use Illuminate\Foundation\Bus\DispatchesJobs;
class processExcelRaisoniListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    use DispatchesJobs;
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  processExcelEvent  $event
     * @return void
     */
    public function handle(processExcelRaisoniEvent $event)
    {
        $merge_excel_data = $event->merge_excel_data;
        
        //send data to job
        $merge_excel = $this->dispatch(new processExcelJobRaisoni($merge_excel_data));
        //return the response to controller coming from job
        return $merge_excel;
    }
}

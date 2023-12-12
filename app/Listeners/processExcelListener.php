<?php

namespace App\Listeners;

use App\Events\processExcelEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\processExcelJob;
use Illuminate\Foundation\Bus\DispatchesJobs;
class processExcelListener
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
    public function handle(processExcelEvent $event)
    {
        $merge_excel_data = $event->merge_excel_data;
        
        //send data to job
        $merge_excel = $this->dispatch(new processExcelJob($merge_excel_data));
        //return the response to controller coming from job
        return $merge_excel;
    }
}

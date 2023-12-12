<?php

namespace App\Listeners;

use App\Events\InstituteEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Jobs\InstituteJob;

class InstituteListener
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
     * @param  InstituteEvent  $event
     * @return void
     */
    public function handle(InstituteEvent $event)
    {
        //get data from event
        $institute_data = $event->institute_data;

        //send data to job
        $ins_data = $this->dispatch(new InstituteJob($institute_data));
    }
}

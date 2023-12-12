<?php

namespace App\Listeners;

use App\Events\DynamicImageEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\DynamicImageJob;
use Illuminate\Foundation\Bus\DispatchesJobs;

class DynamicImageListener
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
     * @param  DynamicImageEvent  $event
     * @return void
     */
    public function handle(DynamicImageEvent $event)
    {
        //data come from event 
        $dynamic_image_data = $event->dynamic_image_data;

        //send data to job
        $dynamic_image = $this->dispatch(new DynamicImageJob($dynamic_image_data));
        //return the response to controller coming from job
        return $dynamic_image;
    }
}

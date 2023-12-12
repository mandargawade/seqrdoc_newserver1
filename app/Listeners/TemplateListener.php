<?php

namespace App\Listeners;

use App\Events\TemplateEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Jobs\TemplateJob;

class TemplateListener
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
     * @param  TemplateEvent  $event
     * @return void
     */
    public function handle(TemplateEvent $event)
    {
        //data come from event 
        $get_template_data = $event->get_template_data;

        //send data to job
        $template_data = $this->dispatch(new TemplateJob($get_template_data));
        //return the response to controller coming from job
        return $template_data;
    }
}

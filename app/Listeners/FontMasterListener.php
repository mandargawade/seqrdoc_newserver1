<?php

namespace App\Listeners;

use App\Events\FontMasterEvent;
use App\Jobs\FontMasterJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class FontMasterListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  FontMasterEvent  $event
     * @return void
     */
    public function handle(FontMasterEvent $event)
    {
        $fontMaster_data=$event->fontMaster_data;
        $fontMaster_data= Dispatch(new FontMasterJob($fontMaster_data));
    }
}

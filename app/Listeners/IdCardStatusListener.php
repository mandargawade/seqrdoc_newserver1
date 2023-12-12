<?php

namespace App\Listeners;

use App\Events\IdCardStatusEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class IdCardStatusListener
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
     * @param  IdCardStatusEvent  $event
     * @return void
     */
    public function handle(IdCardStatusEvent $event)
    {
        //
    }
}

<?php

namespace App\Listeners;

use App\Events\PaymentGatewayEvent;
use App\Jobs\PaymentGatewayJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class PaymentGatewayListener
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
     * @param  PaymentGatewayEvent  $event
     * @return void
     */
    public function handle(PaymentGatewayEvent $event)
    {
        $pg_data=$event->pg_data;
        $pg_data=Dispatch(new PaymentGatewayJob($pg_data));
    }
}

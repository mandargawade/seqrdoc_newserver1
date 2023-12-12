<?php

namespace App\Listeners;

use App\Events\TransactionsEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\TransactionsJob;
use Illuminate\Foundation\Bus\DispatchesJobs;


class TransactionsListener
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
     * @param  TransactionsEvent  $event
     * @return void
     */
    public function handle(TransactionsEvent $event)
    {
        $payment_params=$event->payment_params;
        $payment_params_data= $this->dispatch(new TransactionsJob($payment_params));
        return $payment_params_data;
    }
}

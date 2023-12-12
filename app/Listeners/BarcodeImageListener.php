<?php

namespace App\Listeners;

use App\Events\BarcodeImageEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Jobs\BarcodeImageJob;

class BarcodeImageListener
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
     * @param  BarcodeImageEvent  $event
     * @return void
     */
    public function handle(BarcodeImageEvent $event)
    {
        $getBarcodeImageData = $event->getBarcodeImageData;

        $barcode_image = $this->dispatch(new BarcodeImageJob($getBarcodeImageData));
        return $barcode_image;
    }
}

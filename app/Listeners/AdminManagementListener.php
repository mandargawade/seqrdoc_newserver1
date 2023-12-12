<?php

namespace App\Listeners;

use App\Events\AdminManagementEvent;
use App\Jobs\AdminManagementJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AdminManagementListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        
    }

    /**
     * Handle the event.
     *
     * @param  AdminManagementEvent  $event
     * @return void
     */
    public function handle(AdminManagementEvent $event)
    {
        $admin_data=$event->admin_data;
        $admin_data=dispatch(new AdminManagementJob($admin_data));
    }
}

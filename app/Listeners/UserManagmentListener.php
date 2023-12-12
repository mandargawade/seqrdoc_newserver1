<?php

namespace App\Listeners;

use App\Events\UserManagmentEvent;
use App\Jobs\UserManagmentJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UserManagmentListener
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
     * @param  UserManagmentEvent  $event
     * @return void
     */
    public function handle(UserManagmentEvent $event)
    {
        $user_data=$event->user_data;

        $user_data=Dispatch(new UserManagmentJob($user_data));
    }
}

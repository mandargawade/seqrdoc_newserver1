<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        'App\Events\DynamicImageEvent' => [
            'App\Listeners\DynamicImageListener',
        ],
        'App\Events\InstituteEvent' => [
            'App\Listeners\InstituteListener',
        ],
        'App\Events\BarcodeImageEvent' => [
            'App\Listeners\BarcodeImageListener',
        ],
         'App\Events\FontMasterEvent' => [
            'App\Listeners\FontMasterListener',
        ],
        'App\Events\PaymentGatewayEvent' => [
            'App\Listeners\PaymentGatewayListener',
        ],
        'App\Events\TemplateEvent' => [
            'App\Listeners\TemplateListener',
        ],
        'App\Events\UserManagmentEvent' => [
            'App\Listeners\UserManagmentListener',
        ],
        'App\Events\AdminManagementEvent' => [
            'App\Listeners\AdminManagementListener',
        ],
        'App\Events\TransactionsEvent' => [
            'App\Listeners\TransactionsListener',
        ],
        'App\Events\processExcelEvent' => [
            'App\Listeners\processExcelListener',
        ],
        'App\Events\processExcelRaisoniEvent' => [
            'App\Listeners\processExcelRaisoniListener',
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}

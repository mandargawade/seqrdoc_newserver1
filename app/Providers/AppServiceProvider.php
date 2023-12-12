<?php

namespace App\Providers;
// phpinfo();exit();
use App\models\PaymentGateway;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\PassportServiceProvider;
use Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
    }
}

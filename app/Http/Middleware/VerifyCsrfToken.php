<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        
        '/webapp/payment-gateway/paytm/response',
        'verify/payment/paytm/response-success',
        'verify/payment/paytm/response-success-qr',
        '/verify/payment/paytm/response-success-mobile',
        '/verify/payment/paytm/response-success-mobile-qr',
        '/verify/payu-money-payment-failure',
        '/verify/payu-money-payment-success',
		'/api/AddInfo'
    ];
}

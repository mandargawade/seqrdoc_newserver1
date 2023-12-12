<?php

namespace App\Http\Middleware;

use Closure;
use Auth;
use  Illuminate\Http\Request;

class RedirectIfSuperAdminAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        // dd($subdomain);
        if($subdomain[0] != 'master')
        {
           return redirect()->route('admin.dashboard'); 
        }

        if(Auth::guard('superadmin')->check())
        {
           return redirect()->route('superadmin.dashboard');
        }
        //exit;
        return $next($request);
    }
}

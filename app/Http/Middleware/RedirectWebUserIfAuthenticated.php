<?php

namespace App\Http\Middleware;
use Closure;
use Auth;
class RedirectWebUserIfAuthenticated
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
        if(Auth::guard('webuser')->check())
        {
            
          return redirect('webapp/dashboard');
        }
     return $next($request);
    }
}

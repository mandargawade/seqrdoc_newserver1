<?php

namespace App\Http\Middleware;

use Closure;
use Auth;
use  Illuminate\Http\Request;

class RedirectSuperAdminNotAuthenticate
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
        if(!Auth::guard('superadmin')->check())
        {
            return redirect()->route('superadmin.index');
        }

         $headers = [
            'Pragma'            => 'no-cache',
            'Cache-Control'     => 'no-cache,nocache,no-store, max-age=0, must-revalidate',
            'Expires'           => 'Sun, 02 Jan 1990 00:00:00 GMT'
            
        ];

        if ($request->isMethod('OPTIONS')) {
            return response()->json('{"method":"OPTIONS"}', 200, $headers);
        }
 
        $response = $next($request);
        foreach($headers as $key => $value) {
            $response->headers->set($key, $value);
        }
 
        return $response;
        
        /*$response =$next($request);
         


         return $response->header('Cache-Control','nocache, no-store, max-age=0, must-revalidate')
            ->header('Pragma','no-cache')
            ->header('Expires','Sun, 02 Jan 1990 00:00:00 GMT');*/
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use App\models\SessionManager;

class APIToken
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
        if($request->header('accesstoken')){

            $accesstoken = $request->header('accesstoken');
            $session_manager = SessionManager::where('session_id',$accesstoken)->value('is_logged');
            if($session_manager == 1){

                return $next($request);
            }else{
                return response()->json([
                    'status'=>403,'message' => 'Your account has been deactivated or password has been changed.',
                ]);        
            }

        }
        return response()->json([
            'message' => 'Not a valid API request.',
        ]);
    }

}

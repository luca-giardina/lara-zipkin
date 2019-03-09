<?php
namespace Giardina\LaraZipkin\Middleware;

use Closure;
use Illuminate\Http\Request;

class LaraZipkinTerminateMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request)
    {
        if( env('ZIPKIN_TRACING_ENABLED', false) )
            app('ZipkinClient')->flush();
    }
}

<?php
namespace Giardina\LaraZipkin\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use Zipkin\Propagation\Map;
use Zipkin\Propagation\DefaultSamplingFlags;
use Zipkin\Timestamp;
use Zipkin\Kind;
use Zipkin\Tags;

class LaraZipkinLaravelMiddleware
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
        if( !env('ZIPKIN_TRACING_ENABLED', false) )
            return $next($request);

        $headers = null;
        if( $request->header('X-B3-TraceId') ) {
            $headers = [
                'TraceId' => $request->header('X-B3-TraceId'),
                'SpanId' => $request->header('X-B3-SpanId'),
                'ParentId' => $request->header('X-B3-ParentId')
            ];
        }
        /* Creates the span for the request and Injects the context into the wire */
        app('ZipkinClient')->getNextSpan(
            Route::currentRouteName() ?? Route::current()->uri() ?? $request->getRequestUri(),
            Kind\SERVER,
            $headers
        );

        app('ZipkinClient')->tagBy('x.forwarded.for', $request->ip());
        app('ZipkinClient')->tagBy(Tags\HTTP_ROUTE, $request->fullUrl());
        app('ZipkinClient')->tagBy(Tags\HTTP_METHOD, $request->method());
        app('ZipkinClient')->tagBy('http.request', json_encode($request->all()));

        $response = $next($request);

        $response->header('X-B3-TraceId', (string) app('ZipkinClient')->getTraceId());
        $response->header('X-B3-ParentId', $request->header('X-B3-ParentId') ?? (string) app('ZipkinClient')->getTraceSpanId());
        $response->header('X-B3-SpanId', (string) app('ZipkinClient')->getTraceSpanId());
        $response->header('X-B3-Sampled', app('ZipkinClient')->isSampled());

        app('ZipkinClient')->tagBy(Tags\HTTP_STATUS_CODE, $response->status());

        app('ZipkinClient')->finishAllSpan();

        return $response;
    }
}
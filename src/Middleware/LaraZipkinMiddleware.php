<?php
namespace Giardina\LaraZipkin\Middleware;

use Closure;
use Illuminate\Http\Request;

use Zipkin\Propagation\Map;
use Zipkin\Propagation\DefaultSamplingFlags;
use Zipkin\Timestamp;
use Zipkin\Kind;

class LaraZipkinMiddleware
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
        $ZipkinClient = app('ZipkinClient');

        $name = $request->getMethod() . " " . $request->getRequestUri();

        /* Creates the span for the request and Injects the context into the wire */

        $ZipkinClient->getNextSpan(
            $name,
            Kind\SERVER,
            $request->headers->all()
        );

        $ZipkinClient->tagBy('x.forwarded.for', $request->ip());
        $ZipkinClient->track('LaraZipkinMiddleware', 'handle');

        $response = $next($request);

        $response->header('X-B3-TraceId', (string) $ZipkinClient->getTraceId());
        $response->header('X-B3-ParentId', (string) $ZipkinClient->getTraceSpanId());
        $response->header('X-B3-Sampled', $ZipkinClient->isSampled());

        return $response;
    }

    public function terminate(Request $request)
    {
        $ZipkinClient = app('ZipkinClient');

        /* Sends the trace to zipkin once the response is served */

        $ZipkinClient->finishTrack('LaraZipkinMiddleware', 'handle');
        $ZipkinClient->finishAllSpan();

        $ZipkinClient->flush();
    }
}

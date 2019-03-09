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
        if( !env('ZIPKIN_TRACKING_ENABLED', false) )
            return $next($request);

        // creating main span using current route name if set or getCurrentRoutePath if match or getPathInfo as last choice
        $uri = $request->route()[1]["as"] ?? $this->getCurrentRoutePath($request) ?? $request->getPathInfo();

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
            $uri ?? env('ZIPKIN_ENDPOINT_NAME', 'MainSpan'),
            Kind\SERVER,
            $headers
        );

        app('ZipkinClient')->tagBy('x.forwarded.for', $request->ip());
        app('ZipkinClient')->tagBy(Tags\HTTP_ROUTE, $request->fullUrl());
        app('ZipkinClient')->tagBy(Tags\HTTP_METHOD, $request->method());
        app('ZipkinClient')->tagBy('http.request', json_encode($request->all()));

        $response = $next($request);

        $response->header('X-B3-TraceId', (string) app('ZipkinClient')->getTraceId());
        $response->header('X-B3-ParentId', (string) app('ZipkinClient')->getTraceSpanId());
        $response->header('X-B3-SpanId', (string) app('ZipkinClient')->getTraceSpanId());
        $response->header('X-B3-Sampled', app('ZipkinClient')->isSampled());


        app('ZipkinClient')->finishAllSpan();

        return $response;
    }

    private function getCurrentRoutePath($request)
    {
        $verbs = 'GET|POST|PUT|DELETE|PATCH';
        $routeToRegex = function ($string) use ($verbs) {
            $string = preg_replace("/^({$verbs})/", '', $string);
            $string = preg_replace('/\{\w+\}/', '\w+', $string);
            $string = preg_replace('/\{(\w+):(.+?)\}/', '\2', $string);
            return '#^'.$string.'$#';
        };
        $routeToMethod = function ($string) use ($verbs) {
            return preg_replace("/^({$verbs}).+$/", '\1', $string);
        };
        $routes = [];
        foreach (app('router')->getRoutes() as $routeName => $route) {
            $regex = $routeToRegex($routeName);
            $method = $routeToMethod($routeName);
            $routes[$regex] = compact('route', 'method');
        }
        uksort($routes, function ($a, $b) {
            return strlen($b) - strlen($a);
        });
        $method = $request->getMethod();
        $path = rtrim($request->getPathInfo(), '/');

        $foundRoute = null;
        foreach ($routes as $regex => $details) {
            if (true == preg_match($regex, $path) && $method == $details['method']) {
                $foundRoute = $details['route'];
                break;
            }
        }

        return $foundRoute["uri"];
    }
}

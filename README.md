# Lara-Zipkin
Wrapper for Laravel and Lumen using php-zipking


## Getting Started

In .env you should set

```
ZIPKIN_TRACING_ENABLED=true|false
ZIPKIN_ENDPOINT_NAME=Your_Project_Name
ZIPKIN_SERVER_ADDR=Zipkin_Endpoint_Hostname
ZIPKIN_REMOTE_PORT=Zipkin_Endpoint_Port
ZIPKIN_API_PATH=/api/v2/spans
ZIPKIN_REMOTE_HTTPS=true|false (is the endpoint url https?)
```

**Lumen Setup**

edit app.php

```
$app->register(\Giardina\LaraZipkin\LaraZipkinServiceProvider::class);
```

if you want to use the middleware to auto track requests add to $app->middleware([]);

```
$app->middleware([
    \Giardina\LaraZipkin\Middleware\LaraZipkinTerminateMiddleware::class
]);

$app->routeMiddleware([
    'tracing' => \Giardina\LaraZipkin\Middleware\LaraZipkinLumenMiddleware::class,
]);

```
LaraZipkinTerminateMiddleware send spans to Zipkin after the response is sent

LaraZipkinLumenMiddleware track the requests trying to create low cardinality span (ex ``/route/{param}/and/{id}``) 

I suggest you to give names to the routes so you will never have a problem!

**Laravel Setup**

edit app.php

```
providers -> Giardina\LaraZipkin\LaraZipkinServiceProvider::class
```

if you want to use the middleware to auto track requests add in Kernel.php in protected $middleware = [];

```
protected $middleware = [
    ..
    ..
    \Giardina\LaraZipkin\Middleware\LaraZipkinTerminateMiddleware::class
];
protected $routeMiddleware = [
    ..
    ..
    'tracing' => \Giardina\LaraZipkin\Middleware\LaraZipkinMiddleware::class
]
```





N.B. if you are going to use the LaraZipkinMiddleware to create the main span and want to track the other middlewares using the LaraZipkinClient object I suggest you to add the middleware that creates the main span into the protected $middlewarePriority array: https://laravel.com/docs/5.7/middleware#sorting-middleware


## Using the ZipkinClient object

The LaraZipkinServiceProvider creates into the app container an object:
```
app('ZipkinClient')
```

### Methods

#### getNextSpan

This method allows you to create a main span. By default the Middleware creates a span named with the route name or with the route pattern (ex. /your/route/66) or if there is no route name nor route pattern it will use the request url.

Use this method only if you want to create a new main span and change the context or if you are not going to use the Middleware.
```
public function getNextSpan( $name = 'no-name', $kind = 'SERVER', $headers = null ) : Span
```

#### track

This method will create a child span into the main span named ``$spanName`` of the kind ``$kind`` if ``$spanName`` doesn't exists already. 
If ``$method`` is set it adds an annotation into the childspan: "$method . '_starts'"
```
app('ZipkinClient')->track($spanName, $method, $kind = 'PRODUCER');
```

#### finishTrack

This method will finish the span named $spanName.
If ``$method`` is set it adds an annotation into the childspan: ``$method . '_starts'``
```
app('ZipkinClient')->finishTrack($spanName, $method = null);
```

#### trackCall

This method will create a child span into the span named ``$spanName`` of the kind ``$kind``.
The name for the new child span will be ``$callName`` and it will add a note ``$callName . '_starts'`` to the child span
If `$method` is set it adds an annotation into the childspan: "$method . '_starts'"
```
app('ZipkinClient')->trackCall($spanName, $callName = null, $kind = 'CONSUMER');
```

#### trackEndCall

This method will finish the span named ``$callName`` into ``$spanName``.
```
app('ZipkinClient')->trackEndCall($spanName, $callName);
```

#### tagBy

This method allows to add a tag to the main span in order to search by tag
```
public function tagBy($name, $value)
```


# N.B.
It's possible to use the core object itself as detailed at https://github.com/openzipkin/zipkin-php using the method 
```public function getTracer() : Tracer```


The Middleware add Tags in the main span for:

```
app('ZipkinClient')->tagBy('x.forwarded.for', $request->ip());
app('ZipkinClient')->tagBy(Tags\HTTP_ROUTE, $request->fullUrl());
app('ZipkinClient')->tagBy(Tags\HTTP_METHOD, $request->method());
app('ZipkinClient')->tagBy('http.request', json_encode($request->all()));
```


## Examples

# Tracking another Middleware
```php
/**
 * Handle an incoming request.
 *
 * @param  \Illuminate\Http\Request  $request
 * @param  \Closure  $next
 * @param  string|null  $guard
 * @return mixed
 */
public function handle($request, Closure $next)
{
    app('ZipkinClient')->track('Authenticate', 'handle');

    ...
    code
    ...

    app('ZipkinClient')->finishTrack('Authenticate', 'handle');

    return $next($request);
}
 ```
 
 
# Tracking third party
 ```php
 class Foo extends Bar
{
    public function foo()
    {
        app('ZipkinClient')->track('Foo', __FUNCTION__);

        ..
        code
        ..

        app('ZipkinClient')->trackCall('Foo', 'third_party->call');
        $fooService->barMethod();
        app('ZipkinClient')->trackEndCall('Foo', 'third_party->call');

        app('ZipkinClient')->finishTrack('Foo');
    }
}
 ```


# Send propagation headers via Guzzle request
 ```php
$client = new Client();
$res = $client->request('GET', 'http://foo.com/bar', [
    'headers' => [
        'X-B3-TraceId' => (string) app('ZipkinClient')->getTraceId(),
        'X-B3-SpanId' => (string) app('ZipkinClient')->getTraceSpanId(),
        'X-B3-ParentId' => (string) app('ZipkinClient')->getTraceSpanId(),
        'X-B3-Sampled' => app('ZipkinClient')->isSampled()
    ]
]);
 ```
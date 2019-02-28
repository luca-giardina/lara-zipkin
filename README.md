# Lara-Zipkin
Wrapper for Laravel and Lumen using php-zipking


**Lumen Setup**

edit app.php

$app->register(\Giardina\LaraZipkin\LaraZipkinServiceProvider::class);


if you want to use the middleware to auto track requests add to $app->middleware([]);

\Giardina\LaraZipkin\Middleware\LaraZipkinMiddleware::class


**Laravel Setup**

edit app.php

providers -> Giardina\LaraZipkin\LaraZipkinServiceProvider::class

if you want to use the middleware to auto track requests add in Kernel.php in protected $middleware = [];

\Giardina\LaraZipkin\Middleware\LaraZipkinMiddleware::class

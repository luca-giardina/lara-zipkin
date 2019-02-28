<?php

namespace Giardina\LaraZipkin;

use Illuminate\Support\ServiceProvider;

use Zipkin\Endpoint;
use Zipkin\Samplers\BinarySampler;
use Zipkin\TracingBuilder;
use Zipkin\Reporters\Http;
use Zipkin\Reporters\Http\CurlFactory;
use Zipkin\Kind;
use Giardina\LaraZipkin\Models\ZipkinClient;
use Giardina\LaraZipkin\Models\ZipkinDisabled;

class LaraZipkinServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // bind the object zipkin
        $this->app->singleton('ZipkinClient', function($app)
        {
            if( ! env('ZIPKIN_TRACKING_ENABLED', false) )
                return new ZipkinDisabled();

            $ZipkinServerAddr = env('ZIPKIN_SERVER_ADDR') ?? '127.0.0.1';
            $ZipkinRemotePort = env('ZIPKIN_REMOTE_PORT') ?? '9411';
            $ZipkinRemoteApiPath = env('ZIPKIN_API_PATH') ?? '/api/v2/spans';
            $ZipkinRemoteSecure = env('ZIPKIN_REMOTE_HTTPS') ? 'https' : 'http';

            $endpoint = Endpoint::create(
                env('ZIPKIN_ENDPOINT_NAME') ?? PHP_SAPI,
                $app->request->server('SERVER_ADDR') ?? '127.0.0.1',
                null,
                $app->request->server('SERVER_PORT')
            );


            $reporter = new Http(CurlFactory::create(), [
                'endpoint_url' => $ZipkinRemoteSecure . "://" . $ZipkinServerAddr . ":" . $ZipkinRemotePort . $ZipkinRemoteApiPath
            ]);

            $sampler = BinarySampler::createAsAlwaysSample();

            $tracing = TracingBuilder::create()
                ->havingLocalEndpoint($endpoint)
                ->havingSampler($sampler)
                ->havingReporter($reporter)
                ->build();

            $ZipkinClient = new ZipkinClient($tracing);

            return $ZipkinClient;
        });
    }
}

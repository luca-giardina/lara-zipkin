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
            if( ! env('ZIPKIN_TRACING_ENABLED', false) )
                return new ZipkinDisabled();

            $ZipkinEndPointUrl = env('ZIPKIN_ENDPOINT_URL') ?? 'http://localhost:9411/api/v2/spans';

            $endpoint = Endpoint::create(
                env('ZIPKIN_ENDPOINT_NAME') ?? PHP_SAPI,
                $app->request->server('SERVER_ADDR') ?? '127.0.0.1',
                null,
                $app->request->server('SERVER_PORT')
            );


            $reporter = new Http(CurlFactory::create(), [
                'endpoint_url' => $ZipkinEndPointUrl
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

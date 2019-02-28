<?php

namespace Giardina\LaraZipkin\Models;

use Zipkin\Tracing;
use Zipkin\Tracer;
use Zipkin\Span;
use Zipkin\Propagation\TraceContext;
use Zipkin\Propagation\SamplingFlags;
use Zipkin\Propagation\Map;
use Zipkin\Timestamp;

class ZipkinClient
{
	private $tracing;
	protected $tracer;
	private $mainSpan;
    private $spans = [];

	public function __construct(Tracing $tracing)
	{
		$this->tracing = $tracing;
		$this->mainSpan = null;
	}

	public function getTracer() : Tracer
	{
		if( !$this->tracer )
			$this->tracer = $this->tracing->getTracer();

		return $this->tracer;
	}

	public function getCurrentSpan() : ?Span
	{
		return $this->mainSpan;
	}

	public function getNextSpan( $name = 'no-name', $kind = 'NOKIND', $headers = null ) : Span
	{
		$extractedContext = null;

		if( !empty($headers) )
		{
			$extractedContext = $this->getExractedContext($headers);
		}

		$span = $this->getTracer()->nextSpan($extractedContext);
		
		
		$span->setName($name);
		$span->setKind($kind);
		$span->start(Timestamp\now());

		$this->mainSpan = $span;
		return $span;
	}

	public function newChild( $name = 'no-name', $kind = 'NOKIND' ) : Span
	{
		if( !$this->mainSpan )
			$this->mainSpan = $this->getNextSpan('Generic', 'SERVER');

		$childSpan = $this->getTracer()->newChild($this->mainSpan->getContext());
		
		$childSpan->setName($name);
		$childSpan->setKind($kind);
		$childSpan->start(Timestamp\now());

		return $childSpan;

	}

	public function newChildFor($span = null, $name = 'no-name', $kind = 'NOKIND') {
		if( !$span ) 
			return null;
		
		$childSpan = $this->getTracer()->newChild($span->getContext());
		$childSpan->setName($name);
		$childSpan->setKind($kind);
		$childSpan->start(Timestamp\now());

		return $childSpan;
	}

	protected function getExractedContext( $headers )
	{
		$extractor = $this->tracing->getPropagation()->getExtractor(new Map());

		return $extractor($headers);
	}

	public function flush()
	{
		try {
			return $this->getTracer()->flush();
		}
		catch(Exception $e) {
			return false;
		}
		return true;
	}

	public function annotate($note)
	{
		$this->getCurrentSpan()->annotate($note, Timestamp\now());
	}

	public function annotateFor($spanName, $note = null) {
		if( $note )
			$this->spans[$spanName]->annotate($note, Timestamp\now());
	}

	public function finishCurrentSpan()
	{
		try{
			$this->mainSpan->finish();
			$this->mainSpan = $this->getTracer()->getCurrentSpan();
		}
		catch(Exception $e) {
			return false;
		}
		return $this->mainSpan;
	}

	public function finishAllSpan()
	{
		foreach ($this->spans as $span) {
			$span->finish();
		}
		while( $this->finishCurrentSpan() );
	}

	public function finishTrack($spanName, $method = null)
	{
		if(!isset($this->spans[$spanName]))
			return null;

		if($method) {
			$this->annotateFor($spanName, $method . '_finished');
		}
		$this->spans[$spanName]->finish();
	}

	public function getTraceId()
	{
		return $this->mainSpan->getContext()->getTraceId();
	}

	public function getTraceSpanId()
	{
		return $this->mainSpan->getContext()->getSpanId();
	}

	public function isSampled()
	{
		return $this->mainSpan->getContext()->isSampled();
	}

	public function track($spanName, $method, $kind = 'PRODUCER')
	{
        if( !isset($this->spans[$spanName]) )
            $this->spans[$spanName] = $this->newChild($spanName, $kind);

        $this->annotateFor($spanName, $method . '_starts');
	}

	public function trackCall($spanName, $callName = null, $kind = 'CONSUMER')
	{
        $this->spans[$spanName.$callName] = $this->newChildFor($this->spans[$spanName], $callName, $kind);
        $this->annotateFor($spanName.$callName, $callName . '_starts');
	}

	public function trackEndCall($spanName, $callName)
	{
		$this->finishTrack($spanName.$callName, $callName);
	}

	public function joinCurrentSpan($traceId, $parentId = null)
	{
		if(!$traceId)
			return null;

		$joinContext = TraceContext::create(
	        $traceId,
	        $this->getTraceSpanId(),
	        $parentId,
	        true
		);

		$this->mainSpan = $this->getTracer()->joinSpan($joinContext);
		$this->getTracer()->openScope($this->mainSpan);
	}

	public function tagBy($name, $value)
	{
		$this->mainSpan->tag($name, $value);
	}

}

?>

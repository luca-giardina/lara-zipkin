<?php

namespace Giardina\LaraZipkin\Models;

class ZipkinDisabled
{

	public function getTracer()
	{
		return null;
	}

	public function getCurrentSpan()
	{
		return null;
	}

	public function getNextSpan( $name = 'no-name', $kind = 'NOKIND', $headers = null )
	{
		return null;
	}

	public function newChild( $name = 'no-name', $kind = 'NOKIND' )
	{
		return null;

	}

	public function newChildFor($span = null, $name = 'no-name', $kind = 'NOKIND') {
		return null;
	}

	protected function getExractedContext( $headers )
	{
		return null;
	}

	public function flush()
	{
		return true;
	}

	public function annotate($note)
	{
	}

	public function annotateFor($spanName, $note = null)
	{
	}

	public function finishCurrentSpan()
	{
		return null;
	}

	public function finishAllSpan()
	{
	}

	public function finishTrack($spanName, $method = null)
	{
	}

	public function getTraceId()
	{
		return '';
	}

	public function getTraceSpanId()
	{
		return '';
	}

	public function isSampled()
	{
		return false;
	}

	public function track($spanName, $method, $kind = 'PRODUCER')
	{
	}

	public function trackCall($spanName, $callName = null, $kind = 'CONSUMER')
	{
	}

	public function trackEndCall($spanName, $callName)
	{
	}

	public function joinCurrentSpan($traceId, $parentId = null)
	{
	}

	public function tagBy($name, $value)
	{
	}
}

?>

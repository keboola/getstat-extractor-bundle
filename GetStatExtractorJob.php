<?php

namespace Keboola\GetStatExtractorBundle;

use Keboola\ExtractorBundle\Extractor\Jobs\JsonRecursiveJob,
	Keboola\ExtractorBundle\Common\Logger;
use	Keboola\Utils\Utils;
use Syrup\ComponentBundle\Exception\SyrupComponentException;
use	Keboola\Code\Builder;

class GetStatExtractorJob extends JsonRecursiveJob
{
	protected $configName;
	/**
	 * @var array
	 */
	protected $configMetadata;

	/**
	 * @var Builder
	 */
	protected $stringBuilder;

	/**
	 * Return a download request
	 *
	 * @return \Keboola\ExtractorBundle\Client\SoapRequest | \GuzzleHttp\Message\Request
	 */
	protected function firstPage()
	{
		$params = Utils::json_decode($this->config["params"], true);
		if (!empty($params)) {
			try {
				foreach($params as $key => &$value) {
					if (is_object($value)) {
						$value = $this->stringBuilder->run($value, ['metadata' => $this->configMetadata]);
					}
					unset($value);
				}
			} catch(UserScriptException $e) {
				throw new UserException("User function failed: " . $e->getMessage());
			}
		}

		$url = Utils::buildUrl(ltrim($this->config["endpoint"], "/"), $params);

		$this->configName = preg_replace("/[^A-Za-z0-9\-\._]/", "_", trim($this->config["endpoint"], "/"));

		return $this->client->createRequest("GET", $url);
	}

	/**
	 * Return a download request OR false if no next page exists
	 *
	 * @param $response
	 * @return \Keboola\ExtractorBundle\Client\SoapRequest | \GuzzleHttp\Message\Request | false
	 */
	protected function nextPage($response, $data)
	{
		if (empty($response->attributes()->nextpage)) {
			return false;
		}

		return $this->client->createRequest("GET", ltrim($response->attributes()->nextpage, "/"));
	}

	/**
	 * Call the parser and handle its return value
	 *
	 * @param object $response
	 */
	protected function parse($response, $parentId = null)
	{
		if (!empty($response->attributes()->totalresults)) {
			$data = $this->parser->xmlToObject($response);

			return parent::parse($data, $parentId);
		} else {
			Logger::log("warning", "No results returned by {$this->configName}", (array) $response->attributes());
		}
	}

	/**
	 * Download an URL from REST API and return its body as XML object
	 *
	 * @param \GuzzleHttp\Message\Request $request
	 * @return object - response body
	 */
	protected function download($request)
	{
		return parent::download($request, \Keboola\ExtractorBundle\Extractor\Jobs\RestJob::XML);
	}

	public function setConfigMetadata(array $data)
	{
		$this->configMetadata = $data;
	}

	/**
	 * @param Builder $builder
	 */
	public function setBuilder(Builder $builder)
	{
		$this->stringBuilder = $builder;
	}
}

<?php

namespace Keboola\GetStatExtractorBundle;

use Keboola\ExtractorBundle\Extractor\Extractors\JsonExtractor as Extractor,
	Keboola\ExtractorBundle\Config\Config;
use Syrup\ComponentBundle\Exception\SyrupComponentException,
	Syrup\ComponentBundle\Exception\UserException;
use GuzzleHttp\Client as Client;
use Keboola\GetStatExtractorBundle\GetStatExtractorJob,
	Keboola\GetStatExtractorBundle\Parser\Xml;
use	Keboola\Code\Builder;
use	Keboola\Json\Parser as JsonParser;

class GetStatExtractor extends Extractor
{
	protected $name = "getstat";

	public function __construct($memory_limit = '512M')
	{
		ini_set('memory_limit', $memory_limit);
	}

	public function run(Config $config)
	{
		$apiKey = $config->getAttributes()['apiKey'];
		$client = new Client(
			["base_url" => "http://app.getstat.com/api/v2/{$apiKey}/"]
		);
		$client->getEmitter()->attach($this->getBackoff(8, [500, 502, 503, 504, 408, 420, 429]));

		if (!empty($this->metadata['json_parser.struct']) && is_string($this->metadata['json_parser.struct'])) {
			$struct = unserialize($this->metadata['json_parser.struct']);
			if (!is_array($struct)) {
				$struct = [];
			}
		} else {
			$struct = [];
		}

		$parser = Xml::create(\Monolog\Registry::getInstance('extractor'), $struct);
		$parser->getStruct()->setAutoUpgradeToArray(true);
		$builder = new Builder();

		foreach($config->getJobs() as $jobConfig) {
			$this->metadata['jobs.lastStart.' . $jobConfig->getJobId()] =
				empty($this->metadata['jobs.lastStart.' . $jobConfig->getJobId()])
					? 0
					: $this->metadata['jobs.lastStart.' . $jobConfig->getJobId()];
			$this->metadata['jobs.start.' . $jobConfig->getJobId()] = time();

			$job = new GetStatExtractorJob($jobConfig, $client, $parser);
			$job->setConfigMetadata($this->metadata);
			$job->setBuilder($builder);
			$job->run();

			$this->metadata['jobs.lastStart.' . $jobConfig->getJobId()] = $this->metadata['jobs.start.' . $jobConfig->getJobId()];

		}

		$this->updateParserMetadata($parser);

		return $parser->getCsvFiles();
	}

	protected function updateParserMetadata(JsonParser $parser)
	{
		if ($parser->hasAnalyzed()) {
			$this->metadata['json_parser.struct'] = serialize($parser->getStruct()->getStruct());
		}
	}
}

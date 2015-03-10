<?php

namespace Keboola\GetStatExtractorBundle\Parser;

use Syrup\ComponentBundle\Exception\SyrupComponentException,
	Syrup\ComponentBundle\Exception\UserException;
use	Keboola\Json\Parser;

class Xml extends Parser
{
	/**
	 * @param \SimpleXMLElement $xml
	 * @return object
	 */
	public function xmlToObject(\SimpleXMLElement $xml)
	{
		$data = json_decode(json_encode($xml));
		return $this->nullEmptyObjects($data);
	}

	/**
	 * Eliminate empty {} in the object, making them NULL (perhaps JSON parser should treat them that way?)
	 * @param array|object $data
	 * @return mixed
	 */
	protected function nullEmptyObjects($data)
	{
		foreach($data as $k => &$v) {
			if (empty($v) || (is_object($v) && $this->isEmptyObject($v))) {
				$v = null;
			} elseif (!is_scalar($v)) {
				$v = $this->nullEmptyObjects($v);
			}
			unset($v);
		}
		return $data;
	}
}
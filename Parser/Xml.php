<?php

namespace Keboola\GetStatExtractorBundle\Parser;

use Syrup\ComponentBundle\Exception\SyrupComponentException,
	Syrup\ComponentBundle\Exception\UserException;
use	Keboola\Json\Parser;
use	Keboola\Utils\Utils;

class Xml extends Parser
{
	/**
	 * @param \SimpleXMLElement $xml
	 * @return object
	 */
	public function xmlToObject(\SimpleXMLElement $xml)
	{
		$data = json_decode(json_encode($xml));
		$data = $this->fixNullValues($data);
		$data = $this->nullEmptyObjects($data);
        return $this->fixResult($data);
	}

	/**
	 * Eliminate empty {} in the object, making them NULL (perhaps JSON parser should treat them that way?)
	 * @param array|object $data
	 * @return mixed
	 */
	protected function nullEmptyObjects($data)
	{
		foreach($data as $k => &$v) {
			if (empty($v) || (is_object($v) && Utils::isEmptyObject($v))) {
				$v = null;
			} elseif (!is_scalar($v)) {
				$v = $this->nullEmptyObjects($v);
			}
			unset($v);
		}
		return $data;
	}

	protected function fixNullValues($data)
	{
		foreach($data as $k => &$v) {
			if (is_scalar($v) && $v == 'none') {
				$v = null;
			} elseif (!is_scalar($v)) {
				$v = $this->fixNullValues($v);
			}
			unset($v);
		}
		return $data;
	}

	private function fixResult($data)
    {
        // if there is only one Result in response in is converted to object instead to array of one object
        // so we are fixing it here
        if (isset($data->Result) && !is_array($data->Result)) {
            $data->Result = [$data->Result];
        }
        return $data;
    }
}

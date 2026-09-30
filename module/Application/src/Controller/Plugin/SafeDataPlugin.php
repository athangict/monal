<?php
namespace Application\Controller\Plugin;
 
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class SafeDataPlugin extends AbstractPlugin{
	
	public function rteSafe($data){
		if (is_array($data) ):
			foreach($data as $key => $value):
				$data[$key] = $this->safe($value);
			endforeach;
		else:
			$data = $this->safe($data);
		endif;
		
		return $data;
	}
	
	/**
	 * actual function to do necessary action
	 */
	public function safe($data)
	{
		if (is_array($data)) {
			foreach ($data as $key => $value) {
				$data[$key] = $this->safe($value);
			}
			return $data;
		}

		if ($data === null) {
			return '';
		}

		if (is_bool($data)) {
			$data = $data ? '1' : '0';
		} elseif (is_scalar($data)) {
			$data = (string) $data;
		} elseif (is_object($data) && method_exists($data, '__toString')) {
			$data = (string) $data;
		} else {
			return '';
		}

		$data = trim($data);
		$data = strip_tags($data);
		$data = htmlentities($data, ENT_QUOTES, 'UTF-8'); // convert funky chars to html entities
		$pat = array("\r\n", "\n\r", "\n", "\r"); // remove returns
		$data = str_replace($pat, '', $data);
		$pat = array('/^\s+/', '/\s{2,}/', '/\s+\$/'); // remove multiple whitespaces
		$rep = array('', ' ', '');
		$data = preg_replace($pat, $rep, $data);
		$data = trim($data);
		
		return $data;
	}
}

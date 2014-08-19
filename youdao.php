<?php
/**
 * 利用有道翻译 API 接口达到翻译的目的
 *
 * @author icyleaf <icyleaf.cn@gmail.com>
 */
require_once('workflows.php');


class YouDaoTranslation
{
	private $_url = "http://fanyi.youdao.com/openapi.do"; //?keyfrom=$from&key=$key&type=data&doctype=json&version=1.1&q=$q"
	private $_query = null;

	private $_workflow = null;
	private $_data = array();

	public static function factory($from, $key, $q)
	{
		return new YouDaoTranslation($from, $key, $q);
	}

	public function __construct($from, $key, $q)
	{
		$this->_workflow = new Workflows();

		$this->_query = $q;

		$this->_url .= '?' . http_build_query(array(
			'keyfrom'	=> $from,
			'key'		  => $key,
			'type'		=> 'data',
			'doctype'	=> 'json',
			'version'	=> '1.1',
			'q'			  => $q,
			));

		$this->_data = json_decode($this->_workflow->request($this->_url));
	}

	public function postToNotification()
	{
		$output   = array();
		$response = $this->_data;
		if (isset($response->translation) AND isset($response->translation[0])) {
			if ($this->_query != str_replace('\\', '', $response->translation[0])) {
				$output[] = $response->translation[0];
				if (isset($response->basic) AND 
					  isset($response->basic->explains) AND 
					  count($response->basic->explains) > 0) {
					foreach ($response->basic->explains as $item) 
					{
						$output[] = $item;
					}
				}
			}
		} else $output[] = "有道翻译也爱莫能助了, 你确定翻译的是: '$this->_query' ?";
		return implode("\n", $output);
	}

	public function listInAlfred()
	{
		$i = crc32($this->_query) * 100;

		$argument = $this->postToNotification();
		$response = $this->_data;

		do {

			if (empty($response->translation))     break;
			if (!is_array($response->translation)) break;

			$translation = implode(", ", $response->translation);
			$this->_workflow->result($i++, $translation, $translation, '翻译释意: ' . $response->query, 'icon.png');

			$phonetic = array();
			if (!empty($response->basic->{"us-phonetic"}) && !empty($response->basic->{"uk-phonetic"})) {
				$phonetic[] = "英 [{$response->basic->{"uk-phonetic"}}]";
				$phonetic[] = "美 [{$response->basic->{"us-phonetic"}}]";
			} else if (!empty($response->basic->phonetic)) {
				$phonetic[] = "[{$response->basic->phonetic}]";
			}
			if (count($phonetic)) {
				$phonetic = implode(", ", $phonetic);
				$this->_workflow->result($i++, $phonetic, $phonetic, '声标发音: ' . $response->query, 'icon.png');
			}

			if (isset($response->basic->explains) && count($response->basic->explains))
			foreach($response->basic->explains as $item) {
				$this->_workflow->result($i++, $item, $item, '简明释义: ' . $response->query, 'icon.png');
			}

			if (isset($response->web) && count($response->web))
			foreach($response->web as $item) {
				$values = implode(', ', $item->value);
				$this->_workflow->result($i++, $values, $values, "网络释义: $item->key", 'icon.png');
			}

		} while (0);

		$results = $this->_workflow->results();
		if (count($results) == 0)
			$this->_workflow->result('youdao', $this->_query, '有道翻译也爱莫能助了', '有道翻译也爱莫能助了，尝试网站搜索: '.$this->_query, 'icon.png' );

		return $this->_workflow->toxml();
	}
}

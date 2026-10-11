<?php

namespace Piwik\Plugins\DDBotTracker;

use Piwik\Piwik;
use Piwik\Common;
use Piwik\Config;
use Piwik\Db;
use Piwik\Tracker;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugin\ReportsProvider;
use Piwik\Container\StaticContainer;
use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;

class DDBotTracker extends \Piwik\Plugin {
	private $clsName = 'DDBotTracker';
	public function install() {
		$query =  'CREATE TABLE IF NOT EXISTS `'.Common::prefixTable('ddbot_visits').'` (
			`visit_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			`site_id` INTEGER(10) UNSIGNED NOT NULL,
			`visit_ts` DATETIME NOT NULL,
			`bot_name` VARCHAR(256) NOT NULL,
			`bot_category` VARCHAR(256),
			`bot_url` VARCHAR(256),
			`bot_producer_name` VARCHAR(256),
			`bot_producer_url` VARCHAR(256),
			`page` VARCHAR(256) NOT NULL,
			`useragent` VARCHAR(256) NOT NULL,
			PRIMARY KEY(`visit_id`),
			INDEX `idx_site_bot_visit` (`site_id`, `bot_name`, `visit_ts`)
			) DEFAULT CHARSET=utf8';
		try {
			Db::exec($query);
		} catch (\Exception $e) {
			throw $e;
		}
	}

	public function uninstall()	{
		Db::exec('DROP TABLE `'.Common::prefixTable('ddbot_visits').'`');
	}

	public function registerEvents() {
		return [
			'Tracker.isExcludedVisit' => 'checkBot',
			'AssetManager.getStylesheetFiles' => 'getCSS',
			'AssetManager.getJavaScriptFiles' => 'getJS',
			'ViewDataTable.configure.end' => 'configureViewEnd',
			'Widget.filterWidgets' => 'filterWidgets'
		];
	}

	public function getCSS(&$files) {
		$files[] = 'plugins/'.$this->clsName.'/stylesheets/datatable.css';
		$files[] = 'plugins/'.$this->clsName.'/stylesheets/widget.css';
	}

	public function getJS(&$files) {
		$files[] = 'plugins/'.$this->clsName.'/javascripts/datatable.js';
	}

	public function configureViewEnd(ViewDataTable $view) {
		switch ($view->requestConfig->apiMethodToRequestDataTable) {
			case 'DDBotTracker.getVisitsReport':
				$view->publicReport->configureViewFinal($view);
				break;
		}
	}

	public function filterWidgets($list) {
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$isDashboardPicker = false;
		foreach ($backtrace as $frame)
		if (isset($frame['function']) && $frame['function'] === 'getWidgetMetadata') {
			$isDashboardPicker = true;
			break;
		}
		if (!$isDashboardPicker)
		$list->remove('General_Visitors', 'DDBotTracker_Widget_BotsVsVisitors');
    }

	public function checkBot(&$exclude, $request) {
		$userAgent = $request->getUserAgent();
		$deviceDetector = new DeviceDetector($userAgent, ClientHints::factory($request->getClientHints()));
		$deviceDetector->setCache(StaticContainer::get('DeviceDetector\Cache\Cache'));
		$deviceDetector->parse();
		if (!$deviceDetector->isBot()) return;
		$botData = $deviceDetector->getBot();
		$query = "INSERT INTO `".Common::prefixTable('ddbot_visits')."`
		(site_id, visit_ts, bot_name, bot_category, bot_url, bot_producer_name, bot_producer_url, page, useragent) VALUES (?,FROM_UNIXTIME(?),?,?,?,?,?,?,?)";
		$params = [
			$request->getIdSite(),
			time(),
			substr($botData['name'], 0, 256),
			isset($botData['category']) ? substr($botData['category'], 0, 256) : NULL,
			isset($botData['url']) ? substr($botData['url'], 0, 256) : NULL,
			isset($botData['producer']['name']) ? substr($botData['producer']['name'], 0, 256) : NULL,
			isset($botData['producer']['url']) ? substr($botData['producer']['url'], 0, 256) : NULL,
			substr($request->getParam('url'), 0, 256),
			substr($userAgent, 0, 256)
		];
		//Db::query("SET time_zone = '+00:00'");
		Db::query($query, $params);
		$exclude = true;
	}
}

?>
<?php

namespace Piwik\Plugins\DDBotTracker;

use Piwik\Db;
use Piwik\Date;
use Piwik\Common;
use Piwik\Plugin\Manager as PluginManager;
use Piwik\Plugins\PrivacyManager\PrivacyManager;

class Tasks extends \Piwik\Plugin\Tasks {
	public function schedule() {
		$this->daily('purgeBotVisits');
	}

	public function purgeBotVisits() {
		if (!PluginManager::getInstance()->isPluginActivated('PrivacyManager')) return;

		$settings = PrivacyManager::getPurgeDataSettings();
		if (empty($settings['delete_logs_enable'])) return;

		$days = (int)$settings['delete_logs_older_than'];
		if ($days < 1) return;

		$cutoff = Date::today()->subDay($days)->getTimestampUTC();
		$batch = !empty($settings['delete_logs_max_rows_per_query'])
			? (int)$settings['delete_logs_max_rows_per_query']
			: 100000;

		do {
			$deleted = Db::query(
				'DELETE FROM `'.Common::prefixTable('ddbot_visits').'` WHERE `visit_ts` < FROM_UNIXTIME(?) LIMIT '.$batch,
				[$cutoff]
			)->rowCount();
		} while ($deleted > 0);
	}
}

?>
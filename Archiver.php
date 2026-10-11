<?php

namespace Piwik\Plugins\DDBotTracker;

class Archiver extends \Piwik\Plugin\Archiver {

	const VISITS_RECORD_NAME = 'DDBotTracker_BotArchive_Visits';
	const PAGES_RECORD_NAME = 'DDBotTracker_BotArchive_Pages';

	public static function shouldRunEvenWhenNoVisits(): bool {
		return true;
	}

}

?>
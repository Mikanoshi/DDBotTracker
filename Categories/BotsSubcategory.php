<?php

namespace Piwik\Plugins\DDBotTracker\Categories;

use Piwik\Piwik;
use Piwik\Category\Subcategory;

class BotsSubcategory extends Subcategory {
	protected $categoryId = 'General_Visitors';
	protected $id = 'DDBotTracker_BotTracker';
	protected $order = 98;

	public function getHelp() {
		return '<p>'.Piwik::translate('DDBotTracker_PluginDescription').'</p>';
	}
}

?>
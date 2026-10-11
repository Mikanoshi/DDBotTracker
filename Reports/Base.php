<?php

namespace Piwik\Plugins\DDBotTracker\Reports;

use Piwik\Container\StaticContainer;
use Piwik\Plugins\DDBotTracker\ReportHelpers;
use Piwik\Plugins\DDBotTracker\Columns\Label;

abstract class Base extends \Piwik\Plugin\Report {
	protected $reportHelpers;
	
	protected function init() {
		parent::init();

		$this->dimension = new Label();
		$this->reportHelpers = StaticContainer::get(ReportHelpers::class);
		$this->categoryId = 'General_Visitors';
		$this->subcategoryId = 'DDBotTracker_BotTracker';
		$this->metrics = ['nb_actions'];
		$this->processedMetrics = [];
	}

	public function isEnabled() {
		return true;
	}
}

?>
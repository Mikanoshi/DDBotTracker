<?php

namespace Piwik\Plugins\DDBotTracker;

use Piwik\Piwik;
use Piwik\DataTable;
use Piwik\Container\StaticContainer;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\DDBotTracker\UserSettings;

class ReportHelpers {
	
	public function applyReverseSorting(ViewDataTable &$view, $columns) {
		if (StaticContainer::get(UserSettings::class)->reverseTextSort->getValue()) {
			$view->config->filters['reversesort_pre_filter'] = [
				function (DataTable $table) use ($view, $columns) {
					if (in_array($view->requestConfig->filter_sort_column, $columns)) {
						$view->requestConfig->filter_sort_order = ($view->requestConfig->filter_sort_order === 'desc') ? 'asc' : 'desc';
						$_GET['filter_sort_order']  = $view->requestConfig->filter_sort_order;
					}
				}, [], true
			];
			$view->config->filters['reversesort_post_filter'] = [
				function (DataTable $table) use ($view, $columns) {
					if (in_array($view->requestConfig->filter_sort_column, $columns)) {
						$view->requestConfig->filter_sort_order = ($view->requestConfig->filter_sort_order === 'desc') ? 'asc' : 'desc';
						$_GET['filter_sort_order']  = $view->requestConfig->filter_sort_order;
					}
				}, [], false
			];
		}
	}
}

?>
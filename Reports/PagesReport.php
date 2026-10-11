<?php

namespace Piwik\Plugins\DDBotTracker\Reports;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\DataTable;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\Insights\Visualizations\Insight;
use Piwik\Plugins\DDBotTracker\ReportHelpers;
use Piwik\Widget\WidgetsList;
use Piwik\Report\ReportWidgetFactory;

class PagesReport extends Base {
	protected function init() {
		parent::init();

		$this->name = Piwik::translate('DDBotTracker_Report_Pages');
		$this->documentation = Piwik::translate('DDBotTracker_ReportDocumentationPages');
		$this->actionToLoadSubTables = 'getPagesReport';
		$this->action = 'getPagesReport';
		$this->supportsFlatten = true;
		$this->recursiveLabelSeparator = '​';
	}

	public function configureView(ViewDataTable $view) {
		$view->config->translations = [
			'label' => Piwik::translate('DDBotTracker_Page'),
			'most_frequent_bot' => Piwik::translate('DDBotTracker_MostFrequent'),
			'nb_actions' => Piwik::translate('DDBotTracker_BotActionCount')
		];
		$view->config->columns_to_display = ['label','most_frequent_bot','nb_actions'];
		$view->config->report_ratio_columns = ['nb_actions'];
		$view->config->export_parameters_to_modify = [
			'showColumns' => 'page,most_frequent_bot,nb_actions',
			'hideColumns' => 'label'
		];
		$this->reportHelpers->applyReverseSorting($view, ['label','page','most_frequent_bot']);
		$view->config->related_reports_title = '​';
		$view->config->tooltip_metadata_name = 'tooltip';
		$view->config->show_search = false;
		$view->config->show_footer_icons = true;
		$view->config->show_exclude_low_population = false;
		$view->config->show_table_all_columns = false;
		$view->config->show_insights = true;
		$view->config->show_related_reports  = true;
		$view->config->show_pivot_by_subtable = false;
		$view->config->show_table_performance = false;
		//$view->config->show_all_views_icons = false;
		$view->config->show_bar_chart = false;
		$view->config->show_tag_cloud = false;
		$view->config->show_pie_chart = false;
		$view->config->show_totals_row = false;
		$view->config->show_summary_row = false;
		$view->config->show_export = true;
		$view->config->show_flatten_table = true;
		$view->config->show_flatten_table_export = true;
		$view->config->disable_row_evolution = false;
		$view->requestConfig->filter_limit = 10;
		$view->requestConfig->filter_sort_column = 'nb_actions';
		$view->requestConfig->filter_sort_order = 'desc';
		//$view->requestConfig->show_dimensions = false;
		if ($view->isViewDataTableId(Insight::ID)) {
			$view->requestConfig->flat = true;
		}
	}

	public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory) {
		$widgetsList->addWidgetConfig($factory->createWidget()->setOrder(2));
	}
}

?>
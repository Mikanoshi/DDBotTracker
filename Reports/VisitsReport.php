<?php

namespace Piwik\Plugins\DDBotTracker\Reports;

use Piwik\Request;
use Piwik\Piwik;
use Piwik\Proxy;
use Piwik\Site;
use Piwik\Date;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Widget\WidgetsList;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\Insights\Visualizations\Insight;
use Piwik\Plugins\CoreVisualizations\Visualizations\Cloud;
use Piwik\Plugins\CoreVisualizations\Visualizations\Sparkline;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Pie;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Bar;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;
use Piwik\Plugins\DDBotTracker\DataHelpers;

class VisitsReport extends Base {

	protected function generateReportName($suffix = '') {
		return Piwik::translate('DDBotTracker_Report_Actions').($suffix == '' ? '' : ' - '.$suffix);
	}

	protected function generateReportDoc($custom = '') {
		return $custom != '' ? $custom : Piwik::translate('DDBotTracker_ReportDocumentationVisits');
	}

	protected function init() {
		unset($_GET['custom_report_name']);
		unset($_GET['custom_report_doc']);
		parent::init();
		
		$this->name = $this->generateReportName();
		$this->documentation = $this->generateReportDoc();
		$this->action = 'getVisitsReport';
	}

	private function getBotImage($category) {
		$catimg = $category == '' ? 'generic-bot' : str_replace(' ', '-', strtolower($category));
		$catimg = match ($catimg) {
			'site-monitor', 'network-monitor' => 'monitor',
			'search-tools', 'security-search-bot' => 'search-bot',
			default => $catimg
		};
		return 'plugins/'.$this->module.'/images/bot_categories/'.$catimg.'.svg';
	}

	public function configureView(ViewDataTable $view) {
		$view->publicReport = $this;
		$view->config->datatable_css_class = 'ddbottracker-visits';
		$view->config->translations = [
			'label' => Piwik::translate('DDBotTracker_BotName'),
			'bot_category' => Piwik::translate('DDBotTracker_BotCategory'),
			'bot_producer_name' => Piwik::translate('DDBotTracker_BotProducer'),
			'visit_ts' => Piwik::translate('DDBotTracker_BotLastVisit'),
			'nb_actions' => Piwik::translate('DDBotTracker_BotActionCount')
		];
		$view->config->export_parameters_to_modify = [
			'showColumns' => 'bot_name,bot_category,bot_producer_name,bot_producer_url,useragent,visit_ts,nb_actions',
			'hideColumns' => 'label'
		];
		$view->config->filters['display_filter'] = [
			function (DataTable $table) use ($view) {
				$idSite = Request::fromRequest()->getIntegerParameter('idSite', 0);
				foreach ($table->getRows() as $row) {
					$row->setMetadata('logo', $this->getBotImage($row->getColumn('bot_category')));
					$row->setMetadata('logoWidth', 16);
					$row->setMetadata('logoHeight', 16);
					$prod_name = $row->getColumn('bot_producer_name');
					if (mb_strlen($prod_name) >= 30)
					$row->setMetadata('bot_producer_name_tooltip', $prod_name);
					$row->setColumn('bot_producer_name', $prod_name);
					$ts = $idSite > 0 ? Date::adjustForTimezone($row->getColumn('visit_ts'), Site::getTimezoneFor($idSite)) : $row->getColumn('visit_ts');
					$row->setColumn('visit_ts', gmdate('Y-m-d H:i:s', $ts));
				}
			}, [], true
		];
		$view->config->columns_to_display = ['label','nb_actions'];
		$view->config->report_ratio_columns = ['nb_actions'];
		$view->config->show_footer_icons = true;
		$view->config->show_exclude_low_population = false;
		$view->config->show_table_all_columns = false;
		$view->config->show_search = false;
		$view->config->show_insights = true;
		$view->config->show_related_reports  = false;
		$view->config->show_pivot_by_subtable = false;
		$view->config->show_table_performance = false;
		//$view->config->show_all_views_icons = false;
		$view->config->show_bar_chart = true;
		$view->config->show_tag_cloud = true;
		$view->config->show_pie_chart = true;
		$view->config->show_export = true;
		$view->config->disable_row_evolution = false;
		$view->config->tooltip_metadata_name = 'tooltip';
		$view->requestConfig->filter_limit = 10;
		$view->requestConfig->filter_sort_column = 'nb_actions';
		$view->requestConfig->filter_sort_order = 'desc';
		if ($view->isViewDataTableId(Insight::ID)) {
			$view->config->datatable_js_type = 'VisitsDataTable';
			$view->config->display_logo_instead_of_label = false;
			$view->config->export_parameters_to_modify['showColumns'] = 'label,nb_actions,growth_percent,growth_percent_numeric,grown,value_old,value_new,difference';
			$view->config->export_parameters_to_modify['hideColumns'] = '';
		} else if ($view->isViewDataTableId(Cloud::ID)) {
			$view->config->datatable_js_type = 'VisitsDataTable';
		} else if ($view->isViewDataTableId(Pie::ID)) {
			//$view->config->datatable_js_type = 'VisitsPieDataTable';
			$view->config->selectable_columns = ['nb_actions'];
			$view->requestConfig->addPropertiesThatShouldBeAvailableClientSide(['pie_expanded']);
			$view->requestConfig->addPropertiesThatCanBeOverwrittenByQueryParams(['pie_expanded']);
			$view->config->custom_parameters = ['pie_expanded' => false];
			//$view->config->show_footer_message = '';
		} else if ($view->isViewDataTableId(Bar::ID)) {
			$view->config->selectable_columns = ['nb_actions'];
			$view->config->export_parameters_to_modify['hideColumns'] = '';
			$view->config->show_all_ticks = true;
		} else if ($view->isViewDataTableId(Sparkline::ID)) {
			$view->config->hide_annotations_view = true;
		} else if ($view->isViewDataTableId(Evolution::ID)) {
			$view->config->show_line_graph = true;
			$view->config->hide_annotations_view = false;
			$view->config->show_export = false;
		} else {
			//\Piwik\Log::warning('getViewDataTableId: '.$view->getViewDataTableId());
			$view->config->datatable_js_type = 'VisitsDataTable';
			//$view->requestConfig->request_parameters_to_modify['keep_totals_row_label'] = '';
			$view->config->columns_to_display = ['label','bot_category','bot_producer_name','visit_ts','nb_actions'];
			$this->reportHelpers->applyReverseSorting($view, ['label','bot_name','bot_category','bot_producer_name']);
			// Clean up totals row
			$view->config->filters['custom_filter'] = function (DataTable $table) use ($view) {
				if (!property_exists($view->requestConfig, 'keep_totals_row')) return;
				if (!$view->requestConfig->keep_totals_row) return;
				$totalsRow = $table->getTotalsRow();
				if ($totalsRow)
				foreach (DataHelpers::$AGGREGATION_RULES as $columnName => $operation)
				if (is_callable($operation)) $totalsRow->setColumn($columnName, '​');
			};
		}
	}

	private function setExpanded($view, $expanded) {
		if ($expanded) {
			$view->config->max_graph_elements = 11;
			$view->config->datatable_css_class .= ' ddbottracker-graph-expanded';
			$view->config->custom_parameters['custom_report_name'] = $this->generateReportName(Piwik::translate('DDBotTracker_Report_Top10'));
			$view->config->datatable_actions[] = [
				'id' => 'ddBotTrackerShrink',
				'title' => Piwik::translate('DDBotTracker_Shrink'),
				'icon' => 'icon-minimise'
			];
		} else {
			$view->config->custom_parameters['custom_report_name'] = $this->generateReportName(Piwik::translate('DDBotTracker_Report_Top5'));
			$view->config->max_graph_elements = 6;
			$view->config->datatable_actions[] = [
				'id' => 'ddBotTrackerExpand',
				'title' => Piwik::translate('DDBotTracker_Expand'),
				'icon' => 'icon-maximise'
			];
		}
	}

	public function configureViewFinal(ViewDataTable $view) {
		$from_widget = Request::fromRequest()->getParameter('widget', false) === '1';
		if ($view->isViewDataTableId(Pie::ID)) {
			$pie_expanded_request_get = Request::fromGet()->getParameter('pie_expanded', false);
			$pie_expanded_request_post = Request::fromPost()->getParameter('pie_expanded', false);
			$pie_expanded_db = $view->config->custom_parameters['pie_expanded'] ?? false ;
			$pie_expanded = $pie_expanded_request_post !== false ? ($pie_expanded_request_post === '1') : ($from_widget ? ($pie_expanded_request_get === '1') : ($pie_expanded_db == '1'));
			$view->config->custom_parameters['custom_report_doc'] = $this->generateReportDoc(Piwik::translate('DDBotTracker_ReportDocumentationVisitsGraph'));
			$this->setExpanded($view, $pie_expanded);
			$view->config->filters['custom_filter'] = function (DataTable $table) use ($view, $pie_expanded, $from_widget) {
				foreach ($table->getRows() as $row) {
					if ($row->isSummaryRow()) return;
					$new_label = $row->getColumn('label');
					if (!$pie_expanded && !$from_widget && $row->getColumn('bot_producer_name'))
					$new_label .= ' by '.$row->getColumn('bot_producer_name');
					if (!$from_widget && $row->getColumn('bot_category'))
					$new_label .= ' ('.ucwords($row->getColumn('bot_category')).')';
					$row->setColumn('label', $new_label);
				}
			};
		} else if ($view->isViewDataTableId(Bar::ID)) {
			$bar_expanded_request_get = Request::fromGet()->getParameter('bar_expanded', false);
			$bar_expanded_request_post = Request::fromPost()->getParameter('bar_expanded', false);
			$bar_expanded_db = $view->config->custom_parameters['bar_expanded'] ?? false ;
			$bar_expanded = $bar_expanded_request_post !== false ? ($bar_expanded_request_post === '1') : ($from_widget ? ($bar_expanded_request_get === '1') : ($bar_expanded_db == '1'));
			$view->config->custom_parameters['custom_report_doc'] = $this->generateReportDoc(Piwik::translate('DDBotTracker_ReportDocumentationVisitsGraph'));
			$this->setExpanded($view, $bar_expanded);
			// $view->config->filters['custom_filter'] = function (DataTable $table) use ($view, $bar_expanded) {
			// 	foreach ($table->getRows() as $row) {
			// 		if ($row->isSummaryRow()) return;
			// 		$label = $row->getColumn('label');
			// 		if (mb_strlen($label) > 18)
			// 		$row->setColumn('label', mb_substr($label, 0, 18).'…');
			// 	}
			// };
		} else {
			$view->config->custom_parameters['custom_report_name'] = $this->generateReportName();
			$view->config->custom_parameters['custom_report_doc'] = $this->generateReportDoc();
		}
	}

	public function getRelatedReports()	{
		return [];
	}

	public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory) {
		$widgetsList->addWidgetConfig($factory->createWidget()->setOrder(1));
	}
}

?>
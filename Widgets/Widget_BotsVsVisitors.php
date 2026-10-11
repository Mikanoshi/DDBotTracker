<?php

namespace Piwik\Plugins\DDBotTracker\Widgets;

use Piwik\Piwik;
use Piwik\Site;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\NumberFormatter;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\API\Request;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;
use Piwik\Plugins\CoreVisualizations\Visualizations\Sparklines\Config as SparklinesConfig;

class Widget_BotsVsVisitors extends Widget {
	public static function configure(WidgetConfig $config) {
		$config
			->setName('DDBotTracker_Widget_BotsVsVisitors')
			->setCategoryId('General_Visitors')
			->setSubcategoryId('DDBotTracker_BotTracker')
			->setIsEnabled(true)
			->setOrder(3);
	}

	private function getPeriod($previous = false) {
		$period = Common::getRequestVar('period', 'day', 'string');
		$timezone = Site::getTimezoneFor(Common::getRequestVar('idSite', null, 'int'));
		$date = Common::getRequestVar('date', 'today', 'string');
		$periodObj = PeriodFactory::makePeriodFromQueryParams($timezone, $period, $date);

		if ($previous) {
			$start = $periodObj->getDateStart();
			$end = $periodObj->getDateEnd();
			if ($period === 'range') {
				$days = (int) round(($end->getTimestamp() - $start->getTimestamp()) / 86400) + 1;
				$prevEnd = $start->subDay(1);
				return PeriodFactory::makePeriodFromQueryParams($timezone, $period, $prevEnd->subDay($days - 1)->toString().','.$prevEnd->toString());
			}
			return PeriodFactory::makePeriodFromQueryParams($timezone, $period, $start->subPeriod(1, $period)->toString());
		}

		return $periodObj;
	}

	private function getPreviousPeriodParams() {
		$period = $this->getPeriod(true);
		return ['period' => $period->getLabel(), 'date' => $period->getLabel() === 'range' ? $period->getRangeString() : $period->getDateStart()->toString()];
	}

	private function getVisitorsMetrics($previous = false) {
		$segment = Request::getRawSegmentFromRequest() ?: '';
		$params = [
			'segment' => $segment,
			'columns' => 'nb_actions,nb_uniq_visitors',
			'format_metrics' => 0
		];
		if ($previous) $params += $this->getPreviousPeriodParams();
		$table = Request::processRequest('VisitsSummary.get', $params);
		$row = ($table instanceof DataTable) ? $table->getFirstRow() : false;
		return [
			'actions' => $row ? (int) $row->getColumn('nb_actions') : 0,
			'uniques' => $row ? (int) $row->getColumn('nb_uniq_visitors') : 0
		];
	}

	private function getBotsMetrics($previous = false) {
		$segment = Request::getRawSegmentFromRequest() ?: '';
		$params = [
			'segment' => $segment,
			'columns' => 'nb_actions',
			'format_metrics' => 0
		];
		if ($previous) $params += $this->getPreviousPeriodParams();
		$table = Request::processRequest('DDBotTracker.getVisitsReport', $params);
		$columnValues = ($table instanceof DataTable) ? $table->getColumn('nb_actions') : false;
		return [
			'actions' => $columnValues ? array_sum($columnValues) : 0,
			'uniques' => $columnValues ? count($columnValues) : 0
		];
	}	

	public function render(): string {
		$formatter = NumberFormatter::getInstance();

		$bots = $this->getBotsMetrics();
		$prevBots = $this->getBotsMetrics(true);
		$visitors = $this->getVisitorsMetrics();
		$prevVisitors = $this->getVisitorsMetrics(true);

		$diffBotsRaw = $prevBots['actions'] == 0 ? 0 : ($bots['actions']/$prevBots['actions'] - 1);
		$diffBots = ($diffBotsRaw > 0 ? Piwik::translate('Intl_NumberSymbolPlus') : '').$formatter->formatPercent($diffBotsRaw * 100, 1, 0);
		$diffVisitorsRaw = $prevVisitors['actions'] == 0 ? 0 : ($visitors['actions']/$prevVisitors['actions'] - 1);
		$diffVisitors = ($diffVisitorsRaw > 0 ? Piwik::translate('Intl_NumberSymbolPlus') : '').$formatter->formatPercent($diffVisitorsRaw * 100, 1, 0);

		$periodStr = $this->getPeriod()->getPrettyString();
		$prevPeriodStr = $this->getPeriod(true)->getPrettyString();

		$nbActions = Piwik::translate('General_ColumnNbActions');
		$tooltipBots = Piwik::translate('General_EvolutionSummaryGeneric', [
			$bots['actions'].' '.$nbActions,
			$periodStr,
			$prevBots['actions'].' '.$nbActions,
			$prevPeriodStr ,
			ltrim((string) $diffBots, '+'),
		]);
		$tooltipVisitors = Piwik::translate('General_EvolutionSummaryGeneric', [
			$visitors['actions'].' '.$nbActions,
			$periodStr,
			$prevVisitors['actions'].' '.$nbActions,
			$prevPeriodStr ,
			ltrim((string) $diffVisitors, '+'),
		]);

		return $this->renderTemplate('widget', [
			'bots' => [
				'actions' => $formatter->formatNumber($bots['actions'], 0),
				'uniques' =>  Piwik::translate('DDBotTracker_Widget_BotsVsVisitors_NUniqueBots', [
					$formatter->formatNumber($bots['uniques'], 0)
				])
			],
			'visitors' => [
				'actions' => $formatter->formatNumber($visitors['actions'], 0),
				'uniques' => Piwik::translate('General_NUniqueVisitors', [
					$formatter->formatNumber($visitors['uniques'], 0)
				])
			],
			'evolution' => [
				'bots' => [
					'formatted' => $diffBots,
					'raw' =>$diffBotsRaw
				],
				'visitors' => [
					'formatted' => $diffVisitors,
					'raw' =>$diffVisitorsRaw
				]
			],
			'tooltip' => [
				'bots' => $tooltipBots,
				'visitors' => $tooltipVisitors
			]
		]);
	}
}

?>
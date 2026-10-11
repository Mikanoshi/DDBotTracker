<?php

namespace Piwik\Plugins\DDBotTracker;

use Piwik\Piwik;
use Piwik\Site;
use Piwik\Date;
use Piwik\Period;
use Piwik\Db;
use Piwik\Common;
use Piwik\Segment;
use Piwik\DataTable;
use Piwik\DataTable\Map;
use Piwik\DataTable\Row;
use Piwik\Period\Factory;
use Piwik\Plugins\SegmentEditor\SegmentEditor;

use Piwik\Container\StaticContainer;

class DataHelpers {
	public static array $AGGREGATION_RULES;

	public function __construct() {
		if (empty(self::$AGGREGATION_RULES))
		self::$AGGREGATION_RULES = [
			'label' => 'skip',
			'nb_visits' => 'sum',
			'nb_actions' => 'sum',
			'bot_name' => self::stringFunc(...),
			'bot_category' => self::stringFunc(...),
			'bot_url' => self::stringFunc(...),
			'bot_producer_name' => self::stringFunc(...),
			'bot_producer_url' => self::stringFunc(...),
			'page' => self::stringFunc(...),
			'useragent' => self::stringFunc(...),
			'most_frequent_bot' => self::stringFunc(...),
			'visit_ts' => 'max',
			// dynamic
			'nb_visits_pct' => self::stringFunc(...),
			'nb_actions_pct' => self::stringFunc(...),
			'growth_percent' => self::stringFunc(...)
		];
	}

	public static function stringFunc($thisData, $sumData, $thisRow, $rowToSum, $column = null) {
		if ($thisRow->getColumn('label') == DataTable::LABEL_SUMMARY_ROW ||
			$thisRow->getColumn('label') == DataTable::LABEL_TOTALS_ROW) return '';
		$empty = [null, false, ''];
		if (in_array($thisData, $empty, true))
			return $sumData;
		elseif (in_array($sumData, $empty, true))
			return $thisData;
		else
			return $sumData;
	}

	private function processPeriod($idSite, Period|string &$period, ?string $date = null) {
		$isMultiPeriod = false;
		if (is_string($period) && $date !== null) {
			$isMultiPeriod = Period::isMultiplePeriod($date, $period);
			$period = Factory::build($period, $date, Site::getTimezoneFor($idSite));
		} elseif (!($period instanceof Period)) {
			throw new \InvalidArgumentException('Invalid period parameter type');
		}
		return $isMultiPeriod;
	}

	public function getVisitsTable($idSite, Period|string $period, ?string $date = null, $segment = false) {
		$isMultiPeriod = $this->processPeriod($idSite, $period, $date);
		// Evolution
		if ($isMultiPeriod) {
			$map = new Map();
			foreach ($period->getSubperiods() as $subPeriod) {
				$label = $subPeriod->getLabel();
				$mapLabel = ($label === 'week' || $label === 'range') ? $subPeriod->getRangeString() : $subPeriod->getPrettyString();
				$subTable = $this->getVisitsTable($idSite, $subPeriod, null, $segment);
				$subTable->setMetadata('site', new Site($idSite));
				$subTable->setMetadata('period', $subPeriod);
				$map->addTable($subTable, $mapLabel);
			}
			return $map;
		}

		//\Piwik\Log::error('getVisitsTable: '.$idSite.', '.var_export($date, true).', '.$period->getRangeString());
		$offsetSeconds = Date::getUtcOffset(Site::getTimezoneFor($idSite));
		$rows = Db::fetchAll(
			"WITH RankedVisits AS (
				SELECT *, UNIX_TIMESTAMP(`visit_ts`) as visit_ts_int,
				ROW_NUMBER() OVER (PARTITION BY `bot_name` ORDER BY `visit_ts` DESC) as row_num,
				COUNT(*) OVER (PARTITION BY `bot_name`) as total
				FROM ".Common::prefixTable('ddbot_visits')."
				WHERE `site_id` = ?  AND `visit_ts` BETWEEN FROM_UNIXTIME(?) AND FROM_UNIXTIME(?)
			) SELECT * FROM RankedVisits WHERE row_num = 1",
			[
				$idSite,
				$period->getDateTimeStart()->subSeconds($offsetSeconds)->getTimestampUTC(),
				$period->getDateTimeEnd()->subSeconds($offsetSeconds)->getTimestampUTC()
			]
		);

		$table = new DataTable();
		foreach ($rows as $row) {
			$columns = [
				'label' => $row['bot_name'],
				'nb_actions' => $row['total'],
				'bot_name' => $row['bot_name'],
				'bot_category' => ucwords($row['bot_category']),
				'bot_url' => $row['bot_url'],
				'bot_producer_name' => $row['bot_producer_name'],
				'bot_producer_url' => $row['bot_producer_url'],
				'useragent' => $row['useragent'],
				'visit_ts' => $row['visit_ts_int']
			];
			$metadata = [];
			if ($row['bot_url']) $metadata['url'] = $row['bot_url'];
			if ($row['useragent']) {
				$metadata['tooltip'] = $row['useragent'];
				if ($row['bot_name'] === 'Generic Bot') $metadata['tooltip'] .= ' ['.Piwik::translate('DDBotTracker_AndOthers').']';
			}
			$table->addRow(new Row([
				Row::COLUMNS => $columns,
				Row::METADATA => $metadata
			]));
		}
		//$segmentObj = new Segment($segment ? $segment : '', $idSite);
		$segmentObj = new Segment('', $idSite);
		$table->setMetadata('segment', $segmentObj->getString());
		$table->setMetadata('segmentPretty', $segmentObj->getStoredSegmentName($idSite));
		return $table;
	}

	public function getPagesTable($idSite, Period|string $period, ?string $date = null, $segment = false, bool $expanded = false, $idSubtable = false, $depth = false, bool $flat = false) {
		$isMultiPeriod = $this->processPeriod($idSite, $period, $date);
		// Evolution
		if ($isMultiPeriod) {
			$map = new Map();
			foreach ($period->getSubperiods() as $subPeriod) {
				$label = $subPeriod->getLabel();
				$mapLabel = ($label === 'week' || $label === 'range') ? $subPeriod->getRangeString() : $subPeriod->getPrettyString();
				$subTable = $this->getPagesTable($idSite, $subPeriod, null, $segment, $expanded, $idSubtable, $depth, $flat);
				$subTable->setMetadata('site', new Site($idSite));
				$subTable->setMetadata('period', $subPeriod);
				$map->addTable($subTable, $mapLabel);
			}
			return $map;
		}

		$offsetSeconds = Date::getUtcOffset(Site::getTimezoneFor($idSite));
		$startDate = $period->getDateTimeStart()->subSeconds($offsetSeconds)->getTimestampUTC();
		$endDate = $period->getDateTimeEnd()->subSeconds($offsetSeconds)->getTimestampUTC();
		$subdom = $idSubtable !== false ? hex2bin($idSubtable) : '';
		$subdomquery = $subdom !== '' ? ' AND `page` LIKE ?' : '';
		$dbparams = [];
		$dbparams[] = $idSite;
		$dbparams[] = $startDate;
		$dbparams[] = $endDate;
		if ($subdom !== '') $dbparams[] = $subdom.'%';
		$dbparams[] = $idSite;
		$dbparams[] = $startDate;
		$dbparams[] = $endDate;
		if ($subdom !== '') $dbparams[] = $subdom.'%';

		$rows = Db::fetchAll(
			"WITH RankedPages AS (SELECT `page`, ROW_NUMBER() OVER (PARTITION BY `page` ORDER BY `visit_ts` DESC) as row_num, COUNT(*) OVER (PARTITION BY `page`) as nb_actions FROM ".
			Common::prefixTable('ddbot_visits').
			" WHERE `site_id` = ? AND `visit_ts` between FROM_UNIXTIME(?) AND FROM_UNIXTIME(?)".$subdomquery."),".
			"BotCounts AS (SELECT `page`, `bot_name`, ROW_NUMBER() OVER (PARTITION BY `page` ORDER BY COUNT(*) DESC) as bot_rank FROM ".
			Common::prefixTable('ddbot_visits').
			" WHERE `site_id` = ? AND `visit_ts` between FROM_UNIXTIME(?) AND FROM_UNIXTIME(?)".$subdomquery." GROUP BY `page`, `bot_name`)".
			" SELECT RankedPages.`page`, RankedPages.`nb_actions`, BotCounts.`bot_name` as most_frequent_bot FROM RankedPages".
			" JOIN BotCounts ON RankedPages.`page` = BotCounts.`page` AND BotCounts.`bot_rank` = 1 WHERE RankedPages.row_num = 1",
			$dbparams
		);
		//echo '<pre>'.print_r($rows, true).'</pre>';
		$domainRows = [];
		foreach ($rows as $row) {
			$parts = parse_url($row['page']);
			if (!isset($parts['scheme'], $parts['host'])) continue;
			$path = $parts['path'] ?? '';
			$query = isset($parts['query']) ? '?'.$parts['query'] : '';
			$fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';
			$row['label'] = $path.$query.$fragment;
			$domain = $parts['scheme'].'://'.$parts['host'];
			if (!isset($domainRows[$domain])) $domainRows[$domain] = [];
			$domainRows[$domain][] = $row;
		}

		$buildSubtable = function($subDomain) use ($domainRows, $flat, $expanded) {
			$subTable = new DataTable();
			foreach ($domainRows[$subDomain] as $domainRow)
			$subTable->addRow(new Row([
				Row::COLUMNS => $domainRow,
				Row::METADATA => [
					'url' => $domainRow['page'] ?? '',
					'tooltip' => ($flat || $expanded) ? '' : ($domainRow['page'] ?? '')
				]
			]));
			return $subTable;
		};

		if ($subdom !== '') return $buildSubtable($subdom);

		$table = new DataTable();
		foreach ($domainRows as $domain => $rows) {
			$counts = array_count_values(array_column($rows, 'most_frequent_bot'));
			$sum = array_sum(array_column($rows, 'nb_actions'));
			arsort($counts);
			$parentRow = new Row([
				Row::COLUMNS => [
					'label' => $domain,
					'page' => $domain,
					'most_frequent_bot' => array_key_first($counts) ?? '',
					'nb_actions' => $sum
				]
			]);
			if ($flat || $expanded)
				$parentRow->setSubtable($buildSubtable($domain));
			else
				$parentRow->setNonLoadedSubtableId(bin2hex($domain));
			$table->addRow($parentRow);
		}

		//$segmentObj = new Segment($segment ? $segment : '', $idSite);
		$segmentObj = new Segment('', $idSite);
		$table->setMetadata('segment', $segmentObj->getString());
		$table->setMetadata('segmentPretty', $segmentObj->getStoredSegmentName($idSite));
		return $table;
	}
}

?>
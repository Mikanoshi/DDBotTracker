<?php

namespace Piwik\Plugins\DDBotTracker;

use Piwik\Db;
use Piwik\Site;
use Piwik\Piwik;
use Piwik\Request;
use Piwik\Archive;
use Piwik\DataTable;
use Piwik\Container\StaticContainer;
use Piwik\DataTable\Map;
use Piwik\DataTable\Row;
use Piwik\Plugins\DDBotTracker\Archiver;
use Piwik\Plugins\DDBotTracker\DataHelpers;
use Piwik\Plugins\DDBotTracker\RecordBuilders\VisitsRecordBuilder;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;

class API extends \Piwik\Plugin\API {
	private $dataHelpers;

	public function __construct() {
		$this->dataHelpers = StaticContainer::get(DataHelpers::class);
	}

	private function applyAggrOps(&$dataTable) {
		if ($dataTable instanceof Map) {
			foreach ($dataTable->getDataTables() as $subTable)
			$subTable->setMetadata(DataTable::COLUMN_AGGREGATION_OPS_METADATA_NAME, DataHelpers::$AGGREGATION_RULES);
		} else {
			$dataTable->setMetadata(DataTable::COLUMN_AGGREGATION_OPS_METADATA_NAME, DataHelpers::$AGGREGATION_RULES);
		}
	}

	public function getVisitsReport($idSite, $period, $date, $segment = false) {
		//Piwik::checkUserHasViewAccess($idSite);
		//$dataTable = $this->dataHelpers->getVisitsTable($idSite, $period, $date, $segment);
		$dataTable = Archive::createDataTableFromArchive(Archiver::VISITS_RECORD_NAME, $idSite, $period, $date, $segment);
		$this->applyAggrOps($dataTable);
		return $dataTable;
	}

	public function getPagesReport($idSite, string $period, string $date, $segment = false, bool $expanded = false, $idSubtable = false, $depth = false, bool $flat = false) {
		//Piwik::checkUserHasViewAccess($idSite);
		//$dataTable = $this->dataHelpers->getPagesTable($idSite, $period, $date, $segment, $expanded, $idSubtable, $depth, $flat);
		$dataTable = Archive::createDataTableFromArchive(Archiver::PAGES_RECORD_NAME, $idSite, $period, $date, $segment, $expanded, $flat, $idSubtable, $depth);
		$this->applyAggrOps($dataTable);
		return $dataTable;
	}
}

?>
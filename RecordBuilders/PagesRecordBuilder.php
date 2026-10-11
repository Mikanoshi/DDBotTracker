<?php

namespace Piwik\Plugins\DDBotTracker\RecordBuilders;

use Piwik\Config;
use Piwik\DataTable;
use Piwik\Metrics;
use Piwik\ArchiveProcessor;
use Piwik\ArchiveProcessor\Record;
use Piwik\ArchiveProcessor\RecordBuilder;
use Piwik\Container\StaticContainer;
use Piwik\DataAccess\LogAggregator;
use Piwik\Plugins\DDBotTracker\Archiver;
use Piwik\Plugins\DDBotTracker\DataHelpers;
use Piwik\Plugins\DDBotTracker\Reports\PagesReport;

class PagesRecordBuilder extends RecordBuilder {
	
	private $dataHelpers;

	public function __construct() {
		parent::__construct();

		$this->dataHelpers = StaticContainer::get(DataHelpers::class);
		$this->maxRowsInTable = Config::getInstance()->General['datatable_archiving_maximum_rows_standard'];
		$this->columnToSortByBeforeTruncation = 'nb_actions';
		$this->columnAggregationOps = DataHelpers::$AGGREGATION_RULES;
	}

	public function getRecordMetadata(ArchiveProcessor $archiveProcessor): array {
		return [ Record::make(Record::TYPE_BLOB, Archiver::PAGES_RECORD_NAME) ];
	}

	protected function aggregate(ArchiveProcessor $archiveProcessor): array {
		$params = $archiveProcessor->getParams();
		$table = $this->dataHelpers->getPagesTable(
			$params->getSite()->getId(),
			$params->getPeriod(),
			null, false, true // expanded
		);
		return [ Archiver::PAGES_RECORD_NAME =>  $table ];
	}
}

?>
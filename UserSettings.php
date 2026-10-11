<?php

namespace Piwik\Plugins\DDBotTracker;

use Piwik\Piwik;
use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;

class UserSettings extends \Piwik\Settings\Plugin\UserSettings {
	public $reverseTextSort;
	protected function init() {
		$this->title = Piwik::translate('DDBotTracker_DDBotTracker');
		$this->reverseTextSort = $this->makeSetting('reverseTextSort', true, FieldConfig::TYPE_BOOL,
			function (FieldConfig $field) {
				$field->title = Piwik::translate('DDBotTracker_Setting_Reverse_Title');
				$field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
				$field->description = Piwik::translate('DDBotTracker_Setting_Reverse_Desc');
			}
		);
	}
}

?>
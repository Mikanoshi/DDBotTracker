(function ($, require) {

	var exports = require('piwik/UI'),
		OriginalDataTable = exports.DataTable,
		OriginalPie = exports.JqplotPieGraphDataTable,
		OriginalJqplotBarGraphDataTable = exports.JqplotBarGraphDataTable;

	exports.VisitsDataTable = function (element) {
		OriginalDataTable.call(this, element);
	};
	exports.JqplotPieGraphDataTable = function (element) {
		OriginalPie.call(this, element);
	};
	exports.JqplotBarGraphDataTable = function (element) {
		OriginalJqplotBarGraphDataTable.call(this, element);
	};

	function replaceReportTitleAndHelp(domElem, newTitle, newDesc = '') {
		if (!domElem || !domElem.length) return;

		var $title = '';
		var $headline = domElem.prev('h2');
		if ($headline.length) {
			$title = $headline.find('.title:not(.ng-hide)');
		} else {
			var $widget = domElem.parents('.widget');
			var $widgetPreview = domElem.parents('.widgetpreview-preview');
			if ($widget.length && !$widgetPreview.length) $title = $widget.find('.widgetName > span');
		}

		if ($title.length) {
			$title.text(newTitle);
			if (newDesc !== '') {
				$inlineHelp = $title.parent().find('.inlineHelp > div > p');
				if ($inlineHelp.length) $inlineHelp.text(newDesc);
			}
		}
	};

	function isDDBotTracker(dt) {
		return dt.$element.hasClass('ddbottracker-visits');
	}

	var initFunc = function(expanded_name = '') {
		var dt = this;

		try {
			var dataParams = JSON.parse(dt.$element.attr('data-params'));
			if ('custom_report_name' in dataParams) 
			replaceReportTitleAndHelp(dt.$element, dataParams['custom_report_name'], dataParams['custom_report_doc'] ?? '');
		} catch (e) {}
		
		if (expanded_name !== '')
		dt.$element.on('click', '.ddBotTrackerExpand, .ddBotTrackerShrink', function (e) {
			e.preventDefault();
			e.stopPropagation();
			newExpanded = this.className.includes('ddBotTrackerExpand') ? '1' : '0';
			dt.reloadAjaxDataTable(true, undefined, { [expanded_name]: newExpanded });
			dt.notifyWidgetParametersChange(dt.$element, { [expanded_name]: newExpanded });
			// window.CoreHome.MatomoUrl.updateLocation({ ...MatomoUrl.hashParsed.value, ...{ pie_expanded: newPieExpanded }});
		});
	};

	$.extend(exports.VisitsDataTable.prototype, OriginalDataTable.prototype, {
		init: function() {
			OriginalDataTable.prototype.init.call(this);
			initFunc.call(this);
		}
	});
	$.extend(exports.JqplotPieGraphDataTable.prototype, OriginalPie.prototype, {
		init: function() {
			OriginalPie.prototype.init.call(this);
			if (isDDBotTracker(this))
			initFunc.call(this, 'pie_expanded');
		}
	});
	$.extend(exports.JqplotBarGraphDataTable.prototype, OriginalJqplotBarGraphDataTable.prototype, {
		init: function() {
			OriginalJqplotBarGraphDataTable.prototype.init.call(this);
			if (isDDBotTracker(this))
			initFunc.call(this, 'bar_expanded');
		},
	    _setJqplotParameters: function (params) {
			OriginalJqplotBarGraphDataTable.prototype._setJqplotParameters.call(this, params);
			if (isDDBotTracker(this))
			if (this.jqplotParams.axes.xaxis.ticks) {
				expanded = $('.ddBotTrackerShrink').length > 0;
				console.log(expanded);
				this.jqplotParams.axes.xaxis.ticks = this.jqplotParams.axes.xaxis.ticks.map(function(val) {
					if (!val) return '';
					var str = String(val);
					var maxChars = expanded ? 13 : 20;
					return str.length > maxChars ? str.substr(0, maxChars) + '…' : str;
				});
			}
		}
	});

})(jQuery, require);
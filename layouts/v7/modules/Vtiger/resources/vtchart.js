/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is: vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

/*
 * Chart.js-backed replacement for the old jqplot-based vtchart/VtJqplotInterface.
 * Public API is unchanged: element.vtchart(data, options) with options.renderer
 * one of 'pie', 'bar', 'multibar', 'horizontalbar', 'linechart', 'funnel' - every
 * caller (dashboard widgets, report charts) keeps working without modification.
 */
(function ($) {

	function getToken(name, fallback) {
		var value = getComputedStyle(document.documentElement).getPropertyValue(name);
		return value ? value.trim() : fallback;
	}

	function getPalette() {
		return [
			getToken('--teal', '#0E7C66'),
			getToken('--teal-deep', '#0A5C4B'),
			'#4FA98A',
			getToken('--danger', '#E4572E'),
			getToken('--muted', '#6B7280'),
			'#8AC6B0',
			'#C77DFF',
			'#F4A300'
		];
	}

	var vtChart = function () {

		this.element = false;
		this.chart = false;

		this.triggerClick = function (data) {
			this.element.trigger('vtchartClick', data);
		};

		this.buildCanvas = function () {
			this.element.empty();
			var canvas = document.createElement('canvas');
			this.element.append(canvas);
			return canvas;
		};

		this.renderPie = function () {
			var thisInstance = this;
			var palette = getPalette();
			var rows = this.data['chartData'];
			var canvas = this.buildCanvas();
			this.chart = new Chart(canvas, {
				type: 'pie',
				data: {
					labels: rows.map(function (row) { return row[0]; }),
					datasets: [{
						data: rows.map(function (row) { return row[1]; }),
						backgroundColor: rows.map(function (row, i) { return palette[i % palette.length]; })
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: { legend: { display: true, position: 'right' } },
					onHover: function (evt, elements) {
						evt.native.target.style.cursor = elements.length ? 'pointer' : 'default';
					},
					onClick: function (evt, elements) {
						if (!elements.length) return;
						thisInstance.handleClick({ index: elements[0].index });
					}
				}
			});
		};

		this.renderBar = function () {
			var thisInstance = this;
			var canvas = this.buildCanvas();
			this.chart = new Chart(canvas, {
				type: 'bar',
				data: {
					labels: this.data['labels'],
					datasets: [{
						data: this.data['chartData'][0],
						backgroundColor: getToken('--teal', '#0E7C66')
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: { legend: { display: !!this.data['data_labels'] } },
					scales: {
						y: { beginAtZero: true, max: this.data['yMaxValue'] }
					},
					onHover: function (evt, elements) {
						evt.native.target.style.cursor = elements.length ? 'pointer' : 'default';
					},
					onClick: function (evt, elements) {
						if (!elements.length) return;
						thisInstance.handleClick({ index: elements[0].index });
					}
				}
			});
		};

		this.renderMultibar = function () {
			var thisInstance = this;
			var palette = getPalette();
			var series = this.data['data'];
			var canvas = this.buildCanvas();
			this.chart = new Chart(canvas, {
				type: 'bar',
				data: {
					labels: this.data['ticks'],
					datasets: series.map(function (values, i) {
						return {
							label: (thisInstance.data['labels'] || [])[i] || '',
							data: values,
							backgroundColor: palette[i % palette.length]
						};
					})
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: { legend: { display: true, position: 'right' } },
					scales: {
						x: { stacked: true },
						y: { stacked: true, beginAtZero: true }
					},
					onHover: function (evt, elements) {
						evt.native.target.style.cursor = elements.length ? 'pointer' : 'default';
					},
					onClick: function (evt, elements) {
						if (!elements.length) return;
						thisInstance.handleClick({ gridpos: elements[0].datasetIndex, index: elements[0].index });
					}
				}
			});
		};

		this.renderHorizontalbar = function () {
			var thisInstance = this;
			var palette = getPalette();
			var series = this.data['chartData'];
			var dataLabels = this.data['data_labels'];
			var canvas = this.buildCanvas();
			this.chart = new Chart(canvas, {
				type: 'bar',
				data: {
					labels: this.data['labels'],
					datasets: series.map(function (pairs, i) {
						return {
							label: dataLabels ? dataLabels[i] : '',
							data: pairs.map(function (pair) { return pair[0]; }),
							backgroundColor: palette[i % palette.length]
						};
					})
				},
				options: {
					indexAxis: 'y',
					responsive: true,
					maintainAspectRatio: false,
					plugins: { legend: { display: !!dataLabels, position: 'right' } },
					scales: {
						x: { beginAtZero: true, max: this.data['yMaxValue'] }
					},
					onHover: function (evt, elements) {
						evt.native.target.style.cursor = elements.length ? 'pointer' : 'default';
					},
					onClick: function (evt, elements) {
						if (!elements.length) return;
						thisInstance.handleClick({ index: elements[0].index });
					}
				}
			});
		};

		this.renderLine = function () {
			var thisInstance = this;
			var palette = getPalette();
			var series = this.data['chartData'];
			var dataLabels = this.data['data_labels'];
			var canvas = this.buildCanvas();
			this.chart = new Chart(canvas, {
				type: 'line',
				data: {
					labels: this.data['labels'],
					datasets: series.map(function (values, i) {
						return {
							label: dataLabels ? dataLabels[i] : '',
							data: values,
							borderColor: palette[i % palette.length],
							backgroundColor: palette[i % palette.length],
							fill: false,
							tension: 0.3
						};
					})
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: { legend: { display: !!dataLabels, position: 'ne' } },
					onHover: function (evt, elements) {
						evt.native.target.style.cursor = elements.length ? 'pointer' : 'default';
					},
					onClick: function (evt, elements) {
						if (!elements.length) return;
						thisInstance.handleClick({ index: elements[0].index });
					}
				}
			});
		};

		/* Chart.js has no native funnel chart type - approximated with a
		 * horizontal bar in the same top-to-bottom stage order as the source
		 * data, which reads the same way a funnel does (widest stage first). */
		this.renderFunnel = function () {
			var thisInstance = this;
			var palette = getPalette();
			var rows = JSON.parse(this.data);
			var labels = rows.map(function (row) { return row[2]; });
			var values = rows.map(function (row) { return parseFloat(row[1]); });
			var canvas = this.buildCanvas();
			this.chart = new Chart(canvas, {
				type: 'bar',
				data: {
					labels: labels,
					datasets: [{
						data: values,
						backgroundColor: labels.map(function (l, i) { return palette[i % palette.length]; })
					}]
				},
				options: {
					indexAxis: 'y',
					responsive: true,
					maintainAspectRatio: false,
					plugins: { legend: { display: false } },
					scales: { x: { beginAtZero: true } },
					onHover: function (evt, elements) {
						evt.native.target.style.cursor = elements.length ? 'pointer' : 'default';
					},
					onClick: function (evt, elements) {
						if (!elements.length) return;
						thisInstance.handleClick({ index: elements[0].index });
					}
				}
			});
		};

		this.handleClick = function (pos) {
			var url;
			switch (this.options.renderer) {
				case 'funnel':
					url = this.options.links[pos.index] && this.options.links[pos.index]['links'];
					break;
				case 'multibar':
					if (this.options.links)
						url = this.options.links[pos.gridpos] ? this.options.links[pos.gridpos][pos.index] : undefined;
					break;
				default:
					if (typeof this.options.links != 'undefined')
						url = this.options.links[pos.index];
					break;
			}
			this.triggerClick({ 'url': url });
		};

		this.init = function (element, data, options) {
			this.element = element;
			this.data = data;
			this.options = options;

			switch (this.options.renderer) {
				case 'pie': this.renderPie(); break;
				case 'bar': this.renderBar(); break;
				case 'funnel': this.renderFunnel(); break;
				case 'multibar': this.renderMultibar(); break;
				case 'horizontalbar': this.renderHorizontalbar(); break;
				case 'linechart': this.renderLine(); break;
				default: console.log('vtchart renderer not supported: ' + this.options.renderer);
			}
		};
	};

	$.fn.vtchart = function (options) {
		var data = [];
		for (var i = 0, l = arguments.length; i < l; i++) {
			data.push(arguments[i]);
		}
		if (typeof Chart === 'undefined') {
			console.log('Chart.js not found!');
			return this;
		}
		return this.each(function (index, element) {
			var jQElement = jQuery(element).empty(); /* Clear any existing content to avoid overlapping redraw */
			var instance = new vtChart();
			instance.init(jQElement, data[0], data[1]);
		});
	};

})(jQuery);

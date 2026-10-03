/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is: vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

Vtiger.Class("Vtiger_DashBoard_Js",{

	//GridStack instance of the active tab
	grid : false,

	//Layout is edited and saved only at the full column count (4). Below that
	//the grid is a read-only reflow of the saved layout, so a phone or laptop
	//view can never overwrite the layout arranged on a wide screen.
	fullGridColumns : 4,

	//static property which will store the instance of dashboard
	currentInstance : false,
	dashboardTabsLimit : 10,

	/**
	 * Mirror a widget's grid position/size into the data-row/col/sizex/sizey
	 * attributes (1-based, as stored on the server). savePositions() and
	 * saveWidgetSize() read those attributes, so keeping them in sync is all
	 * the persistence code needs.
	 */
	syncWidgetAttrs : function(element) {
		var node = element.gridstackNode;
		if (!node) return;
		jQuery(element).attr({
			'data-col' : node.x + 1,
			'data-row' : node.y + 1,
			'data-sizex' : node.w,
			'data-sizey' : node.h
		});
	},

	/**
	 * Put a freshly created widget <li> into the active grid, in the first free
	 * spot, and save the layout.
	 */
	addToGrid : function(widgetContainer, width, height) {
		var grid = Vtiger_DashBoard_Js.grid;
		widgetContainer.addClass('grid-stack-item').append('<div class="grid-stack-item-content"></div>');
		grid.el.appendChild(widgetContainer[0]);
		grid.makeWidget(widgetContainer[0], {
			w : Math.min(parseInt(width) || 1, grid.getColumn()),
			h : parseInt(height) || 1,
			autoPosition : true
		});
	},

	addWidget : function(element, url) {
		var element = jQuery(element);
		var linkId = element.data('linkid');
		var name = element.data('name');

		// After adding widget, we should remove that widget from Add Widget drop down menu from active tab
		var activeTabId = Vtiger_DashBoard_Js.currentInstance.getActiveTabId();
		jQuery('a[data-name="'+name+'"]',"#tab_"+activeTabId).parent().hide();
		var widgetContainer = jQuery('<li class="new dashboardWidget loadcompleted" id="'+ linkId +'" data-name="'+name+'" data-mode="open"></li>');
		widgetContainer.data('url', url);
		var width = element.data('width');
		var height = element.data('height');
		Vtiger_DashBoard_Js.addToGrid(widgetContainer, width, height);
		Vtiger_DashBoard_Js.currentInstance.loadWidget(widgetContainer);
	},

	addMiniListWidget: function(element, url) {
		// 1. Show popup window for selection (module, filter, fields)
		// 2. Compute the dynamic mini-list widget url
		// 3. Add widget with URL to the page.

		element = jQuery(element);

		app.request.post({"url":"index.php?module=Home&view=MiniListWizard&step=step1"}).then(function(err,res){
			var callback = function(data){
				var wizardContainer = jQuery(data);
				var form = jQuery('form', wizardContainer);

				var moduleNameSelectDOM = jQuery('select[name="module"]', wizardContainer);
				var filteridSelectDOM = jQuery('select[name="filterid"]', wizardContainer);
				var fieldsSelectDOM = jQuery('select[name="fields"]', wizardContainer);

				var moduleNameSelect2 = vtUtils.showSelect2ElementView(moduleNameSelectDOM, {
					placeholder: app.vtranslate('JS_SELECT_MODULE')
				});
				var filteridSelect2 = vtUtils.showSelect2ElementView(filteridSelectDOM,{
					placeholder: app.vtranslate('JS_PLEASE_SELECT_ATLEAST_ONE_OPTION')
				});
				var fieldsSelect2 = vtUtils.showSelect2ElementView(fieldsSelectDOM, {
					placeholder: app.vtranslate('JS_PLEASE_SELECT_ATLEAST_ONE_OPTION'),
					closeOnSelect: true,
					maximumSelectionSize: 2
				});
				var footer = jQuery('.modal-footer', wizardContainer);

				filteridSelectDOM.closest('tr').hide();
				fieldsSelectDOM.closest('tr').hide();
				footer.hide();

				moduleNameSelect2.change(function(){
					if (!moduleNameSelect2.val()) return;

					var moduleNameSelect2Params = {
						module: 'Home',
						view: 'MiniListWizard',
						step: 'step2',
						selectedModule: moduleNameSelect2.val()
					};

					app.request.post({"data":moduleNameSelect2Params}).then(function(err,res) {
						filteridSelectDOM.empty().html(res).trigger('change');
						filteridSelect2.closest('tr').show();
						fieldsSelect2.closest('tr').hide();
						footer.hide();
					})
				});
				filteridSelect2.change(function(){
					if (!filteridSelect2.val()) return;

					var selectedModule = moduleNameSelect2.val();
					var filteridSelect2Params = {
						module: 'Home',
						view: 'MiniListWizard',
						step: 'step3',
						selectedModule: selectedModule,
						filterid: filteridSelect2.val()
					};

					app.request.post({"data":filteridSelect2Params}).then(function(err,res){
						fieldsSelectDOM.empty().html(res).trigger('change');
						var translatedModuleNames = JSON.parse(jQuery("#minilistWizardContainer").find("#translatedModuleNames").val());
						var fieldsLabelText = app.vtranslate('JS_EDIT_FIELDS', translatedModuleNames[selectedModule], translatedModuleNames[selectedModule]);
						fieldsSelect2.closest('tr').find('.fieldLabel label').text(fieldsLabelText);
						fieldsSelect2.closest('tr').show();
					});
				});
				fieldsSelect2.change(function() {
					if (!fieldsSelect2.val()) {
						footer.hide();
					} else {
						footer.show();
					}
				});

				form.submit(function(e){
					e.preventDefault();
					//To disable savebutton after one submit to prevent multiple submits
					jQuery("[name='saveButton']").attr('disabled','disabled');
					var selectedModule = moduleNameSelect2.val();
					var selectedFilterId= filteridSelect2.val();
					var selectedFields = fieldsSelect2.val();
					if (typeof selectedFields != 'object') selectedFields = [selectedFields];

					// TODO mandatory field validation

					finializeAdd(selectedModule, selectedFilterId, selectedFields);
				});
			}
			app.helper.showModal(res,{"cb":callback});
		});

		function finializeAdd(moduleName, filterid, fields) {
			var data = {
				module: moduleName
			}
			if (typeof fields != 'object') fields = [fields];
			data['fields'] = fields;

			url += '&filterid='+filterid+'&data=' + JSON.stringify(data);
			var linkId = element.data('linkid');
			var name = element.data('name');
			var widgetContainer = jQuery('<li class="new dashboardWidget loadcompleted" id="'+ linkId +"-" + filterid +'" data-name="'+name+'" data-mode="open"></li>');
			widgetContainer.data('url', url);
			var width = element.data('width');
			var height = element.data('height');
			Vtiger_DashBoard_Js.addToGrid(widgetContainer, width, height);
			Vtiger_DashBoard_Js.currentInstance.loadWidget(widgetContainer);
			app.helper.hideModal();
		}
	},

	addNoteBookWidget : function(element, url) {
		// 1. Show popup window for selection (module, filter, fields)
		// 2. Compute the dynamic mini-list widget url
		// 3. Add widget with URL to the page.

		element = jQuery(element);


		app.request.get({"url":"index.php?module=Home&view=AddNotePad"}).then(function(err,res){
			var callback = function(data){
				var wizardContainer = jQuery(data);
				var form = jQuery('form', wizardContainer);
				var params = {
					submitHandler : function(form){
						//To prevent multiple click on save
						var form = jQuery(form);
						jQuery("[name='saveButton']").attr('disabled','disabled');
						var notePadName = form.find('[name="notePadName"]').val();
						var notePadContent = form.find('[name="notePadContent"]').val();
						var linkId = element.data('linkid');
						var noteBookParams = {
							'module' : app.getModuleName(),
							'action' : 'NoteBook',
							'mode' : 'NoteBookCreate',
							'notePadName' : notePadName,
							'notePadContent' : notePadContent,
							'linkId' : linkId,
							'tab' : jQuery(".tab-pane.active").data("tabid")
						}
						app.request.post({"data":noteBookParams}).then(function(err,data) {
							if(data){
								var widgetId = data.widgetId;
								app.helper.hideModal();

								url += '&widgetid='+widgetId;

								var name = element.data('name');
								var widgetContainer = jQuery('<li class="new dashboardWidget loadcompleted" id="'+ linkId +"-" + widgetId +'" data-name="'+name+'" data-mode="open"></li>');
								widgetContainer.data('url', url);
								var width = element.data('width');
								var height = element.data('height');
								Vtiger_DashBoard_Js.addToGrid(widgetContainer, width, height);
								Vtiger_DashBoard_Js.currentInstance.loadWidget(widgetContainer);
							}
						});
						return false;
					}
				}
				form.vtValidate(params);
			}
			app.helper.showModal(res,{"cb":callback});
		});

	}

},{


	container : false,
	instancesCache : {},

	init : function() {
		Vtiger_DashBoard_Js.currentInstance = this;
		this.addComponents();
	},

	addComponents : function (){
		this.addComponent('Vtiger_Index_Js');
	},

	getDashboardContainer : function(){
		return jQuery(".dashBoardContainer");
	},

	getContainer : function(tabid) {
		if(typeof tabid == 'undefined'){
			tabid = this.getActiveTabId();
		}
		return jQuery(".dashboardGrid_"+tabid).find('ul.grid-stack');
	},

	getWidgetInstance : function(widgetContainer) {
			var id = widgetContainer.attr('id');
			if(!(id in this.instancesCache)) {
					var widgetName = widgetContainer.data('name');
					if(widgetName === "ChartReportWidget"){
						widgetName+= "_"+id;
					}
					this.instancesCache[id] = Vtiger_Widget_Js.getInstance(widgetContainer, widgetName);
			}
	else{
		this.instancesCache[id].init(widgetContainer);
	}
			return this.instancesCache[id];
	},

	getActiveTabId : function(){ 
		return jQuery(".tab-pane.active").data("tabid");
	},

	getActiveTabName : function(){ 
		return jQuery(".tab-pane.active").data("tabname");
	},

	saveWidgetSize: function (widget) {
		var dashboardTabId = widget.closest('.tab-pane.active').data('tabid');
		var widgetSize = {
			'sizex': widget.attr('data-sizex'),
			'sizey': widget.attr('data-sizey')
		};
		if (widgetSize.sizex && widgetSize.sizey) {
			var params = {
				'module': 'Vtiger',
				'action': 'SaveWidgetSize',
				'id': widget.attr('id'),
				'size': widgetSize,
				'tabid': dashboardTabId
			};
			app.request.post({"data": params}).then(function (err, data) {
			});
		}
	},

	getWaitingForResizeCompleteMsg: function () {
		return '<div class="wait_resizing_msg"><p class="text-info">'+app.vtranslate('JS_WIDGET_RESIZING_WAIT_MSG')+'</p></div>';
	},

	registerGrid : function() {
		var thisInstance = this;
		var container = this.getContainer();
		if (!container.length) return;

		var grid = GridStack.init({
			column : 4,
			columnOpts : {
				// same window-width thresholds the old getgridColumns() used:
				// <768px 1 col, <992px 2 cols, <1440px 3 cols, else 4
				breakpoints : [{w : 767, c : 1}, {w : 991, c : 2}, {w : 1439, c : 3}],
				// above the last breakpoint the grid would otherwise fall back to 12 columns
				columnMax : 4,
				breakpointForWindow : true,
				// keep reading order, but pull later widgets up into gaps
				layout : 'compact'
			},
			cellHeight : 300,
			margin : 10,
			auto : false,
			handle : '.dashboardWidgetHeader',
			resizable : {handles : 'se'}
		}, container[0]);
		Vtiger_DashBoard_Js.grid = grid;

		// Init may already have picked 1-3 columns from the window width. Place the
		// saved layout into the full-width space, exactly as saved, and only then
		// reflow it: clamping widgets one by one makes them collide and push each
		// other down.
		grid.column(Vtiger_DashBoard_Js.fullGridColumns, 'none');
		var cols = grid.getColumn();
		var items = container.children('li.dashboardWidget').get();
		items.sort(function(a, b) {
			var rowA = parseInt(jQuery(a).attr('data-row'));
			var rowB = parseInt(jQuery(b).attr('data-row'));
			var colA = parseInt(jQuery(a).attr('data-col'));
			var colB = parseInt(jQuery(b).attr('data-col'));
			if (rowA === rowB) return colA - colB;
			return rowA - rowB;
		});

		// Saved widgets go in first, in reading order, so that their places are
		// authoritative; widgets that were never positioned then flow into
		// whatever space is left instead of taking a saved widget's slot.
		var isUnpositioned = function(li) {
			return jQuery(li).attr('data-position') == 'false';
		};
		items = items.filter(function(li) { return !isUnpositioned(li); })
			.concat(items.filter(isUnpositioned));

		grid.batchUpdate();
		jQuery.each(items, function(i, li) {
			var item = jQuery(li);
			var width = Math.min(parseInt(item.attr('data-sizex')) || 1, cols);
			var options = {w : width, h : parseInt(item.attr('data-sizey')) || 1};
			if (isUnpositioned(li)) {
				options.autoPosition = true;
			} else {
				options.x = Math.min(Math.max((parseInt(item.attr('data-col')) || 1) - 1, 0), cols - width);
				options.y = Math.max((parseInt(item.attr('data-row')) || 1) - 1, 0);
			}
			grid.makeWidget(li, options);
		});
		grid.batchUpdate(false);
		// reflow to the window's column count (a no-op on wide screens)
		grid.onResize();
		items.forEach(function(li) {
			Vtiger_DashBoard_Js.syncWidgetAttrs(li);
		});
		thisInstance.updateEditMode(grid);
		// GridStack reflows by itself through a ResizeObserver on the grid; also
		// do it explicitly on window resize so the read-only switch below always
		// follows the real column count. Always acts on the active tab's grid.
		var reflowTimer;
		jQuery(window).off('resize.dashboardGrid').on('resize.dashboardGrid', function() {
			clearTimeout(reflowTimer);
			reflowTimer = setTimeout(function() {
				thisInstance.reflowActiveGrid();
			}, 200);
		});

		// Positions are saved only after a user action. Saving on load would
		// store whatever column layout the first device happened to render.
		grid.on('resizestart', function(event, el) {
			var widgetContent = jQuery(el).find('.dashboardWidgetContent');
			widgetContent.before(thisInstance.getWaitingForResizeCompleteMsg());
			widgetContent.addClass('hide');
		});
		grid.on('resizestop', function(event, el) {
			var widget = jQuery(el);
			var widgetContent = widget.find('.dashboardWidgetContent');
			widgetContent.prev('.wait_resizing_msg').remove();
			widgetContent.removeClass('hide');
			// let the grid apply the final size before reading it back
			setTimeout(function() {
				Vtiger_DashBoard_Js.syncWidgetAttrs(el);
				var widgetName = widget.data('name');
				/**
				 * we are setting default height in DashBoardWidgetContents.tpl
				 * need to overwrite based on resized widget height
				 */
				var widgetChartContainer = widget.find(".widgetChartContainer");
				if(widgetChartContainer.length > 0){
					widgetChartContainer.css("height",widget.height() - 60);
				}
				widgetChartContainer.html('');
				Vtiger_Widget_Js.getInstance(widget, widgetName);
				widget.trigger(Vtiger_Widget_Js.widgetPostResizeEvent);
				thisInstance.saveWidgetSize(widget);
				thisInstance.persistLayout();
			}, 0);
		});
		grid.on('dragstop', function() {
			setTimeout(function() {
				thisInstance.persistLayout();
			}, 0);
		});
	},

	/**
	 * Apply the responsive column count to the active tab's grid, then
	 * re-evaluate whether it may be edited.
	 */
	reflowActiveGrid : function() {
		var grid = Vtiger_DashBoard_Js.grid;
		if (!grid) return;
		grid.onResize();
		this.updateEditMode(grid);
	},

	/**
	 * Dragging and resizing are allowed only at the full column count.
	 */
	updateEditMode : function(grid) {
		grid.setStatic(grid.getColumn() < Vtiger_DashBoard_Js.fullGridColumns);
	},

	/**
	 * Sync every widget of the active tab from the grid and save the positions.
	 */
	persistLayout : function() {
		var grid = Vtiger_DashBoard_Js.grid;
		// a reflowed (narrower) layout must never replace the full-width one
		if (!grid || grid.getColumn() < Vtiger_DashBoard_Js.fullGridColumns) return;
		var widgets = jQuery('.tab-pane.active .dashboardWidget');
		widgets.each(function(i, el) {
			Vtiger_DashBoard_Js.syncWidgetAttrs(el);
		});
		this.savePositions(widgets);
	},

	savePositions: function(widgets) {
		var widgetRowColPositions = {}
		for (var index=0, len = widgets.length; index < len; ++index) {
			var widget = jQuery(widgets[index]);
			widgetRowColPositions[widget.attr('id')] = JSON.stringify({
					row: widget.attr('data-row'), col: widget.attr('data-col')
			});
		}
		var params = {
			module: 'Vtiger', 
			action: 'SaveWidgetPositions', 
			positionsmap: widgetRowColPositions,
			tabid: widgets.first().closest('.tab-pane').data('tabid')
		};
		app.request.post({"data":params}).then(function(err,data){
		});
	},

	getDashboardWidgets : function() {
		return jQuery('.dashboardWidget', jQuery('.tab-pane.active'));
	},

	 loadWidgets : function() {
		var thisInstance = this;
		var widgetList = thisInstance.getDashboardWidgets();
		widgetList.each(function(index,widgetContainerELement){
			if(thisInstance.isScrolledIntoView(widgetContainerELement)){
				thisInstance.loadWidget(jQuery(widgetContainerELement));
				jQuery(widgetContainerELement).addClass('loadcompleted');
			}
		});
	},

	isScrolledIntoView : function (elem) {
		var viewportWidth = jQuery(window).width(),
		viewportHeight = jQuery(window).height(),

		documentScrollTop = jQuery(document).scrollTop(),
		documentScrollLeft = jQuery(document).scrollLeft(),

		minTop = documentScrollTop,
		maxTop = documentScrollTop + viewportHeight,
		minLeft = documentScrollLeft,
		maxLeft = documentScrollLeft + viewportWidth,

		$targetElement = jQuery(elem),
		elementOffset = $targetElement.offset();
		if (
			(elementOffset.top > minTop && elementOffset.top < maxTop) &&
			(elementOffset.left > minLeft &&elementOffset.left < maxLeft)
			){
				return true;
			 } 
		else {
				return false;
			 }
	},

	loadWidget : function(widgetContainer) {
		var thisInstance = this;
		var urlParams = widgetContainer.data('url');
		var mode = widgetContainer.data('mode');

		var activeTabId = this.getActiveTabId();
		urlParams += "&tab="+activeTabId;
		app.helper.showProgress();
		if(mode == 'open') {
			app.request.post({"url":urlParams}).then(function(err,data){
				widgetContainer.children('.grid-stack-item-content').prepend(data);
				vtUtils.applyFieldElementsView(widgetContainer);

				var widgetChartContainer = widgetContainer.find(".widgetChartContainer");
				if (widgetChartContainer.length > 0) {
					widgetChartContainer.css("height", widgetContainer.height() - 60);
				}

				thisInstance.getWidgetInstance(widgetContainer);
				try {
					widgetContainer.trigger(Vtiger_Widget_Js.widgetPostLoadEvent);
				} catch (error) {
					widgetContainer.find('[name="chartcontent"]').html('<div>'+app.vtranslate('JS_NO_DATA_AVAILABLE')+'</div>').css({'text-align': 'center', 'position': 'relative', 'top': '100px'});
				}
				app.helper.hideProgress();
				// A widget added from the menu only gets its server row when this request runs,
				// so its place can only be saved now (saving earlier updates zero rows)
				if (widgetContainer.hasClass('new')) {
					widgetContainer.removeClass('new');
					thisInstance.persistLayout();
				}
			});
		} else {
		}
	},

	registerRefreshWidget : function() {
		var thisInstance = this;
		this.getContainer().on('click', 'a[name="drefresh"]', function(e) {
			var element = $(e.currentTarget);
			var parent = element.closest('li');
			var widgetInstnace = thisInstance.getWidgetInstance(parent);
			widgetInstnace.refreshWidget();
			return;
		});
	},

	removeWidget : function() {
		this.getContainer().on('click', 'li a[name="dclose"]', function(e) {
			var element = $(e.currentTarget);
			var listItem = jQuery(element).parents('li');
			var width = listItem.attr('data-sizex');
			var height = listItem.attr('data-sizey');

			var url = element.data('url');
			var parent = element.closest('li.dashboardWidget');
			var widgetName = parent.data('name');
			var widgetTitle = parent.find('.dashboardTitle').attr('title');
			var activeTabId = element.closest(".tab-pane").data("tabid");

			var message = app.vtranslate('JS_ARE_YOU_SURE_TO_DELETE_WIDGET', widgetTitle);
			app.helper.showConfirmation({'message' : message, 'htmlSupportEnable' : false}).then(function(e) {
				app.helper.showProgress();
				app.request.post({"url":url}).then(
					function(err,response) {
						if (err == null) {

							var nonReversableWidgets = ['MiniList','Notebook','ChartReportWidget']

							parent.fadeOut('slow', function() {
								Vtiger_DashBoard_Js.grid.removeWidget(parent[0], false);
								parent.remove();
								Vtiger_DashBoard_Js.currentInstance.persistLayout();
							});
							if (jQuery.inArray(widgetName, nonReversableWidgets) == -1) {
								var data = '<li><a onclick="Vtiger_DashBoard_Js.addWidget(this, \''+response.url+'\')" href="javascript:void(0);"';
								data += 'data-width='+width+' data-height='+height+ ' data-linkid='+response.linkid+' data-name='+response.name+'>'+response.title+'</a></li>';
								var divider = jQuery('.widgetsList .divider','#tab_'+activeTabId);
								if(divider.length) {
									jQuery(data).insertBefore(divider);
								} else {
									jQuery(data).insertAfter(jQuery('.widgetsList li:last','#tab_'+activeTabId));
								}
							}
						}
						app.helper.hideProgress();
					}
				);
			});
		});
	},

	registerLazyLoadWidgets : function() {
		var thisInstance = this;
		jQuery(window).on("scroll", function() {
			var widgetList = jQuery('.dashboardWidget').not('.loadcompleted');
			if(!widgetList[0]){
				// We shouldn't unbind as we might have widgets in another tab
				//jQuery(window).unbind('scroll');
			}
			widgetList.each(function(index,widgetContainerELement){
				if(thisInstance.isScrolledIntoView(widgetContainerELement)){
					thisInstance.loadWidget(jQuery(widgetContainerELement));
					jQuery(widgetContainerELement).addClass('loadcompleted');
				}
			});
		});
	},

	registerWidgetFullScreenView : function() {
		var thisInstance = this;
		this.getContainer().on('click','a[name="widgetFullScreen"]',function(e){
			var currentTarget = jQuery(e.currentTarget);
			var widgetContainer = currentTarget.closest('li');
			var widgetName = widgetContainer.data('name');
			var widgetTitle = widgetContainer.find('.dashboardTitle').text();
			var widgetId = widgetContainer.attr('id');
			var data = widgetContainer.find('input.widgetData').val();
			var chartType = '';
			if(widgetContainer.find('input[name="charttype"]').length){
				chartType = widgetContainer.find('input[name="charttype"]').val();
			}
			var clickThrough = 0;
			if(widgetContainer.find('input[name="clickthrough"]').length){
				clickThrough = widgetContainer.find('input[name="clickthrough"]').val();
			}
			var fullscreenview = '<div class="fullscreencontents modal-dialog modal-lg">\n\
									<div class="modal-content">\n\
									<div class="modal-header backgroundColor">\n\
										<div class="clearfix">\n\
											<div class="float-end">\n\
												<button data-bs-dismiss="modal" class="close" title="'+app.vtranslate('JS_CLOSE')+'"><span aria-hidden="true" class="fa fa-close"></span></button>\n\
											</div>\n\
											<h4 class="float-start">'+widgetTitle+'</h4>\n\
										</div>\n\
									</div>\n\
									<div class="modal-body" style="overflow:auto;">\n\
										<ul style="list-style: none;"><li id="fullscreenpreview" class="dashboardWidget fullscreenview" data-name="'+widgetName+'">\n\
											<div class="dashboardWidgetContent" style="min-height:500px;width:100%;min-width:600px; margin: 0 auto" data-displaymode="fullscreen">';
						if(chartType != ''){
							fullscreenview += ' <input type="hidden" value="'+chartType+'" name="charttype">\n\
												<input type="hidden" value="'+clickThrough+'" name="clickthrough">\n\
												<div id="chartDiv" name="chartcontent" style="width:100%;height:100%" data-mode="preview"></div> \n\
												<input class="widgetData" type="hidden" value="" name="data">';
						} else {
							fullscreenview += ' <div class="dashboardWidgetContent" style="width:100%;height:100%" data-displaymode="fullscreen">\n\
													<div id="chartDiv" class="widgetChartContainer" style="width:100%;height:100%"></div>\n\
														<input class="widgetData" type="hidden" value="" name="data">';
						}
							fullscreenview += '</div></ul></li></div></div></div>';

			var callback = function(modalData){
				var element = jQuery(modalData);
				var modal= jQuery(".myModal",element);
				modal.parent().css({'top':'30px','left':'30px','right':'30px','bottom':'30px'});
				modal.css('height','100%');
				var modalWidgetContainer = jQuery('.fullscreenview');
				modalWidgetContainer.find('.widgetData').val(data);
				 if(chartType != ''){
					//Chart report widget 
					var chartClassName = chartType.toCamelCase();
					var chartClass = window["Report_"+chartClassName + "_Js"];
					chartClass('Vtiger_ChartReportWidget_Widget_Js',{},{
						init : function() {
								this._super(modalWidgetContainer);
							}
					});
				}
				var widgetInstance = Vtiger_Widget_Js.getInstance(modalWidgetContainer, widgetName);
				modalWidgetContainer.trigger(Vtiger_Widget_Js.widgetPostLoadEvent);
			}
			app.helper.showModal(fullscreenview,{"cb":callback});
		});
	},

	registerFilterInitiater : function() {
		var container = this.getContainer();
		container.on('click', 'a[name="dfilter"]', function(e) {
			var widgetContainer = jQuery(e.currentTarget).closest('.dashboardWidget');
			var filterContainer = widgetContainer.find('.filterContainer');
			var dashboardWidgetFooter = jQuery('.dashBoardWidgetFooter', widgetContainer);

			widgetContainer.toggleClass('dashboardFilterExpanded');
			filterContainer.slideToggle(500);

			var callbackFunction = function() {
				widgetContainer.toggleClass('dashboardFilterExpanded');
				filterContainer.slideToggle(500);
			}
			//adding clickoutside event on the dashboardWidgetHeader
			var helper = new Vtiger_Helper_Js();
			helper.addClickOutSideEvent(dashboardWidgetFooter, callbackFunction);

			return false;
		})
	},

	registerDeleteDashboardTab : function(){
		var self = this;
		var dashBoardContainer = this.getDashboardContainer();
		dashBoardContainer.off("click",'.deleteTab');
		dashBoardContainer.on("click",'.deleteTab',function(e){
			// To prevent tab click event
			e.preventDefault();
			e.stopPropagation();

			var currentTarget = jQuery(e.currentTarget);
			var tab = currentTarget.closest(".dashboardTab");

			var tabId = tab.data("tabid");
			var tabName = tab.data("tabname");
			var message = app.vtranslate('JS_ARE_YOU_SURE_TO_DELETE_DASHBOARDTAB', tabName);
			app.helper.showConfirmation({'message' : message, 'htmlSupportEnable' : false}).then(function(e) {
				app.helper.showProgress();
				var data = {
					'module' : 'Vtiger',
					'action' : 'DashBoardTab',
					'mode' : 'deleteTab',
					'tabid': tabId
				}

				app.request.post({"data":data}).then(function(err,data){
					app.helper.hideProgress();
					if(err == null){
						jQuery('li[data-tabid="'+tabId+'"]').remove();
						jQuery('.tab-content #tab_'+tabId).remove();

						if(jQuery('.dashboardTab.active').length <= 0){
							// click the first tab if none of the tabs are active
							var firstTab = jQuery('.dashboardTab').get(0);
							jQuery(firstTab).find('a').click();
						}


						app.helper.showSuccessNotification({"message":''});
						if(jQuery('.dashboardTab').length < Vtiger_DashBoard_Js.dashboardTabsLimit ){
							var element = dashBoardContainer.find('li.disabled');
							self.removeQtip(element);
						}

					} else {
						app.helper.showErrorNotification({"message":err});
					}
				});
			});
		});
	},

	registerAddDashboardTab : function(){
		var self = this;
		var dashBoardContainer = this.getDashboardContainer();
		dashBoardContainer.off('click','.addNewDashBoard');
		dashBoardContainer.on("click",".addNewDashBoard",function(e){
			if(jQuery('.dashboardTab').length >= Vtiger_DashBoard_Js.dashboardTabsLimit ){
				app.helper.showErrorNotification({"message":app.vtranslate("JS_TABS_LIMIT_EXCEEDED")});
				return;
			}
			var currentElement = jQuery(e.currentTarget);
			var data = {
				'module'	: 'Home',
				'view'		: 'DashBoardTab',
				'mode'		: 'showDashBoardAddTabForm'
			};

			app.request.post({"data":data}).then(function(err,res){
				if(err === null){
					var cb = function(data){
						var form = jQuery(data).find('#AddDashBoardTab');
						var params = {
							submitHandler : function(form){
								var labelEle = jQuery(form).find('[name="tabName"]');
								var tabName = labelEle.val().trim();
								if(tabName.length > 50) {
									vtUtils.showValidationMessage(labelEle, app.vtranslate('JS_TAB_LABEL_EXCEEDS_CHARS', 50), {
										position: {
											my: 'bottom left',
											at: 'top left',
											container : jQuery(form)
										}
									});
									return false;
								}else {
									vtUtils.hideValidationMessage(labelEle);
								}

								var params = jQuery(form).serializeFormData();
								params['tabName'] = params['tabName'].trim();
								app.request.post({"data":params}).then(function (err,data) {
									app.helper.hideModal();
									if(err) {
										app.helper.showErrorNotification({"message":err});
									}else {
										var tabid = data["tabid"];
										var tabname = data["tabname"];
										var tabEle = '<li class="dashboardTab" data-tabid="'+tabid+'" data-tabname="'+tabname+'">';
										tabEle += '<a data-bs-toggle="tab" href="#tab_'+tabid+'">\n\
														<div>\n\
															<span class="name textOverflowEllipsis" style="width:10%">\n\
															<strong></strong>\n\
															</span>\n\
															<span class="editTabName hide"><input type="text" name="tabName"></span>\n\
															<i class="fa fa-close deleteTab"></i>\n\
															<i class="fa fa-bars moveTab hide"></i>\n\
														</div>\n\
														</a>';
										tabEle += '</li>';

										var tabContentEle = '<div id="tab_'+tabid+'" class="tab-pane fade" data-tabid="'+tabid+'"></div>';

										jQuery('.moreSettings').before(tabEle);
										jQuery('.moreSettings').prev().find('.name > strong').text(tabname);
										dashBoardContainer.find('.tab-content').append(tabContentEle);

										// selecting added tab
										var currentTab = jQuery('li[data-tabid="'+tabid+'"]');
										currentTab.find('a').click();
										if(jQuery('.dashboardTab').length >= Vtiger_DashBoard_Js.dashboardTabsLimit ){
											jQuery('#newDashBoardLi').addClass('disabled');
											self.registerQtipMessage();
										}


									}
								});
							}
						}
						form.vtValidate(params);
					}
					app.helper.showModal(res,{"cb":cb});
				}
			})

		})
	},
	removeQtip : function(element){
		jQuery(element).qtip("destroy");
		element.removeClass('disabled');
	},

	registerQtipMessage: function(){
		var dashBoardContainer = this.getDashboardContainer();
		var element = dashBoardContainer.find('li.disabled');
		var title = app.vtranslate("JS_TABS_LIMIT_EXCEEDED")
			jQuery(element).qtip({
				content: title,
				hide: {
					event:'click mouseleave',
				},
				position: {
					my: 'bottom center',
					at: 'top left',
					adjust: {
						x: 30,
						y: 10
					}
				},
				style: {
					classes: 'qtip-dark'
				}
			});
	},
	registerDashBoardTabRename : function(){
		var container = this.getContainer();
		var dashBoardContainer = jQuery(container).closest(".dashBoardContainer");

		dashBoardContainer.on("dblclick",".dashboardTab",function(e){
			e.preventDefault();
			e.stopPropagation();

			var currentTarget = jQuery(e.currentTarget);
			if(jQuery(".editTabName:visible").length > 0){
				return;
			}
			var nameEle = currentTarget.find(".name");
			var oldName = nameEle.attr("value");
			var editEle = currentTarget.find(".editTabName");

			// Lock renaming default dashboard for user (which otherwise would be recreated)
			if (oldName == "My Dashboard") {
				return;
			}

			nameEle.addClass("hide");
			editEle.removeClass("hide");
			editEle.find("input").val(oldName);

			currentTarget.on("clickoutside",function(e){
				var newName = editEle.find("input").val();
				var tabId = currentTarget.data("tabid");

			if(newName.trim() == "") {
				vtUtils.showValidationMessage(editEle, app.vtranslate('JS_TAB_NAME_SHOULD_NOT_BE_EMPTY'), {
					position : {
					my: 'top left',
					at: 'bottom left',
					container: editEle.closest('.dashboardTab')
					}
				});
				return false;
			}
			vtUtils.hideValidationMessage(editEle);

			if(newName.length > 50) {
				vtUtils.showValidationMessage(editEle, app.vtranslate('JS_TAB_LABEL_EXCEEDS_CHARS', 50), {
					position: {
						my: 'bottom left',
						at: 'top left',
						container : jQuery('.module-action-content')
					}
				});
				return false;
			 } else {
				vtUtils.hideValidationMessage(editEle);
			 }
			currentTarget.off("clickoutside");	
				if(newName != oldName){
					var data = {
						'module' : 'Vtiger',
						'action' : 'DashBoardTab',
						'mode' : 'renameTab',
						'tabid': tabId,
						'tabname': newName
					}
					currentTarget.find('.name > strong').text(newName);
					app.helper.showProgress();
					app.request.post({data:data}).then(function(err,data){
						app.helper.hideProgress();
						if(err == null){
							app.helper.showSuccessNotification({"message":''});
							currentTarget.data('tabname', newName);
						} else {
							app.helper.showErrorNotification({"message":err});
							currentTarget.find('.name > strong').text(oldName);
						}
					})
				}
				nameEle.attr("value",newName);

				editEle.addClass("hide");
				nameEle.removeClass("hide");
			})
		});
	},

	registerDashBoardTabClick : function(){
		var thisInstance = this;
		var container = this.getContainer();
		var dashBoardContainer = jQuery(container).closest(".dashBoardContainer");

		dashBoardContainer[0].addEventListener("shown.bs.tab",function(e){
			var matchedTarget = e.target.closest('.dashboardTab');
			if (!matchedTarget || !dashBoardContainer[0].contains(matchedTarget)) return;
			var currentTarget = jQuery(matchedTarget);
			var tabid = currentTarget.data('tabid');
			app.changeURL("index.php?module=Home&view=DashBoard&tabid="+tabid);

			// If tab is already loaded earlier then we shouldn't reload tab or rebuild its grid
			if(typeof jQuery("#tab_"+tabid).find(".dashBoardTabContainer").val() !== 'undefined'){
				// Point the shared reference at the grid of the tab that was just clicked
				var tabGrid = thisInstance.getContainer(tabid)[0];
				if (tabGrid && tabGrid.gridstack) {
					Vtiger_DashBoard_Js.grid = tabGrid.gridstack;
					// the window may have been resized while this tab was hidden
					thisInstance.reflowActiveGrid();
				}
				return;
			}
			var data = {
				'module': 'Home',
				'view': 'DashBoardTab',
				'mode': 'getTabContents',
				'tabid' : tabid
			}

			app.request.post({"data":data}).then(function(err,data){
				if(err === null){
					var dashBoardModuleName = jQuery("#tab_"+tabid,".tab-content").html(data).find('[name="dashBoardModuleName"]').val();
					if(typeof dashBoardModuleName != 'undefined' && dashBoardModuleName.length > 0 ) {
						var dashBoardInstanceClassName = app.getModuleSpecificViewClass(app.view(),dashBoardModuleName);
						if(dashBoardInstanceClassName != null) {
							var dashBoardInstance = new window[dashBoardInstanceClassName]();
						}
					}
					app.event.trigger("post.DashBoardTab.load", dashBoardInstance);
				}
			});
		});
	},

	registerRearrangeTabsEvent : function(){
		var dashBoardContainer = this.getDashboardContainer();

		// on click of Rearrange button
		dashBoardContainer.on("click",'ul.moreDashBoards .reArrangeTabs',function(e){
			var currentEle = jQuery(e.currentTarget);
			dashBoardContainer.find(".dashBoardDropDown").addClass('hide');

			var sortableContainer = dashBoardContainer.find(".tabContainer");
			var sortableEle = sortableContainer.find(".sortable");

			currentEle.addClass("hide");
			dashBoardContainer.find(".deleteTab").addClass("hide");
			dashBoardContainer.find(".moveTab").removeClass("hide");
			dashBoardContainer.find(".updateSequence").removeClass("hide");

			sortableEle.sortable({
				'containment': sortableContainer,
				stop : function(){}
			});
		});

		// On click of save sequence
		dashBoardContainer.find(".updateSequence").on("click",function(e){
			var reArrangedList = {};
			var currEle = jQuery(e.currentTarget);
			jQuery(".sortable li").each(function(i,el){
				var el = jQuery(el);
				var tabid = el.data("tabid");
				reArrangedList[tabid] = ++i;
			});

			var data = {
				"module" : "Vtiger",
				"action" : "DashBoardTab",
				"mode" : "updateTabSequence",
				"sequence" : JSON.stringify(reArrangedList)
			}

			app.request.post({"data":data}).then(function(err,data){
				if(err == null){
					currEle.addClass("hide");
					dashBoardContainer.find(".moveTab").addClass("hide");
					dashBoardContainer.find(".reArrangeTabs").removeClass("hide");
					dashBoardContainer.find(".deleteTab").removeClass("hide");
					dashBoardContainer.find(".dashBoardDropDown").removeClass('hide');

					var sortableEle = dashBoardContainer.find(".tabContainer").find(".sortable");
					sortableEle.sortable('destroy');

					app.helper.showSuccessNotification({"message":''});
				} else {
					app.helper.showErrorNotification({"message":err});
				}
			});
		});

	},

	registerEvents : function() {
		var thisInstance = this;
		this.registerLazyLoadWidgets();
		this.registerAddDashboardTab();
		this.registerDashBoardTabClick();
		this.registerDashBoardTabRename();
		this.registerDeleteDashboardTab();
		this.registerRearrangeTabsEvent();
		this.registerQtipMessage();
		app.event.off("post.DashBoardTab.load");
		app.event.on("post.DashBoardTab.load",function(event, dashBoardInstance){
			var instance = thisInstance;
			if(typeof dashBoardInstance != 'undefined') {
				instance = dashBoardInstance;
				instance.registerEvents();
			}
			instance.registerGrid();
			instance.loadWidgets();
			instance.registerRefreshWidget();
			instance.removeWidget();
			instance.registerWidgetFullScreenView();
			instance.registerFilterInitiater();
		});
		app.event.trigger("post.DashBoardTab.load");
	}
});

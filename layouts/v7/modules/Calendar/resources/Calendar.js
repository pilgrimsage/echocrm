/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is: vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

Vtiger.Class("Calendar_Calendar_Js", {
	calendarViewContainer: false,
	feedsWidgetPostLoadEvent: 'Calendar.Viewtypes.PostLoad.Event',
	disabledFeedsStorageKey: 'calendar.feeds.disabled',
	calendarInstance: false,
	calendar: false,
	agendaView: false,
	agendaCache: false,
	numberOfDaysInAgendaView: 7,
	userPreferenceCache: [],
	sideBarEssentialsState: '',
	getInstance: function () {
		if (!Calendar_Calendar_Js.calendarInstance) {
			if (app.view() == 'SharedCalendar') {
				Calendar_Calendar_Js.calendarInstance = new Calendar_SharedCalendar_Js();
			} else {
				Calendar_Calendar_Js.calendarInstance = new Calendar_Calendar_Js();
			}
		}
		return Calendar_Calendar_Js.calendarInstance;
	},
	showCreateEventModal: function () {
		var instance = Calendar_Calendar_Js.getInstance();
		instance.showCreateEventModal();
	},
	showCreateTaskModal: function () {
		var instance = Calendar_Calendar_Js.getInstance();
		instance.showCreateTaskModal();
	},
	showCalendarSettings: function () {
		var instance = Calendar_Calendar_Js.getInstance();
		instance.showCalendarSettings();
	},
	deleteCalendarEvent: function (eventId, sourceModule, isRecurring) {
		var instance = Calendar_Calendar_Js.getInstance();
		instance.deleteCalendarEvent(eventId, sourceModule, isRecurring);
	},
	editCalendarEvent: function (eventId, isRecurring) {
		var instance = Calendar_Calendar_Js.getInstance();
		instance.editCalendarEvent(eventId, isRecurring);
	},
	editCalendarTask: function (taskId) {
		var instance = Calendar_Calendar_Js.getInstance();
		instance.editCalendarTask(taskId);
	},
	markAsHeld: function (recordId) {
		var instance = Calendar_Calendar_Js.getInstance();
		instance.markAsHeld(recordId);
	},
	holdFollowUp: function (eventId) {
		var instance = Calendar_Calendar_Js.getInstance();
		instance.holdFollowUp(eventId);
	}

}, {
	init: function () {
		this.addComponents();
	},
	addComponents: function () {
		this.addIndexComponent();
	},
	addIndexComponent: function () {
		this.addModuleSpecificComponent('Index', 'Vtiger', app.getParentModuleName());
	},
	registerCreateFollowUpEvent: function (modalContainer) {
		var thisInstance = this;
		var params = {
			submitHandler: function (form) {
				form = jQuery(form);
				form.find('[type="submit"]').attr('disabled', 'disabled');
				var formData = form.serializeFormData();
				app.helper.showProgress();
				app.request.post({'data': formData}).then(function (err, res) {
					app.helper.hideProgress();
					app.helper.hideModal();
					if (!err && res['created']) {
						jQuery('.vt-notification').remove();
						thisInstance.updateListView();
						thisInstance.updateCalendarView("Event");
					} else {
						app.event.trigger('post.save.failed', err);
					}
				});
			}
		};
		modalContainer.find('form#followupQuickCreate').vtValidate(params);
	},
	holdFollowUp: function (eventId) {
		var thisInstance = this;
		var requestParams = {
			'module': 'Calendar',
			'view': 'QuickCreateFollowupAjax',
			'record': eventId
		};
		app.helper.showProgress();
		app.request.get({'data': requestParams}).then(function (err, resp) {
			app.helper.hideProgress();
			if (!err && resp) {
				app.helper.showModal(resp, {
					'cb': function (modalContainer) {
						thisInstance.registerCreateFollowUpEvent(modalContainer);
					}
				});
			}
		});
	},
	updateListView: function () {
		if (app.view() === 'List') {
			var listInstance = Vtiger_List_Js.getInstance();
			listInstance.loadListViewRecords();
		}
	},
	updateCalendarView: function (activitytype) {
		if (app.view() === 'Calendar' || app.view() === 'SharedCalendar') {
			if (activitytype === 'Event') {
				this.updateAllEventsOnCalendar();
			} else {
				this.updateAllTasksOnCalendar();
			}
		}
	},
	markAsHeld: function (recordId) {
		var thisInstance = this;
		app.helper.showConfirmation({
			message: app.vtranslate('JS_CONFIRM_MARK_AS_HELD')
		}).then(function () {
			var requestParams = {
				module: "Calendar",
				action: "SaveFollowupAjax",
				mode: "markAsHeldCompleted",
				record: recordId
			};

			app.request.post({'data': requestParams}).then(function (e, res) {
				jQuery('.vt-notification').remove();
				if (e) {
					app.event.trigger('post.save.failed', e);
				} else if (res && res['valid'] === true && res['markedascompleted'] === true) {
					thisInstance.updateListView();
					thisInstance.updateCalendarView(res.activitytype);
				} else {
					app.helper.showAlertNotification({
						'message': app.vtranslate('JS_FUTURE_EVENT_CANNOT_BE_MARKED_AS_HELD')
					});
				}
			});
		});
	},
	registerCalendarSharingTypeChangeEvent: function (modalContainer) {
		var selectedUsersContainer = app.helper.getSelect2FromSelect(
				jQuery('#selectedUsers', modalContainer)
				);
		jQuery('[name="sharedtype"]').on('change', function () {
			var sharingType = jQuery(this).data('sharingtype');

			if (sharingType === 'selectedusers') {
				selectedUsersContainer.show();
				selectedUsersContainer.attr('style', 'display:block;width:90%;');
			} else {
				selectedUsersContainer.hide();
			}
		});
		jQuery('[name="sharedtype"]:checked').trigger('change');
	},
	registerHourFormatChangeEvent: function (modalContainer) {
		var hourFormatConditionMapping = jQuery('input[name="timeFormatOptions"]', modalContainer).data('value');
		var form = modalContainer.find('form');
		form.find('input[name="hour_format"]').on('click', function () {
			var hourFormatVal = jQuery(this).val();
			var startHourElement = jQuery('select[name="start_hour"]', form);
			var conditionSelected = startHourElement.val();
			var list = hourFormatConditionMapping['hour_format'][hourFormatVal]['start_hour'];
			var options = '';
			for (var key in list) {
				if (list.hasOwnProperty(key)) {
					var conditionValue = list[key];
					options += '<option value="' + key + '"';
					if (key === conditionSelected) {
						options += ' selected="selected" ';
					}
					options += '>' + conditionValue + '</option>';
				}
			}
			startHourElement.html(options).trigger("change");
		});
	},
	registerCalendarSettingsShownEvents: function (modalContainer) {
		this.registerCalendarSharingTypeChangeEvent(modalContainer);
		this.registerHourFormatChangeEvent(modalContainer);
		app.helper.showVerticalScroll(jQuery('.modal-body'), {setHeight: '400px'});
		vtUtils.enableTooltips();
		modalContainer.find('button[name="saveButton"]').on('click', function () {
			jQuery(this).attr('disabled', 'disabled');
			modalContainer.find('form').find('[name="sourceView"]').val(app.view());
			modalContainer.find('form').submit();
		});
	},
	showCalendarSettings: function () {
		var thisInstance = this;
		var params = {
			'module': app.getModuleName(),
			'view': 'Calendar',
			'mode': 'Settings'
		};
		app.helper.showProgress();
		app.request.post({'data': params}).then(function (e, data) {
			app.helper.hideProgress();
			if (!e) {
				app.helper.showModal(data, {
					'cb': function (modalContainer) {
						thisInstance.registerCalendarSettingsShownEvents(modalContainer);
					}
				});
			} else {
				console.log("network error : ", e);
			}
		});
	},
	getDisabledFeeds: function () {
		return app.storage.get(Calendar_Calendar_Js.disabledFeedsStorageKey, []);
	},
	disableFeed: function (sourceKey) {
		var disabledFeeds = this.getDisabledFeeds();
		if (disabledFeeds.indexOf(sourceKey) === -1) {
			disabledFeeds.push(sourceKey);
			app.storage.set(Calendar_Calendar_Js.disabledFeedsStorageKey, disabledFeeds);
		}
	},
	enableFeed: function (sourceKey) {
		var disabledFeeds = this.getDisabledFeeds();
		if (disabledFeeds.indexOf(sourceKey) !== -1) {
			disabledFeeds = jQuery.grep(disabledFeeds, function (value) {
				return value !== sourceKey;
			});
			app.storage.set(Calendar_Calendar_Js.disabledFeedsStorageKey, disabledFeeds);
		}
	},
	getFeedRequestParams: function (start, end, feedCheckbox) {
		var userFormat = jQuery('body').data('userDateformat').toUpperCase();
		var dateFormat = userFormat;
		var startDate = moment(start).format(dateFormat);
		var endDate = moment(end).format(dateFormat);
		return {
			'start': startDate,
			'end': endDate,
			'type': feedCheckbox.data('calendarFeed'),
			'fieldname': feedCheckbox.data('calendarFieldname'),
			'color': feedCheckbox.data('calendarFeedColor'),
			'textColor': feedCheckbox.data('calendarFeedTextcolor'),
			'conditions': feedCheckbox.data('calendarFeedConditions')
		};
	},
	getActiveFeedsRequest: function (start, end, calendarModule) {
		var thisInstance = this;
		var selector = calendarModule ? '[data-calendar-feed="' + calendarModule + '"]:checked' : 'input[data-calendar-feed]:checked';
		var activeFeeds = jQuery(selector);
		var activeFeedsRequestParams = {};
		activeFeeds.each(function () {
			var feedCheckbox = jQuery(this);
			activeFeedsRequestParams[feedCheckbox.data('calendarSourcekey')] = thisInstance.getFeedRequestParams(start, end, feedCheckbox);
		});
		return {feeds: activeFeeds, params: activeFeedsRequestParams};
	},
	loadEvents: function (info, success, failure) {
		var request = this.getActiveFeedsRequest(info.start, info.end);
		if (!request.feeds.length || (Calendar_Calendar_Js.calendar && Calendar_Calendar_Js.calendar.view.type === 'vtAgendaList')) {
			success([]);
			return;
		}
		var requestParams = {
			'module': app.getModuleName(),
			'action': 'Feed',
			'mode': 'batch',
			'feedsRequest': request.params
		};
		var events = [];
		app.helper.showProgress();
		request.feeds.attr('disabled', 'disabled');
		app.request.post({'data': requestParams}).then(function (e, data) {
			if (!e) {
				data = JSON.parse(data);
				for (var feedType in data) {
					JSON.parse(data[feedType]).forEach(function (entry) {
						events.push(entry);
					});
				}
			} else {
				console.log("error in response : ", e);
			}
			success(events);
			request.feeds.removeAttr('disabled');
			app.helper.hideProgress();
		});
	},
	refetchEvents: function () {
		if (Calendar_Calendar_Js.calendar) {
			Calendar_Calendar_Js.calendar.refetchEvents();
		}
	},
	assignFeedTextColor: function (feedCheckbox) {
		var color = feedCheckbox.data('calendarFeedColor');
		var contrast = app.helper.getColorContrast(color);
		var textColor = (contrast === 'dark') ? 'white' : 'black';
		feedCheckbox.data('calendarFeedTextcolor', textColor);
		feedCheckbox.closest('.calendar-feed-indicator').css({'color': textColor});
	},
	colorizeFeed: function (feedCheckbox) {
		this.assignFeedTextColor(feedCheckbox);
	},
	restoreFeedsState: function (widgetContainer) {
		var thisInstance = this;
		var disabledFeeds = this.getDisabledFeeds();
		var feedsList = widgetContainer.find('#calendarview-feeds > ul.feedslist');
		var calendarfeeds = feedsList.find('[data-calendar-feed]');
		calendarfeeds.each(function () {
			var feedCheckbox = jQuery(this);
			var sourceKey = feedCheckbox.data('calendarSourcekey');
			if (disabledFeeds.indexOf(sourceKey) === -1) {
				feedCheckbox.attr('checked', true);
			}
			thisInstance.colorizeFeed(feedCheckbox);
		});
	},
	addEvents: function (feedCheckbox) {
		if (feedCheckbox.is(':checked')) {
			this.refetchEvents();
		}
	},
	removeEvents: function (feedCheckbox) {
		this.refetchEvents();
	},
	registerFeedChangeEvent: function () {
		var thisInstance = this;
		jQuery('#calendarview-feeds').on('change',
				'input[type="checkbox"].toggleCalendarFeed',
				function () {
					var curentTarget = jQuery(this);
					var sourceKey = curentTarget.data('calendarSourcekey');
					if (curentTarget.is(':checked')) {
						thisInstance.enableFeed(sourceKey);
						thisInstance.addEvents(curentTarget);
					} else {
						thisInstance.disableFeed(sourceKey);
						thisInstance.removeEvents(curentTarget);
					}
				});
	},
	updateRangeFields: function (container, options) {
		var moduleName = container.find('select[name="modulesList"]').val();
		var fieldSelectElement = container.find('select[name="fieldsList"]');

		var sourceFieldSelect = container.find('select[name="sourceFieldsList"]');
		var targetFieldSelect = container.find('select[name="targetFieldsList"]');
		fieldSelectElement.removeAttr('disabled');

		var optionsCount = fieldSelectElement.find('option').not('option[value="birthday"]');

		if (moduleName === 'Events' || moduleName === 'Calendar') {
			optionsCount = fieldSelectElement.find('option').not('option[value="date_start,due_date"]');
		}

		if (optionsCount.length > 1) {
			container.find('[name="rangeFields"]').removeAttr('disabled').trigger('change');
		} else {
			container.find('[name="rangeFields"]').attr('disabled', true).attr('checked', false).trigger('change');
		}

		var selectedValue = fieldSelectElement.find('option:selected').val();
		sourceFieldSelect.html(options).select2();
		targetFieldSelect.html(options).select2();

		if (moduleName === 'Events' || moduleName === 'Calendar') {
			sourceFieldSelect.find('option[value="date_start,due_date"]').remove();
			targetFieldSelect.find('option[value="date_start,due_date"]').remove();
		}
		sourceFieldSelect.find('option[value="birthday"]').remove();
		targetFieldSelect.find('option[value="birthday"]').remove();
		if (selectedValue === 'birthday') {
			selectedValue = fieldSelectElement.find('option:selected').next().val();
		}
		var otherOption = targetFieldSelect.find('option').not('option[value="' + selectedValue + '"]');
		sourceFieldSelect.val(selectedValue).trigger('change.select2');
		if (otherOption.length > 0) {
			targetFieldSelect.val(otherOption.val()).trigger('change.select2');
		} else {
			targetFieldSelect.html('').select2();
		}
	},
	updateDateFields: function (container) {
		var fieldMeta = container.find('[name="moduleDateFields"]').data('value');
		var moduleSelectElement = container.find('select[name="modulesList"]');
		var moduleName = moduleSelectElement.val();

		var fieldSelectElement = container.find('select[name="fieldsList"]');

		var options = '';
		for (var key in fieldMeta) {
			if (fieldMeta.hasOwnProperty(key) && key === moduleName) {
				var moduleSpecificFields = fieldMeta[key];
				for (var fieldName in moduleSpecificFields) {
					if (moduleSpecificFields.hasOwnProperty(fieldName)) {
						options += '<option value="' + fieldName + '" data-viewfieldname="' + fieldName + '">' +
								moduleSpecificFields[fieldName] + '</option>';
					}
				}
			}
		}
		if (options === '')
			options = '<option value="">NONE</option>';

		fieldSelectElement.html(options).select2().trigger('change');

		var editorMode = container.find('.editorMode').val();
		if (editorMode === 'create') {
			this.updateRangeFields(container, options);
		}
	},
	getRandomColor: function () {
		return '#' + (0x1000000 + (Math.random()) * 0xffffff).toString(16).substr(1, 6);
	},
	registerDateFieldChangeEvent: function (modalContainer) {
		var thisInstance = this;
		var parentElement = jQuery('#calendarview-feeds');
		var fieldsSelect = modalContainer.find('[name="fieldsList"]');

		fieldsSelect.on('change', function () {
			var moduleName = modalContainer.find('[name="modulesList"]').find('option:selected').val();
			var selectedOption = jQuery(this).find('option:selected');
			var fieldName = selectedOption.val();
			var currentColor = thisInstance.getRandomColor();

			var calendarSourceKey = moduleName + '_' + fieldName;
			if (moduleName === 'Events') {
				var conditions = modalContainer.find('#calendarviewconditions').val();
				conditions = thisInstance._getParsedConditions(conditions);
				if (conditions.hasOwnProperty('value')) {
					calendarSourceKey += '_' + conditions.value;
				}
			}

			var feedCheckbox = jQuery('[data-calendar-sourcekey="' + calendarSourceKey + '"]', parentElement);
			if (feedCheckbox.length) {
				currentColor = feedCheckbox.data('calendarFeedColor');
			}
			modalContainer.find('.selectedColor').val(currentColor);
		});
		modalContainer.find('#calendarviewconditions').on('change', function () {
			fieldsSelect.trigger('change');
		});
	},
	_getParsedConditions: function (conditions) {
		var parsedConditions = {};
		if (conditions !== '') {
			parsedConditions = JSON.parse(conditions);
			if (typeof parsedConditions !== 'object') {
				parsedConditions = JSON.parse(parsedConditions);
			}
		}
		return parsedConditions;
	},
	saveFeedSettings: function (modalContainer, feedIndicator) {
		var thisInstance = this;
		var modulesList = modalContainer.find('select[name="modulesList"]');
		var moduleName = modulesList.val();
		var fieldName = modalContainer.find('select[name="fieldsList"]').val();
		var selectedColor = modalContainer.find('input.selectedColor').val();
		var conditions = '';
		if (moduleName === 'Events') {
			conditions = modalContainer.find('[name="conditions"]').val();
			if (conditions !== '') {
				conditions = JSON.stringify(conditions);
			}
		}

		var editorMode = modalContainer.find('.editorMode').val();
		if (editorMode === 'create') {
			var translatedFieldName = modalContainer.find('.selectedType').data('typename');
			if (modalContainer.find('[name="rangeFields"]').is(':checked')) {
				var sourceValue = modalContainer.find('[name="sourceFieldsList"]').val();
				var targetValue = modalContainer.find('[name="targetFieldsList"]').val();
				fieldName = sourceValue + ',' + targetValue;
				translatedFieldName = modalContainer.find('[name="sourceFieldsList"] option:selected').text() + ',' + modalContainer.find('[name="targetFieldsList"] option:selected').text();
			}
		}

		var params = {
			module: 'Calendar',
			action: 'CalendarUserActions',
			mode: 'addCalendarView',
			viewmodule: moduleName,
			viewfieldname: fieldName,
			viewColor: selectedColor,
			viewConditions: conditions
		};

		app.helper.showProgress();
		app.request.post({'data': params}).then(function (e, data) {
			if (!e) {
				var contrast = app.helper.getColorContrast(selectedColor);
				var textColor = (contrast === 'dark') ? 'white' : 'black';
				var message = app.vtranslate('JS_CALENDAR_VIEW_COLOR_UPDATED_SUCCESSFULLY');
				var parsedConditions = thisInstance._getParsedConditions(conditions);
				var feedIndicatorTitle = moduleName + '-' + translatedFieldName;
				var calendarSourceKey = moduleName + '_' + fieldName;

				if (parsedConditions.hasOwnProperty('value')) {
					calendarSourceKey += '_' + parsedConditions.value;
					feedIndicatorTitle = moduleName + '(' + app.vtranslate(parsedConditions.value) + ') -' + translatedFieldName;
				}

				if (editorMode === 'create') {
					var translatedModuleName = modulesList.find('option:selected').text();
					var feedIndicatorTemplate = jQuery('#calendarview-feeds').find('ul.dummy > li.feed-indicator-template');
					feedIndicatorTemplate.removeClass('.feed-indicator-template');
					var newFeedIndicator = feedIndicatorTemplate.clone(true, true);
					//replacing module name prefix with translated module name and concatinating with field name
					var feedIndicatorModuleEndIndex = feedIndicatorTitle.indexOf('('); // Events (ActivityType) - title...
					if (feedIndicatorModuleEndIndex == -1) { // ModuleName - title...
							feedIndicatorModuleEndIndex = feedIndicatorTitle.indexOf('-');
					}
					feedIndicatorTitle = translatedModuleName + feedIndicatorTitle.substr(feedIndicatorModuleEndIndex);
					newFeedIndicator.find('span:first').text(feedIndicatorTitle);
					var newFeedCheckbox = newFeedIndicator.find('.toggleCalendarFeed');
					newFeedCheckbox.attr('data-calendar-sourcekey', calendarSourceKey).
							attr('data-calendar-feed', moduleName).
							attr('data-calendar-fieldlabel', translatedFieldName).
							attr('data-calendar-fieldname', fieldName).
							attr('title', translatedModuleName).
							attr('checked', 'checked');
					if (data['type']) {
						newFeedCheckbox.attr('data-calendar-type', data['type']);
					}
					feedIndicator = newFeedIndicator;
					jQuery('#calendarview-feeds').find('ul:first').append(feedIndicator);
					message = app.vtranslate('JS_CALENDAR_VIEW_ADDED_SUCCESSFULLY');
				} else {
					feedIndicator = jQuery('#calendarview-feeds')
							.find('[data-calendar-sourcekey="' + calendarSourceKey + '"]')
							.closest('.calendar-feed-indicator');
				}

				feedIndicator.css({'background-color': selectedColor, 'color': textColor});
				var feedCheckbox = feedIndicator.find('.toggleCalendarFeed');
				feedCheckbox.data('calendarFeedColor', selectedColor).
						data('calendarFeedTextcolor', textColor).
						data('calendarFeedConditions', conditions);
				thisInstance.refreshFeed(feedCheckbox);

				app.helper.hideProgress();
				app.helper.hideModal();
				app.helper.showSuccessNotification({'message': message});
			} else {
				console.log("error occured while saving : ", params, e);
			}
		});
	},
	registerColorEditorSaveEvent: function (modalContainer, feedIndicator) {
		var thisInstance = this;
		modalContainer.find('[name="saveButton"]').on('click', function () {
			var currentTarget = jQuery(this);
			currentTarget.attr('disabled', 'disabled');
			var modulesSelect = modalContainer.find('select[name="modulesList"]');
			var fieldsSelect = modalContainer.find('select[name="fieldsList"]');
			var selectedType = modalContainer.find('.selectedType');

			var moduleName = modulesSelect.val();
			var fieldName = fieldsSelect.val();

			selectedType.val(fieldName).data(
					'typename',
					fieldsSelect.find('option:selected').text()
					);

			var selectedColor = modalContainer.find('.selectedColor').val(),
					conditions = '';
			if (moduleName === 'Events') {
				conditions = modalContainer.find('[name="conditions"]').val();
				if (conditions !== '') {
					conditions = JSON.stringify(conditions);
				}
			}

			thisInstance.checkDuplicateFeed(moduleName, fieldName, selectedColor, conditions).then(
					function (result) {
						thisInstance.saveFeedSettings(modalContainer, feedIndicator);
					},
					function () {
						app.helper.showErrorNotification({'message': app.vtranslate('JS_CALENDAR_VIEW_YOU_ARE_EDITING_NOT_FOUND')});
						currentTarget.removeAttr('disabled');
					});
		});
	},
	registerColorEditorEvents: function (modalContainer, feedIndicator) {
		var thisInstance = this;
		var feedCheckbox = feedIndicator.find('input[type="checkbox"].toggleCalendarFeed');

		thisInstance.registerDateFieldChangeEvent(modalContainer);

		var modulesSelect = modalContainer.find('[name="modulesList"]');
		modulesSelect.on('change', function () {
			thisInstance.updateDateFields(modalContainer);
//handling eventtype condition element display
			var module = jQuery(this).val();
			if (module === 'Events') {
				modalContainer.find('#js-eventtype-condition').removeClass('hide');
				var feedConditions = feedCheckbox.data('calendarFeedConditions');
				if (feedConditions !== '') {
					modalContainer.find('[name="conditions"]').val(JSON.parse(feedConditions)).trigger('change');
				}
			} else {
				modalContainer.find('#js-eventtype-condition').addClass('hide');
			}
		}).val(feedCheckbox.data('calendarFeed')).trigger('change.select2').trigger('change');

		var fieldSelectElement = modalContainer.find('[name="fieldsList"]');
		fieldSelectElement.val(feedCheckbox.data('calendarFieldname')).trigger('change.select2').trigger('change');

		thisInstance.registerColorEditorSaveEvent(modalContainer, feedIndicator);
	},
	showColorEditor: function (feedIndicator) {
		var thisInstance = this;
		var params = {
			module: app.getModuleName(),
			view: 'ActivityTypeViews',
			mode: 'editActivityType'
		};
		app.helper.showProgress();
		app.request.post({'data': params}).then(function (e, data) {
			app.helper.hideProgress();
			if (!e) {
				app.helper.showModal(data, {
					'cb': function (modalContainer) {
						thisInstance.registerColorEditorEvents(modalContainer, feedIndicator);
					}
				});
			} else {
				console.log("network error : ", e);
			}
		});
	},
	registerFeedsColorEditEvent: function () {
		var thisInstance = this;
		jQuery('#calendarview-feeds').on('click', '.editCalendarFeedColor',
				function () {
					var feedIndicator = jQuery(this).closest('li.calendar-feed-indicator');
					thisInstance.showColorEditor(feedIndicator);
				});
	},
	getFeedDeleteParameters: function (feedCheckbox) {
		return {
			module: 'Calendar',
			action: 'CalendarUserActions',
			mode: 'deleteCalendarView',
			viewmodule: feedCheckbox.data('calendarFeed'),
			viewfieldname: feedCheckbox.data('calendarFieldname'),
			viewfieldlabel: feedCheckbox.data('calendarFieldlabel'),
			viewConditions: feedCheckbox.data('calendarFeedConditions')
		};
	},
	deleteFeed: function (feedIndicator) {
		var thisInstance = this;
		var feedCheckbox = feedIndicator.find('input[type="checkbox"].toggleCalendarFeed');
		var params = thisInstance.getFeedDeleteParameters(feedCheckbox);

		app.helper.showProgress();
		app.request.post({'data': params}).then(function (e) {
			if (!e) {
				thisInstance.removeEvents(feedCheckbox);
				feedIndicator.remove();
				app.helper.showSuccessNotification({
					message: app.vtranslate('JS_CALENDAR_VIEW_DELETED_SUCCESSFULLY')
				});
			} else {
				console.log("error : ", e);
			}
			app.helper.hideProgress();
		});
	},
	registerFeedDeleteEvent: function () {
		var thisInstance = this;
		jQuery('#calendarview-feeds').on('click', '.deleteCalendarFeed',
				function () {
					var feedIndicator = jQuery(this).closest('.calendar-feed-indicator');
					app.helper.showConfirmation({
						message: app.vtranslate('JS_CALENDAR_VIEW_DELETE_CONFIRMATION')
					}).then(function () {
						thisInstance.deleteFeed(feedIndicator);
					});
				});
	},
	checkDuplicateFeed: function (moduleName, fieldName, selectedColor, conditions) {
		var aDeferred = jQuery.Deferred();
		var params = {
			'module': 'Calendar',
			'action': 'CalendarUserActions',
			'mode': 'checkDuplicateView',
			'viewmodule': moduleName,
			'viewfieldname': fieldName,
			'viewColor': selectedColor,
			'viewConditions': conditions
		};
		app.request.post({'data': params}).then(function (e, result) {
			if (!e) {
				if (result['success']) {
					aDeferred.resolve(result);
				} else {
					aDeferred.reject(result);
				}
			} else {
				console.log("error : ", e);
			}
		});
		return aDeferred.promise();
	},
	registerAddActivityTypeEvent: function (modalContainer) {
		var thisInstance = this;
		modalContainer.find('[name="saveButton"]').on('click', function () {
			var currentTarget = jQuery(this);
			currentTarget.attr('disabled', 'disabled');
			var fieldSelect = modalContainer.find('select[name="fieldsList"]');
			var selectedType = modalContainer.find('.selectedType');
			selectedType.val(fieldSelect.val()).data(
					'typename',
					fieldSelect.find('option:selected').text()
					);
			var moduleName = modalContainer.find('select[name="modulesList"]').val();
			var fieldName = fieldSelect.val();
			if (modalContainer.find('[name="rangeFields"]').is(':checked')) {
				var sourceValue = modalContainer.find('[name="sourceFieldsList"]').val();
				var targetValue = modalContainer.find('[name="targetFieldsList"]').val();
				fieldName = sourceValue + ',' + targetValue;
			}
			var selectedColor = modalContainer.find('.selectedColor').val(),
					conditions = '';
			if (moduleName === 'Events') {
				conditions = modalContainer.find('[name="conditions"]').val();
				if (conditions !== '') {
					conditions = JSON.stringify(conditions);
				}
			}

			thisInstance.checkDuplicateFeed(moduleName, fieldName, selectedColor, conditions).then(
					function(result) {
					    app.helper.showErrorNotification({'message':result['message']});
					    currentTarget.removeAttr('disabled');
					},
					function() {
					    thisInstance.saveFeedSettings(modalContainer);
					});
		});
	},
	registerAddActivityTypeFeedActions: function (modalContainer) {
		var thisInstance = this;
		thisInstance.registerDateFieldChangeEvent(modalContainer);

		var modulesSelect = modalContainer.find('[name="modulesList"]');
		modulesSelect.on('change', function () {
			thisInstance.updateDateFields(modalContainer);
//handling eventtype condition element display
			var module = jQuery(this).val();
			if (module === 'Events') {
				modalContainer.find('#js-eventtype-condition').removeClass('hide');
			} else {
				modalContainer.find('#js-eventtype-condition').addClass('hide');
			}
		}).trigger('change');

		var sourceFieldsSelect = modalContainer.find('select[name="sourceFieldsList"]');
		sourceFieldsSelect.on('change', function () {
			var selectedValue = sourceFieldsSelect.find('option:selected').val();
			if (selectedValue === targetFieldsSelect.find('option:selected').val()) {
				var otherOption = targetFieldsSelect.find('option').not('option[value="' + selectedValue + '"]');
				targetFieldsSelect.val(otherOption.val()).trigger('change.select2');
			}
		});

		var targetFieldsSelect = modalContainer.find('select[name="targetFieldsList"]');
		targetFieldsSelect.on('change', function () {
			var selectedValue = targetFieldsSelect.find('option:selected').val();
			if (selectedValue === sourceFieldsSelect.find('option:selected').val()) {
				var otherOption = sourceFieldsSelect.find('option').not('option[value="' + selectedValue + '"]');
				sourceFieldsSelect.val(otherOption.val()).trigger('change.select2');
			}
		});

		var rangeFieldsOption = modalContainer.find('[name="rangeFields"]');
		rangeFieldsOption.on('change', function () {
			var fieldSelectEle = modalContainer.find('select[name="fieldsList"]');
			var sourceFieldsSelect = modalContainer.find('select[name="sourceFieldsList"]');
			var targetFieldsSelect = modalContainer.find('select[name="targetFieldsList"]');
			if (rangeFieldsOption.is(':checked')) {
				fieldSelectEle.attr('disabled', true);
				sourceFieldsSelect.removeAttr('disabled');
				targetFieldsSelect.removeAttr('disabled');
			} else {
				fieldSelectEle.removeAttr('disabled');
				sourceFieldsSelect.attr('disabled', true);
				targetFieldsSelect.attr('disabled', true);
			}

			//after disabling or enabling, set the options and selected value for select2 elements
			var fieldSelectedValue = fieldSelectEle.find('option:selected').val();
			var fieldOptions = fieldSelectEle.find('option');
			fieldSelectEle.html(fieldOptions).select2();
			fieldSelectEle.val(fieldSelectedValue).trigger('change.select2');

			var sourceOptions = sourceFieldsSelect.find('option');
			sourceFieldsSelect.html(sourceOptions).select2();
			sourceFieldsSelect.val(fieldSelectedValue).trigger('change.select2');

			var sourceSelectValue = sourceFieldsSelect.find('option:selected').val();
			var otherOption = targetFieldsSelect.find('option').not('option[value="' + sourceSelectValue + '"]');
			var targetOptions = targetFieldsSelect.find('option');
			targetFieldsSelect.html(targetOptions).select2();
			targetFieldsSelect.val(otherOption.val()).trigger('change.select2');
		});

		thisInstance.registerAddActivityTypeEvent(modalContainer);
	},
	showAddActivityTypeFeedView: function () {
		var thisInstance = this;
		var params = {
			module: app.getModuleName(),
			view: 'ActivityTypeViews',
			mode: 'addActivityType'
		};
		app.helper.showProgress();
		app.request.post({'data': params}).then(function (e, data) {
			app.helper.hideProgress();
			if (!e) {
				app.helper.showModal(data, {
					'cb': function (modalContainer) {
						thisInstance.registerAddActivityTypeFeedActions(modalContainer);
					}
				});
			} else {
				console.log("network error : ", e);
			}
		});
	},
	showAddCalendarFeedEditor: function () {
		this.showAddActivityTypeFeedView();
	},
	registerFeedAddEvent: function (widgetContainer) {
		var thisInstance = this;
		widgetContainer.find('.add-calendar-feed').on('click', function () {
			thisInstance.showAddCalendarFeedEditor();
		});
	},
	registerWidgetPostLoadEvent: function () {
		var thisInstance = this;
		app.event.on(Calendar_Calendar_Js.feedsWidgetPostLoadEvent,
				function (e, widgetContainer) {
					thisInstance.restoreFeedsState(widgetContainer);
					thisInstance.refetchEvents();
					thisInstance.registerFeedAddEvent(widgetContainer);
					thisInstance.registerFeedChangeEvent();
					thisInstance.registerFeedsColorEditEvent();
					thisInstance.registerFeedDeleteEvent();
					thisInstance.registerFeedMassSelectEvent();
				});
	},

	/**
	 * Event listener for change on mass select checkbox.
	 * Click/set true/false on all non-matching checkboxes & 
	 * trigger change event. Contributed by Libertus Solutions
	**/
	registerFeedMassSelectEvent : function() {
		var container = jQuery('#calendarview-feeds');
		var calendarFeeds = jQuery('ul.feedslist input.toggleCalendarFeed', container);
		jQuery('input.mass-select', container).on('change', function() {
			var massSelectchecked = this.checked;
			calendarFeeds.each(function(i) {
				// Only trigger change where necessary
				if(this.checked != massSelectchecked) {
					this.checked = massSelectchecked;
					jQuery(this).change();
				}
			});
		});
	},

	changeWidgetDisplayState: function (widget, state) {
		var key = widget.data('widgetName') + '_WIDGET_DISPLAY_STATE';
		app.storage.set(key, state);
	},
	registerCollapseEvents: function (widget) {
		var thisInstance = this;
		widget[0].addEventListener('hide.bs.collapse', function (e) {
			var widgetStateIndicator = widget.find('i.widget-state-indicator');
			widgetStateIndicator.removeClass('fa-chevron-down').addClass('fa-chevron-right');
			thisInstance.changeWidgetDisplayState(widget, 'hide');
		});
		widget[0].addEventListener('show.bs.collapse', function (e) {
			var widgetStateIndicator = widget.find('i.widget-state-indicator');
			widgetStateIndicator.removeClass('fa-chevron-right').addClass('fa-chevron-down');
			thisInstance.changeWidgetDisplayState(widget, 'show');
		});
	},
	getWidgetDisplayState: function (widget) {
		var key = widget.data('widgetName') + '_WIDGET_DISPLAY_STATE';
		var value = app.storage.get(key);
		return (value !== null) ? value : 'show';
	},
	restoreWidgetState: function (widget) {
		if (this.getWidgetDisplayState(widget) === 'show') {
			widget.find('.sidebar-widget-header > a').trigger('click');
		}
	},
	initializeWidgets: function () {
		var thisInstance = this;
		var widgets = jQuery('.sidebar-widget');
		jQuery.each(widgets, function () {
			var widget = jQuery(this);
			var widgetHeader = widget.find('.sidebar-widget-header');
			var dataUrl = widgetHeader.data('url');
			var dataParams = app.convertUrlToDataParams(dataUrl);
			var widgetBody = widget.find('.sidebar-widget-body');
			app.request.post({data: dataParams}).then(function (e, data) {
				if (!e) {
					widgetBody.html(data);
                                        let fullCalendarViewHeight = $('.fc-view-harness').height();
                                        widgetBody.css('max-height', (fullCalendarViewHeight - 10) + 'px');
					app.helper.showVerticalScroll(
							widgetBody,
							{
								'autoHideScrollbar': true,
								'scrollbarPosition': 'outside'
							}
					);
//thisInstance.registerCollapseEvents(widget);
//thisInstance.restoreWidgetState(widget);
					app.event.trigger(Calendar_Calendar_Js.feedsWidgetPostLoadEvent, widget);
				} else {
					console.log("error in response : ", e);
				}
			});
		});
	},
	getCalendarViewContainer: function () {
		if (!Calendar_Calendar_Js.calendarViewContainer.length) {
			Calendar_Calendar_Js.calendarViewContainer = jQuery('#mycalendar');
		}
		return Calendar_Calendar_Js.calendarViewContainer;
	},
	getUserPrefered: function (setting) {
		if (typeof Calendar_Calendar_Js.userPreferenceCache[setting] === 'undefined') {
			Calendar_Calendar_Js.userPreferenceCache[setting] = jQuery('#' + setting).val();
		}
		return Calendar_Calendar_Js.userPreferenceCache[setting];
	},
	transformToEventObject: function (eventData, feedCheckbox) {
		var eventObject = {};
		eventObject.id = eventData._recordId;
		eventObject.title = eventData.subject.display_value;

		eventObject.start = eventData.date_start.calendar_display_value;
		eventObject.end = eventData.due_date.calendar_display_value;

		eventObject.url = 'index.php?module=Calendar&view=Detail&record=' + eventData._recordId;

		var module = feedCheckbox.data('calendarFeed');
		var color = feedCheckbox.data('calendarFeedColor');
		var textColor = feedCheckbox.data('calendarFeedTextcolor');

		eventObject.activitytype = eventData.activitytype.value;
		eventObject.status = eventData.eventstatus.value;
		eventObject.allDay = false;
		eventObject.module = module;

		eventObject.color = color;
		eventObject.textColor = textColor;
		return eventObject;
	},
	updateAgendaListView: function () {
		if (Calendar_Calendar_Js.calendar && Calendar_Calendar_Js.calendar.view.type === 'vtAgendaList') {
			this.renderAgenda();
		}
	},
	updateAllEventsOnCalendar: function () {
		this._updateAllOnCalendar("Events");
		this.updateAgendaListView();
	},
	showEventOnCalendar: function (eventData) {
//method 1
//var feedCheckbox = jQuery('[data-calendar-type="Events_1"]');
//var eventObject = this.transformToEventObject(eventData,feedCheckbox);
//this.getCalendarViewContainer().fullCalendar('renderEvent',eventObject);

//method 2
//var thisInstance = this;
//var eventFeeds = jQuery('[data-calendar-feed="Events"]');
//eventFeeds.each(function(i, eventFeed) {
//thisInstance.refreshFeed(jQuery(eventFeed));
//});

//method 3 - Need to update all events, 
//since support for multiple calendar views for events is enabled
		this.updateAllEventsOnCalendar();
	},
	validateAndSaveEvent: function (modalContainer) {
		var thisInstance = this;
		var params = {
			submitHandler: function (form) {
				jQuery("button[name='saveButton']").attr("disabled", "disabled");
				if (this.numberOfInvalids() > 0) {
					return false;
				}
				var e = jQuery.Event(Vtiger_Edit_Js.recordPresaveEvent);
				app.event.trigger(e);
				if (e.isDefaultPrevented()) {
					return false;
				}
				var formData = jQuery(form).serialize();
				app.helper.showProgress();
				app.request.post({data: formData}).then(function (err, data) {
					app.helper.hideProgress();
					if (!err) {
						jQuery('.vt-notification').remove();
						app.helper.hideModal();
						var message = typeof formData.record !== 'undefined' ? app.vtranslate('JS_EVENT_UPDATED') : app.vtranslate('JS_RECORD_CREATED');
						app.helper.showSuccessNotification({"message": message});
						thisInstance.showEventOnCalendar(data);
					} else {
						app.event.trigger('post.save.failed', err);
						jQuery("button[name='saveButton']").removeAttr('disabled');
					}
				});
			}
		};
		modalContainer.find('form').vtValidate(params);
	},
	registerCreateEventModalEvents: function (modalContainer) {
		this.validateAndSaveEvent(modalContainer);
	},
	setStartDateTime: function (modalContainer, startDateTime) {
		var startDateElement = modalContainer.find('input[name="date_start"]');
		var startTimeElement = modalContainer.find('input[name="time_start"]');
		startDateElement.val(startDateTime.format(vtUtils.getMomentDateFormat()));
		startTimeElement.val(startDateTime.format(vtUtils.getMomentTimeFormat()));
		vtUtils.registerEventForDateFields(startDateElement);
		vtUtils.registerEventForTimeFields(startTimeElement);
		startDateElement.trigger('change');
	},
	showCreateModal: function (moduleName, startDateTime) {
		var isAllowed = jQuery('#is_record_creation_allowed').val();
		if (isAllowed) {
			var thisInstance = this;
			var quickCreateNode = jQuery('#quickCreateModules').find('[data-name="' + moduleName + '"]');
			if (quickCreateNode.length <= 0) {
				app.helper.showAlertNotification({
					'message': app.vtranslate('JS_NO_CREATE_OR_NOT_QUICK_CREATE_ENABLED')
				});
			} else {
				quickCreateNode.trigger('click');
			}

			app.event.one('post.QuickCreateForm.show', function (e, form) {
				thisInstance.performingDayClickOperation = false;
				var modalContainer = form.closest('.modal');
				if (typeof startDateTime !== 'undefined' && startDateTime) {
					thisInstance.setStartDateTime(modalContainer, startDateTime);
				}
				if (moduleName === 'Events') {
					thisInstance.registerCreateEventModalEvents(form.closest('.modal'));
				}
			});
		}
	},
	_updateAllOnCalendar: function (calendarModule) {
		this.refetchEvents();
	},
	showCreateTaskModal: function () {
		this.showCreateModal('Calendar');
	},
	showCreateEventModal: function (startDateTime) {
		this.showCreateModal('Events', startDateTime);
	},
	updateAllTasksOnCalendar: function () {
		this._updateAllOnCalendar("Calendar");
	},
	showTaskOnCalendar: function (data) {
		this.updateAllTasksOnCalendar();
	},
	updateCalendar: function (calendarModule, data) {
		if (calendarModule === 'Events') {
			this.showEventOnCalendar(data);
		} else if (calendarModule === 'Calendar') {
			this.showTaskOnCalendar(data);
		}
	},
	registerPostQuickCreateSaveEvent: function () {
		var thisInstance = this;
		app.event.on("post.QuickCreateForm.save", function (e, data, formData) {
			if (formData.module === 'Calendar' || formData.module === 'Events') {
				thisInstance.updateCalendar(formData.calendarModule, data);
			}
		});
	},
	performingDayClickOperation: false,
	performDayClickAction: function (info) {
		if (!this.performingDayClickOperation) {
			this.performingDayClickOperation = true;
			var date = moment(info.date);
			if (!info.allDay || info.view.type === 'dayGridMonth') {
				this.showCreateEventModal(date);
			} else {
				this.showCreateModal('Calendar', date);
			}
		}
	},
	daysOfWeek: {
		Sunday: 0,
		Monday: 1,
		Tuesday: 2,
		Wednesday: 3,
		Thursday: 4,
		Friday: 5,
		Saturday: 6
	},
	refreshFeed: function (feedCheckbox) {
		if (feedCheckbox.is(':checked')) {
			this.refetchEvents();
		}
	},
	_updateEventOnResize: function (postData, revertFunc) {
		var thisInstance = this;
		app.helper.showProgress();
		app.request.post({'data': postData}).then(function (e, resp) {
			app.helper.hideProgress();
			if (!e) {
				jQuery('.vt-notification').remove();
				if (!resp['ispermitted']) {
					revertFunc();
					app.helper.showErrorNotification({
						'message': app.vtranslate('JS_NO_EDIT_PERMISSION')
					});
				} else if (resp['error']) {
					revertFunc();
				} else {
					if (resp['recurringRecords'] === true) {
						thisInstance.updateAllEventsOnCalendar();
					}
					app.helper.showSuccessNotification({
						'message': app.vtranslate('JS_EVENT_UPDATED')
					});
				}
			} else {
				app.event.trigger('post.save.failed', e);
				thisInstance.updateAllEventsOnCalendar();
			}
		});
	},
	updateEventOnResize: function (info) {
		var thisInstance = this;
		var event = this.eventData(info.event);
		var delta = info.endDelta;
		var revertFunc = info.revert;
		var view = info.view;
		if (event.module !== 'Calendar' && event.module !== 'Events') {
			revertFunc();
			return;
		}

		var postData = {
			'module': app.getModuleName(),
			'action': 'DragDropAjax',
			'mode': 'updateDeltaOnResize',
			'id': event.id,
			'activitytype': event.activitytype,
			'secondsDelta': delta.days * 86400 + Math.round(delta.milliseconds / 1000),
			'view': view.type,
			'userid': event.userid
		};

		if (event.recurringcheck) {
			app.helper.showConfirmationForRepeatEvents().then(function (recurringData) {
				jQuery.extend(postData, recurringData);
				thisInstance._updateEventOnResize(postData, revertFunc);
			});
		} else {
			thisInstance._updateEventOnResize(postData, revertFunc);
		}
	},
	updateEventOnDrop: function (info) {
		var thisInstance = this;
		var event = this.eventData(info.event);
		var delta = info.delta;
		var revertFunc = info.revert;
		var view = info.view;
		if (event.module !== 'Calendar' && event.module !== 'Events') {
			revertFunc();
			return;
		}

		var postData = {
			'module': 'Calendar',
			'action': 'DragDropAjax',
			'mode': 'updateDeltaOnDrop',
			'id': event.id,
			'activitytype': event.activitytype,
			'secondsDelta': delta.days * 86400 + Math.round(delta.milliseconds / 1000),
			'view': view.type,
			'userid': event.userid
		};

		if (event.recurringcheck) {
			app.helper.showConfirmationForRepeatEvents().then(function (recurringData) {
				jQuery.extend(postData, recurringData);
				thisInstance._updateEventOnResize(postData, revertFunc);
			});
		} else {
			thisInstance._updateEventOnResize(postData, revertFunc);
		}
	},
	getActivityTypeClassName: function (activitytype) {
		var className = 'fa fa-calendar';
		switch (activitytype) {
			case 'Meeting' :
				className = 'vicon-meeting';
				break;
			case 'Call' :
				className = 'fa fa-phone';
				break;
			case 'Mobile Call' :
				className = 'fa fa-mobile';
				break;
		}
		return className;
	},
	eventData: function (fcEvent) {
		return jQuery.extend({
			id: fcEvent.id,
			title: fcEvent.title,
			start: fcEvent.start,
			end: fcEvent.end,
			allDay: fcEvent.allDay
		}, fcEvent.extendedProps);
	},
	addActivityTypeIcons: function (event, element) {
		var icon = '<span class="vt-event-icon"><i class="' + this.getActivityTypeClassName(event.activitytype) + '"></i></span>&nbsp;';
		var time = jQuery(element).find('.fc-event-time');
		if (time.length) {
			time.first().prepend(icon);
		} else {
			jQuery(element).find('.fc-event-title').first().prepend(icon);
		}
	},
	_deleteCalendarEvent: function (eventId, sourceModule, extraParams) {
		var thisInstance = this;
		if (typeof extraParams === 'undefined') {
			extraParams = {};
		}
		var params = {
			"module": "Calendar",
			"action": "DeleteAjax",
			"record": eventId,
			"sourceModule": sourceModule
		};
		jQuery.extend(params, extraParams);

		app.helper.showProgress();
		app.request.post({'data': params}).then(function (e, res) {
			app.helper.hideProgress();
			if (!e) {
				var deletedRecords = res['deletedRecords'];
				for (var key in deletedRecords) {
					var eventId = deletedRecords[key];
					thisInstance.refetchEvents();
				}
				app.helper.showSuccessNotification({
					'message': app.vtranslate('JS_RECORD_DELETED')
				});
			} else {
				app.helper.showErrorNotification({
					'message': app.vtranslate('JS_NO_DELETE_PERMISSION')
				});
			}
		});
	},
	deleteCalendarEvent: function (eventId, sourceModule, isRecurring) {
		var thisInstance = this;
		if (isRecurring) {
			app.helper.showConfirmationForRepeatEvents().then(function (postData) {
				thisInstance._deleteCalendarEvent(eventId, sourceModule, postData);
			});
		} else {
			app.helper.showConfirmation({
				message: app.vtranslate('LBL_DELETE_CONFIRMATION')
			}).then(function () {
				thisInstance._deleteCalendarEvent(eventId, sourceModule);
			});
		}
	},
	updateEventOnCalendar: function (eventData) {
		this.updateAllEventsOnCalendar();
	},
	_updateEvent: function (form, extraParams) {
		var formData = jQuery(form).serializeFormData();
		extraParams = extraParams || {};
		jQuery.extend(formData, extraParams);
		app.helper.showProgress();
		app.request.post({data: formData}).then(function (err, data) {
			app.helper.hideProgress();
			if (!err) {
				jQuery('.vt-notification').remove();
				app.helper.showSuccessNotification({"message": ''});
				app.event.trigger("post.QuickCreateForm.save", data, jQuery(form).serializeFormData());
				app.helper.hideModal();
			} else {
				app.event.trigger('post.save.failed', err);
				jQuery("button[name='saveButton']").removeAttr("disabled");
			}
		});
	},
	validateAndUpdateEvent: function (modalContainer, isRecurring) {
		var thisInstance = this;
		var params = {
			submitHandler: function (form) {
				jQuery("button[name='saveButton']").attr("disabled", "disabled");
				if (this.numberOfInvalids() > 0) {
					jQuery("button[name='saveButton']").removeAttr("disabled");
					return false;
				}
				var e = jQuery.Event(Vtiger_Edit_Js.recordPresaveEvent);
				app.event.trigger(e);
				if (e.isDefaultPrevented()) {
					return false;
				}
				if (isRecurring) {
					app.helper.showConfirmationForRepeatEvents().then(function (postData) {
						thisInstance._updateEvent(form, postData);
					});
				} else {
					thisInstance._updateEvent(form);
				}
			}
		};
		modalContainer.find('form').vtValidate(params);
	},
	registerEditEventModalEvents: function (modalContainer, isRecurring) {
		this.validateAndUpdateEvent(modalContainer, isRecurring);
	},
	showEditModal: function (moduleName, record, isRecurring) {
		var thisInstance = this;
		var quickCreateNode = jQuery('#quickCreateModules').find('[data-name="' + moduleName + '"]');
		if (quickCreateNode.length <= 0) {
			app.helper.showAlertNotification({
				'message': app.vtranslate('JS_NO_CREATE_OR_NOT_QUICK_CREATE_ENABLED')
			});
		} else {
			var quickCreateUrl = quickCreateNode.data('url');
			var quickCreateEditUrl = quickCreateUrl + '&mode=edit&record=' + record;
			quickCreateNode.data('url', quickCreateEditUrl);
			quickCreateNode.trigger('click');
			quickCreateNode.data('url', quickCreateUrl);

			if (moduleName === 'Events') {
				app.event.one('post.QuickCreateForm.show', function (e, form) {
					thisInstance.registerEditEventModalEvents(form.closest('.modal'), isRecurring);
				});
			}
		}
	},
	showEditTaskModal: function (taskId) {
		this.showEditModal('Calendar', taskId);
	},
	editCalendarTask: function (taskId) {
		this.showEditTaskModal(taskId);
	},
	showEditEventModal: function (eventId, isRecurring) {
		this.showEditModal('Events', eventId, isRecurring);
	},
	editCalendarEvent: function (eventId, isRecurring) {
		this.showEditEventModal(eventId, isRecurring);
	},
	registerPopoverEvent: function (event, element, calendarView) {
		element = jQuery(element);
		var dateFormat = this.getUserPrefered('date_format');
		dateFormat = dateFormat.toUpperCase();
		var hourFormat = this.getUserPrefered('time_format');
		var timeFormat = 'HH:mm';
		if (hourFormat === '12') {
			timeFormat = 'hh:mm a';
		}

		var generatePopoverContentHTML = function (eventObj) {
			var timeString = '';
			if (eventObj.activitytype === 'Task') {
				timeString = moment(eventObj.start).format(timeFormat);
			} else if (eventObj.module === "Events") {
				if (eventObj.start) {
					timeString = moment(eventObj.start).format(timeFormat);
				}
				if (eventObj.end) {
					timeString += ' - ' + moment(eventObj.end).format(timeFormat);
				}
			} else {
				timeString = moment(eventObj.start).format(dateFormat);
			}
			var sourceModule = eventObj.module;
			if (!sourceModule) {
				sourceModule = 'Calendar';
			}
			var popOverHTML = '' +
			'<span>' +
				timeString +
			'</span>';

			if (sourceModule === 'Calendar' || sourceModule == 'Events') {
				popOverHTML += '' +
						'<span class="float-end cursorPointer" ' +
						'onClick="Calendar_Calendar_Js.deleteCalendarEvent(\'' + eventObj.id +
						'\',\'' + sourceModule + '\',' + eventObj.recurringcheck + ');" title="' + app.vtranslate('JS_DELETE') + '">' +
						'&nbsp;&nbsp;<i class="fa fa-trash"></i>' +
						'</span> &nbsp;&nbsp;';

				if (sourceModule === 'Events') {
					popOverHTML += '' +
							'<span class="float-end cursorPointer" ' +
							'onClick="Calendar_Calendar_Js.editCalendarEvent(\'' + eventObj.id +
							'\',' + eventObj.recurringcheck + ');" title="' + app.vtranslate('JS_EDIT') + '">' +
							'&nbsp;&nbsp;<i class="fa fa-pencil"></i>' +
							'</span>';
				} else if (sourceModule === 'Calendar') {
					popOverHTML += '' +
							'<span class="float-end cursorPointer" ' +
							'onClick="Calendar_Calendar_Js.editCalendarTask(\'' + eventObj.id + '\');" title="' + app.vtranslate('JS_EDIT') + '">' +
							'&nbsp;&nbsp;<i class="fa fa-pencil"></i>' +
							'</span>';
				}

				if (eventObj.status !== 'Held' && eventObj.status !== 'Completed') {
					popOverHTML += '' +
							'<span class="float-end cursorPointer"' +
							'onClick="Calendar_Calendar_Js.markAsHeld(\'' + eventObj.id + '\');" title="' + app.vtranslate('JS_MARK_AS_HELD') + '">' +
							'<i class="fa fa-check"></i>' +
							'</span>';
				} else if (eventObj.status === 'Held') {
					popOverHTML += '' +
							'<span class="float-end cursorPointer" ' +
							'onClick="Calendar_Calendar_Js.holdFollowUp(\'' + eventObj.id + '\');" title="' + app.vtranslate('JS_CREATE_FOLLOW_UP') + '">' +
							'<i class="fa fa-flag"></i>' +
							'</span>';
				}
			}
			return popOverHTML;
		};

		var params = {
			'title': event.title,
			'content': generatePopoverContentHTML(event),
			'trigger': 'hover',
			'closeable': true,
			'placement': 'auto',
			'animation': 'fade'
		};
		if (calendarView.type === 'timeGridDay') {
			params.constrains = 'vertical';
		}
		element.webuiPopover(params);
	},
	performPreEventRenderActions: function (info) {
		if (info.view.type === 'vtAgendaList') {
			return;
		}
		var event = this.eventData(info.event);
		this.addActivityTypeIcons(event, info.el);
		this.registerPopoverEvent(event, info.el, info.view);
	},
	getCalendarHeight: function (view) {
		var portion = 0.86;
		if (typeof view !== 'undefined') {
			if (view === 'AgendaList') {
				portion = 1;
			}
		}
//calendar-height is 86% of window height
		return jQuery(window).height() * portion;
	},
	getDefaultCalendarView: function () {
		var userDefaultActivityView = this.getUserPrefered('activity_view');
		if (userDefaultActivityView === 'Today') {
			userDefaultActivityView = 'timeGridDay';
		} else if (userDefaultActivityView === 'This Week') {
			userDefaultActivityView = 'timeGridWeek';
		} else if (userDefaultActivityView === 'Agenda') {
			userDefaultActivityView = 'vtAgendaList';
		} else {
			userDefaultActivityView = 'dayGridMonth';
		}
		return userDefaultActivityView;
	},
	getDefaultCalendarTimeFormat: function () {
		return {
			hour: 'numeric',
			minute: '2-digit',
			meridiem: 'short',
			hour12: parseInt(this.getUserPrefered('time_format')) !== 24
		};
	},
	getCalendarConfigs: function () {
		var thisInstance = this;
		var timeFormat = thisInstance.getDefaultCalendarTimeFormat();
		var dateFormat = app.getDateFormat();
		// Intl orders day/month by locale: 'en' gives M/D, 'en-gb' gives D/M
		var locale = dateFormat.search("mm") < dateFormat.search("dd") ? 'en' : 'en-gb';
		var gotoDate = function (ev, button) {
			thisInstance.openGotoDate(button);
		};

		return {
			locale: locale,
			initialView: thisInstance.getDefaultCalendarView(),
			headerToolbar: {
				left: 'dayGridMonth,timeGridWeek,timeGridDay,vtAgendaList',
				center: 'title',
				right: 'today prev,gotoDate,next'
			},
			customButtons: {
				gotoDate: {text: '\uf073', click: gotoDate}
			},
			buttonText: {
				today: app.vtranslate('LBL_TODAY'),
				dayGridMonth: app.vtranslate('LBL_MONTH'),
				timeGridWeek: app.vtranslate('LBL_WEEK'),
				timeGridDay: app.vtranslate('LBL_DAY'),
				vtAgendaList: app.vtranslate('LBL_AGENDA')
			},
			views: {
				dayGridMonth: {dayHeaderFormat: {weekday: 'short'}},
				timeGridWeek: {dayHeaderFormat: {weekday: 'short', month: 'numeric', day: 'numeric'}},
				timeGridDay: {dayHeaderFormat: {weekday: 'long', month: 'numeric', day: 'numeric'}},
				vtAgendaList: {
					duration: {days: Calendar_Calendar_Js.numberOfDaysInAgendaView},
					content: function (arg) {
						var start = moment(arg.dateProfile.currentRange.start);
						return {domNodes: [Calendar_Calendar_Js.agendaView.render(start)[0]]};
					}
				}
			},
			height: 'auto',
			fixedWeekCount: false,
			firstDay: thisInstance.daysOfWeek[thisInstance.getUserPrefered('start_day')],
			scrollTime: thisInstance.getUserPrefered('start_hour'),
			slotMinTime: thisInstance.getUserPrefered('start_hour') + ':00',
			editable: true,
			dayMaxEvents: true,
			eventDisplay: 'block',
			eventTimeFormat: timeFormat,
			slotLabelFormat: timeFormat,
			allDayText: app.vtranslate('LBL_ALL_DAY'),
			events: function (info, success, failure) {
				thisInstance.loadEvents(info, success, failure);
			},
			dateClick: function (info) {
				thisInstance.performDayClickAction(info);
			},
			eventResize: function (info) {
				thisInstance.updateEventOnResize(info);
			},
			eventDrop: function (info) {
				thisInstance.updateEventOnDrop(info);
			},
			eventDidMount: function (info) {
				thisInstance.performPreEventRenderActions(info);
			},
			datesSet: function (info) {
				thisInstance.onViewChange(info.view);
			}
		};
	},
	onViewChange: function (view) {
		if (view.type === 'vtAgendaList') {
			jQuery(".sidebar-essentials").addClass("hide");
			jQuery(".content-area").addClass("full-width");
			jQuery(".essentials-toggle").addClass("hide");
			this.renderAgenda();
		} else {
			jQuery(".essentials-toggle").removeClass("hide");
			if (Calendar_Calendar_Js.sideBarEssentialsState === 'show') {
				jQuery(".sidebar-essentials").removeClass("hide");
				jQuery(".content-area").removeClass("full-width");
			} else if (Calendar_Calendar_Js.sideBarEssentialsState === 'hidden') {
				jQuery(".sidebar-essentials").addClass("hide");
				jQuery(".content-area").addClass("full-width");
			}
		}
	},
	fetchAgendaEvents: function (date) {
		var aDeferred = jQuery.Deferred();

		var dateFormat = this.getUserPrefered('date_format');
		dateFormat = dateFormat.toUpperCase();
		var startDate = date.format(dateFormat);

		var requestParams = {
			'module': app.getModuleName(),
			'action': 'FetchAgendaEvents',
			'startDate': startDate,
			'numOfDays': Calendar_Calendar_Js.numberOfDaysInAgendaView
		};

		app.helper.showProgress();
		app.request.post({'data': requestParams}).then(function (e, res) {
			app.helper.hideProgress();
			if (!e) {
				aDeferred.resolve(res);
			} else {
				aDeferred.reject(e);
			}
		});

		return aDeferred.promise();
	},
	fetchEventDetails: function (eventId) {
		var aDeferred = jQuery.Deferred();

		var requestParams = {
			'module': app.getModuleName(),
			'action': 'CalendarActions',
			'mode': 'fetchAgendaViewEventDetails',
			'id': eventId
		};

		app.helper.showProgress();
		app.request.post({'data': requestParams}).then(function (e, res) {
			app.helper.hideProgress();
			if (!e) {
				aDeferred.resolve(res);
			} else {
				aDeferred.reject(e);
			}
		});

		return aDeferred.promise();
	},
	renderAgenda: function () {
		var thisInstance = this;
		var start = moment(Calendar_Calendar_Js.calendar.view.currentStart);
		this.fetchAgendaEvents(start).then(function (agendaEvents) {
			Calendar_Calendar_Js.agendaCache = {start: start.format('YYYY-MM-DD'), events: agendaEvents};
			Calendar_Calendar_Js.agendaView.fill(jQuery('.agendaListView'), agendaEvents);
		});
	},
	registerAgendaListView: function () {
		var thisInstance = this;
		Calendar_Calendar_Js.agendaView = {
			vtDateFormat: thisInstance.getUserPrefered('date_format').toUpperCase(),
			getCourseDay: function (date) {
				var dateFormat = this.vtDateFormat;
				if (moment().format(dateFormat) === date.format(dateFormat)) {
					return app.vtranslate('LBL_TODAY').toUpperCase();
				}
				if (moment().add(1, 'days').format(dateFormat) === date.format(dateFormat)) {
					return app.vtranslate('LBL_TOMORROW').toUpperCase();
				}
				return date.format('LL');
			},
			getWeekDay: function (date) {
				var weekDay = date.format('dddd');
				var label = 'LBL_' + weekDay.toUpperCase();
				return app.vtranslate(label).toUpperCase();
			},
			renderHtml: function (start) {
				var dateFormat = this.vtDateFormat;
				var day = moment(start);
				var skeleton = '<div class="agendaListView">';
				for (var i = 0; i < Calendar_Calendar_Js.numberOfDaysInAgendaView; i++) {
					if (i) {
						day.add(1, 'days');
					}
					skeleton += '' +
							'<div class="agendaListDay" data-date="' + day.format(dateFormat) + '">' +
							'<div class="agendaListViewHeader clearfix">' +
							'<div class="day">' + this.getCourseDay(day) + '</div>' +
							'<div class="weekDay">' + this.getWeekDay(day) + '</div>' +
							'</div>' +
							'<hr>' +
							'<div class="agendaListViewBody">' +
							'</div>' +
							'</div>';
				}
				skeleton += '</div>';
				return skeleton;
			},
			generateEventDetailsHTML: function (res) {
				var html = '<div class="agenda-table-cell"></div>' +
						'<div class="agenda-table-cell"></div>' +
						'<div class="agenda-table-cell">' +
						'<div class="agenda-table details">';
				for (var fieldLabel in res) {
					var eachItem = '<div class="agenda-details">';
					eachItem += '<span class="detailLabel">' + fieldLabel + '</span>';
					eachItem += '<span class="separator"> : </span>';
					eachItem += '<span class="fieldValue">' + String(res[fieldLabel] ?? '').trim() + '</span>';
					eachItem += '</div>';
					html += eachItem;
				}
				html += '</div>' +
						'</div>';
				return html;
			},
			registerToggleMoreDetailsEvent: function (container) {
				var agenda = this;
				container.on('click', '.agenda-more-details', function () {
					var target = jQuery(this);
					var indicator = target.find('i');
					var wrapper = target.closest('.agenda-event-wrapper');
					var eventId = wrapper.data('eventId');
					var details = wrapper.find('.agenda-event-details');
					if (indicator.hasClass('fa-plus-square-o')) {
						if (details.data('isDetailsLoaded')) {
							details.removeClass('hide');
						} else {
							thisInstance.fetchEventDetails(eventId).then(function (res) {
								details.append(agenda.generateEventDetailsHTML(res));
								details.removeClass('hide');
								details.data('isDetailsLoaded', true);
							});
						}
						indicator.removeClass('fa-plus-square-o').addClass('fa-minus-square-o');
					} else {
						details.addClass('hide');
						indicator.removeClass('fa-minus-square-o').addClass('fa-plus-square-o');
					}
				});
			},
			render: function (start) {
				var container = jQuery(this.renderHtml(start));
				var height = thisInstance.getCalendarHeight('AgendaList') + 'px';
				container.css('max-height', height).css('min-height', height);
				this.registerToggleMoreDetailsEvent(container);
				var cache = Calendar_Calendar_Js.agendaCache;
				if (cache && cache.start === start.format('YYYY-MM-DD')) {
					this.fill(container, cache.events);
				}
				return container;
			},
			getAgendaActionsHTML: function (event) {
				var actionsMarkup = '' +
						'<div class="agenda-event-actions verticalAlignMiddle">' +
						'<span class="float-end cursorPointer" ' +
						'onClick="Calendar_Calendar_Js.deleteCalendarEvent(\'' + event.id +
						'\',\'Events\',' + event.recurringcheck + ');" title="' + app.vtranslate('JS_DELETE') + '">' +
						'&nbsp;&nbsp;<i class="fa fa-trash"></i>' +
						'</span>' +
						'<span class="float-end cursorPointer" ' +
						'onClick="Calendar_Calendar_Js.editCalendarEvent(\'' + event.id +
						'\',' + event.recurringcheck + ');" title="' + app.vtranslate('JS_EDIT') + '">' +
						'&nbsp;&nbsp;<i class="fa fa-pencil"></i>' +
						'</span>';

				if (event.status !== 'Held') {
					actionsMarkup += '' +
							'<span class="float-end cursorPointer"' +
							'onClick="Calendar_Calendar_Js.markAsHeld(\'' + event.id + '\');" title="' + app.vtranslate('JS_MARK_AS_HELD') + '">' +
							'&nbsp;&nbsp;<i class="fa fa-check"></i>' +
							'</span>';
				} else if (event.status === 'Held') {
					actionsMarkup += '' +
							'<span class="float-end cursorPointer" ' +
							'onClick="Calendar_Calendar_Js.holdFollowUp(\'' + event.id + '\');" title="' + app.vtranslate('JS_CREATE_FOLLOW_UP') + '">' +
							'&nbsp;&nbsp;<i class="fa fa-flag"></i>' +
							'</span>';
				}
				actionsMarkup += '</div>';
				return actionsMarkup;
			},
			getAgendaEventTitle: function (event) {
				return event.status === 'Held' ?
						'<span><strike>' + event.title + '</strike><span>' :
						'<span>' + event.title + '</span>';
			},
			generateEventHTML: function (event) {
				var html = '' +
						'<div class="agenda-event-wrapper" data-event-id="' + event.id + '">' +
						'<div class="agenda-event-info">' +
						'<div class="agenda-event-time verticalAlignMiddle">' +
						'<div>' + event.startTime + ' - ' + event.endTime + '</div>' +
						'</div>' +
						'<div class="agenda-more-details cursorPointer verticalAlignMiddle">' +
						'<i class="fa fa-plus-square-o" title=' + app.vtranslate('JS_DETAILS') + '></i>' +
						'</div>' +
						'<div class="agenda-event-title verticalAlignMiddle">&nbsp;' +
						'<i class="' + thisInstance.getActivityTypeClassName(event.activitytype) + '" title="' + app.vtranslate(event.activitytype) + '"></i>&nbsp;&nbsp;&nbsp;';
				if (event.recurringcheck) {
					html += '<i class="fa fa-repeat" style="font-size:10px;" title="' + app.vtranslate('JS_RECURRING_EVENT') + '"></i>&nbsp;';
				}
				html += this.getAgendaEventTitle(event) +
						'</div>' +
						'<div class="agenda-event-status verticalAlignMiddle">' + event.status + '</div>' +
						this.getAgendaActionsHTML(event) +
						'</div>' +
						'<div class="agenda-event-details hide verticalAlignMiddle">' +
						'</div>' +
						'</div>';
				return html;
			},
			displayNoEventsMessage: function (container) {
				container.find('.agendaListViewBody').each(function (i, element) {
					var currentList = jQuery(element);
					if (!currentList.find('.agenda-event-wrapper').length) {
						currentList.html(
								'<div class="agendaNoEvents">' +
								app.vtranslate('JS_NO_EVENTS_F0R_THE_DAY') +
								'</div>'
								);
					}
				});
			},
			fill: function (container, agendaEvents) {
				var agenda = this;
				container.find('.agendaListViewBody').empty();
				for (var key in agendaEvents) {
					var containerBody = container.find('[data-date="' + key + '"] .agendaListViewBody');
					jQuery.each(agendaEvents[key], function (i, event) {
						containerBody.append(agenda.generateEventHTML(event));
					});
				}
				this.displayNoEventsMessage(container);
			}
		};
	},
	openGotoDate: function (button) {
		if (!this.gotoPicker) {
			var thisInstance = this;
			var flatpickrFormatMap = {
				'dd-mm-yyyy': 'd-m-Y',
				'mm-dd-yyyy': 'm-d-Y',
				'yyyy-mm-dd': 'Y-m-d'
			};
			// detached input; the calendar toolbar button only positions the popup
			this.gotoPicker = flatpickr(document.createElement('input'), {
				dateFormat: flatpickrFormatMap[thisInstance.getUserPrefered('date_format')] || 'm-d-Y',
				clickOpens: false,
				positionElement: button,
				onChange: function (selectedDates) {
					Calendar_Calendar_Js.calendar.gotoDate(selectedDates[0]);
				}
			});
		}
		this.gotoPicker.set('positionElement', button);
		this.gotoPicker.open();
	},
	initializeCalendar: function () {
		this.registerAgendaListView();
		Calendar_Calendar_Js.calendar = new FullCalendar.Calendar(this.getCalendarViewContainer()[0], this.getCalendarConfigs());
		Calendar_Calendar_Js.calendar.render();
		this.performSidebarEssentialsRecognition();
	},
	performSidebarEssentialsRecognition: function () {
		app.event.on("Vtiger.Post.MenuToggle", function () {
			var essentialsHidden = jQuery(".sidebar-essentials").hasClass("hide");
			if (essentialsHidden) {
				Calendar_Calendar_Js.sideBarEssentialsState = 'hidden';
			} else {
				Calendar_Calendar_Js.sideBarEssentialsState = 'show';
			}
		});
		var essentialsHidden = jQuery(".sidebar-essentials").hasClass("hide");
		if (essentialsHidden) {
			Calendar_Calendar_Js.sideBarEssentialsState = 'hidden';
		} else {
			Calendar_Calendar_Js.sideBarEssentialsState = 'show';
		}
	},
	registerEvents: function () {
		this._super();
		this.initializeCalendar();
		this.registerWidgetPostLoadEvent();
		this.initializeWidgets();
		this.registerPostQuickCreateSaveEvent();
	}
});

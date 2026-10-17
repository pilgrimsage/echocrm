/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.1
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is: vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/

var vtUtils = {

        weekDaysArray : {Sunday : 0,Monday : 1, Tuesday : 2, Wednesday : 3,Thursday : 4, Friday : 5, Saturday : 6},

    /**
	 * Function which will show the select2 element for select boxes . This will use select2 library (v4).
	 * The params are select2 4 options; data-* attributes of the element are merged in as options too.
	 */
	showSelect2ElementView : function(selectElement, params) {
		if(selectElement.length == 0) {
			return selectElement;
		}
		if(typeof params == 'undefined') {
			params = {};
		}

		var data = selectElement.data();
		if(data != null) {
			// jQuery keeps select2's own instance on the element; it is not an option
			data = jQuery.extend({}, data);
			delete data.select2;
			delete data.select2Id;
			params = jQuery.extend(data,params);
		}
    if(jQuery('#minilistWizardContainer').length){params.maximumSelectionLength=4};
        // Fix to eliminate flicker happening on list view load
        var ele = jQuery(selectElement);
        if(ele.hasClass("listSearchContributor")){
            ele.closest(".select2_search_div").find(".select2_input_element").remove();
            ele.show();
        }

		// Sort DOM nodes alphabetically in select box.
		if (typeof params['customSortOptGroup'] != 'undefined' && params['customSortOptGroup']) {
			jQuery('optgroup', selectElement).each(function(){
				var optgroup = jQuery(this);
				var options  = optgroup.children().toArray().sort(function(a, b){
					var aText = jQuery(a).text();
					var bText = jQuery(b).text();
					return aText < bText ? 1 : -1;
				});
				jQuery.each(options, function(i, v){
					optgroup.prepend(v);
				});
			});
			delete params['customSortOptGroup'];
		}

		//The "you can select only N items" message when maximumSelectionLength is set
		if(typeof params.maximumSelectionLength != "undefined") {
			params.language = jQuery.extend({
				maximumSelected : function(args) {
					return app.vtranslate('JS_YOU_CAN_SELECT_ONLY')+' '+args.maximum+' '+app.vtranslate('JS_ITEMS');
				}
			}, params.language || {});
		}
		// a multi select stays open while picking several values
		if(selectElement.prop('multiple') && typeof params.closeOnSelect == 'undefined') {
			params.closeOnSelect = false;
		}
		// the classes of an option (the picklist colours) are put on its row in the dropdown
		if(typeof params.templateResult == 'undefined') {
			params.templateResult = function(data, container) {
				if(data.element && data.element.className) {
					jQuery(container).addClass(data.element.className);
				}
				return data.text;
			};
		}
		// BS5 modals keep keyboard focus inside themselves, so the dropdown (and its search box) must live in the modal
		if(typeof params.dropdownParent == 'undefined') {
			var modalParent = selectElement.closest('.modal');
			if(modalParent.length) {
				params.dropdownParent = modalParent;
			}
		}
		// select2 4 follows the element's width, not its min-width (a few templates size a select that way)
		var minWidth = parseInt(selectElement.css('min-width'), 10);
		if(typeof params.width == 'undefined' && minWidth > 0) {
			params.width = minWidth + 'px';
		}
		// shown while its row is still hidden, select2 cannot measure the element: it then fills its parent
		if(typeof params.width == 'undefined' && !selectElement[0].offsetWidth) {
			params.width = '100%';
		}
        selectElement.select2(params);
		//validator should not validate select2 text inputs
		var select2Container = selectElement.data('select2').$container;
		select2Container.find('.select2-search__field').addClass('ignore-validation');
		selectElement.on('select2:open', function(e) {
			jQuery('.select2-search__field').addClass('ignore-validation');
		});
		return selectElement;
	},

    /**
	 * A multiple <select> the user can type values into freely (select2 tags), with a list of suggestions.
	 * It carries data-tag-input so the scripts that read a filter can tell it from an ordinary select.
	 * @param name, classes, id - attributes of the new select
	 * @param suggestions - array of values offered in the dropdown
	 * @param selectedCsv - comma separated values that are chosen to start with
	 */
	buildTagSelect : function(name, classes, suggestions, selectedCsv, id) {
		var select = jQuery('<select multiple data-tag-input="true"></select>').attr('name', name).addClass(classes);
		if(id) {
			select.attr('id', id);
		}
		var selected = [];
		jQuery.each(String(selectedCsv ?? '').split(','), function(i, value) {
			value = value.trim();
			if(value !== '') {
				selected.push(value);
			}
		});
		var added = {};
		var addOption = function(value) {
			if(added[value]) {
				return;
			}
			added[value] = true;
			select.append(new Option(value, value, false, selected.indexOf(value) !== -1));
		};
		jQuery.each(suggestions || [], function(i, value) {
			addOption(String(value));
		});
		jQuery.each(selected, function(i, value) {
			addOption(value);
		});
		return select;
	},

    /**
	 * The data (id, text, element...) behind one chosen value of a select2, from its <li class="select2-selection__choice">.
	 * Select2 4 keeps it in a private cache, reachable through its utils module.
	 */
	getSelect2ChoiceData : function(choiceElement) {
		var element = choiceElement instanceof jQuery ? choiceElement[0] : choiceElement;
		return jQuery.fn.select2.amd.require('select2/utils').GetData(element, 'data');
	},

    /**
	 * Lets the user drag the chosen values of a multiple select2 into an order.
	 * Select2 4 draws the choices in option order and redraws them on every change, so the order the user wants
	 * is remembered here and applied again after each redraw; read it back with getSelect2OrderedValues().
	 * @param selectElement - the multiple <select>, already shown with showSelect2ElementView
	 * @param savedOrder - optional array of values in the order they were saved
	 */
	makeSelect2Sortable : function(selectElement, savedOrder) {
		var instance = selectElement.data('select2');
		if(!instance) {
			return;
		}
		var rendered = instance.$container.find('ul.select2-selection__rendered');
		var order = jQuery.map(savedOrder || [], String);
		var choices = function() {
			return rendered.children('li.select2-selection__choice').toArray();
		};
		var idOf = function(li) {
			var data = vtUtils.getSelect2ChoiceData(li);
			return data ? String(data.id) : null;
		};
		var apply = function() {
			var lis = choices();
			var present = lis.map(idOf);
			order = order.filter(function(id) { return present.indexOf(id) !== -1; });
			present.forEach(function(id) {
				if(order.indexOf(id) === -1) {
					order.push(id);
				}
			});
			var searchField = rendered.children('li.select2-search--inline');
			order.forEach(function(id) {
				var li = lis.filter(function(item) { return idOf(item) === id; })[0];
				if(li) {
					searchField.before(li);
				}
			});
		};
		instance.on('selection:update', apply);
		// pressing on a choice must start a drag, not open the dropdown
		rendered.on('mousedown', 'li.select2-selection__choice', function(e) {
			e.stopPropagation();
		});
		rendered.sortable({
			items : 'li.select2-selection__choice',
			containment : 'parent',
			tolerance : 'pointer',
			stop : function() {
				order = choices().map(idOf);
				selectElement.trigger('sortupdate.select2order');
			}
		});
		apply();
	},

	/**
	 * Values of a multiple select2 in the order the user arranged them (see makeSelect2Sortable)
	 */
	getSelect2OrderedValues : function(selectElement) {
		var instance = selectElement.data('select2');
		if(!instance) {
			return selectElement.val() || [];
		}
		return instance.$container.find('li.select2-selection__choice').map(function() {
			var data = vtUtils.getSelect2ChoiceData(this);
			return data ? String(data.id) : null;
		}).get();
	},

    /**
     * Range picker: "start,end" in the user's date format in the input, as the old plugin wrote it
     */
    registerDateRangeField : function(element, userDateFormat) {
        var formatMap = {'dd-mm-yyyy': 'd-m-Y', 'mm-dd-yyyy': 'm-d-Y', 'yyyy-mm-dd': 'Y-m-d'};
        var days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        var firstDay = Math.max(0, days.indexOf(String(jQuery('#start_day').val() || '').toLowerCase()));
        var setRange = function(instance, from, to) {
            instance.setDate([from, to], true);
            instance.close();
        };
        flatpickr(element[0], {
            mode: 'range',
            rangeSeparator: ',',
            dateFormat: formatMap[userDateFormat] || 'm-d-Y',
            locale: {firstDayOfWeek: firstDay, rangeSeparator: ','},
            onChange: function(selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    jQuery(instance.input).trigger('datepicker-change');
                }
            },
            onReady: function(selectedDates, dateStr, instance) {
                var footer = document.createElement('div');
                footer.className = 'flatpickr-footer';
                [['Today', 0, 0], ['Last 7 days', -6, 0], ['Last 30 days', -29, 0]].forEach(function(item) {
                    var link = document.createElement('a');
                    link.textContent = item[0];
                    link.href = 'javascript:void(0)';
                    link.onclick = function() {
                        var from = new Date(); from.setDate(from.getDate() + item[1]);
                        setRange(instance, from, new Date());
                    };
                    footer.appendChild(link);
                });
                instance.calendarContainer.appendChild(footer);
            }
        });
    },

    /**
     * Function register datepicker for dateField elements
     * @param {jQuery} parent
     */
    registerEventForDateFields : function(parent, params) {
        var element;
		if (parent.hasClass('dateField') && !parent.hasClass('ignore-ui-registration')) {
            element = parent;
        } else {
            element = jQuery('.dateField:not(ignore-ui-registration)', parent);
        }

		if(typeof params == 'undefined') {
			params = {};
		}

        var parentDateElement = element.parent();
        jQuery('.input-group-addon',parentDateElement).on('click',function(e){
            var elem = jQuery(e.currentTarget);
            elem.parent().find('.dateField').focus();
        });

        var userDateFormat = app.getDateFormat();
        var calendarType = element.data('calendarType');
        if(element.length > 0){
            jQuery(element).each(function(index, Elem){
                element = jQuery(Elem);
                 if(calendarType == "range"){
                    vtUtils.registerDateRangeField(element, userDateFormat);
                }else{
                    var elementDateFormat = element.data('dateFormat');
                    if(typeof elementDateFormat !== 'undefined') {
                        userDateFormat = elementDateFormat;
                    }
                    var flatpickrFormatMap = {
                        'dd-mm-yyyy': 'd-m-Y',
                        'mm-dd-yyyy': 'm-d-Y',
                        'yyyy-mm-dd': 'Y-m-d'
                    };
                    var defaultPickerParams = {
                        dateFormat: flatpickrFormatMap[userDateFormat] || 'm-d-Y',
                        allowInput: true,
                        onChange: function(selectedDates, dateStr, instance){
                            // the pages listen for this event (list search, report conditions)
                            jQuery(instance.input).trigger('datepicker-change');
                        },
                        onReady: function(selectedDates, dateStr, instance){
                            var todayBtn = document.createElement('a');
                            todayBtn.className = 'flatpickr-today-btn';
                            todayBtn.textContent = 'Today';
                            todayBtn.href = 'javascript:void(0)';
                            todayBtn.onclick = function(){ instance.setDate(new Date(), true); };

                            var clearBtn = document.createElement('a');
                            clearBtn.className = 'flatpickr-clear-btn';
                            clearBtn.textContent = 'Clear';
                            clearBtn.href = 'javascript:void(0)';
                            clearBtn.onclick = function(){ instance.clear(); instance.close(); };

                            var footer = document.createElement('div');
                            footer.className = 'flatpickr-footer';
                            footer.appendChild(todayBtn);
                            footer.appendChild(clearBtn);
                            instance.calendarContainer.appendChild(footer);
                        }
                    };
                    jQuery.extend(defaultPickerParams, params);
                    element.each(function(index, el){
                        flatpickr(el, defaultPickerParams);
                    });

					if(element.hasClass('input-daterange')){
						element = element.find('input');
					}
                }
            });
        }
    },

    /**
	 * Function which will register time fields
	 * @params : container - jquery object which contains time fields with class timepicker-default or itself can be time field
	 *			 registerForAddon - boolean value to register the event for Addon or not
	 *			 params  - params for the  plugin
	 * @return : container to support chaining
	 */
	registerEventForTimeFields : function(container, registerForAddon, params) {
		if(typeof container === 'undefined') {
			container = jQuery('body');
		}
		if(typeof registerForAddon === 'undefined'){
			registerForAddon = true;
		}

		container = jQuery(container);

		if (container.hasClass('timepicker-default')) {
            var element = container;
        } else {
            var element = container.find('.timepicker-default');
        }

		if(registerForAddon === true){
			var parentTimeElem = element.closest('.time');
			jQuery('.input-group-addon',parentTimeElem).on('click',function(e){
				var elem = jQuery(e.currentTarget);
				elem.closest('.time').find('.timepicker-default').focus();
			});
		}

		if(typeof params === 'undefined') {
			params = {};
		}

		var timeFormat = element.data('format');
		if(timeFormat == '24') {
			timeFormat = 'H:i';
		} else {
			timeFormat = 'h:i A';
		}
		var defaultsTimePickerParams = {
			'timeFormat' : timeFormat,
			'className'  : 'timePicker'
		};
		var params = jQuery.extend(defaultsTimePickerParams, params);

        if(element.length) {
            element.timepicker(params);
        }

		return container;
	},

    /**
     * Function to change view of edited elements related to selected Plugin
     * @param {type} elementsContainer
     * @returns {undefined}
     */
    applyFieldElementsView : function(container){
        this.showSelect2ElementView(container.find('select.select2'));
        this.registerEventForDateFields(container.find('.dateField').not('.ignore-ui-registration'));
        this.registerEventForTimeFields(container.find('.timepicker-default'));
    },

    showQtip : function(element,message,customParams) {
        if(typeof customParams === 'undefined') {
            customParams = {};
        }
        var qtipParams =  {
            content: {
                text: message
            },
            show: {
                event: 'Vtiger.Qtip.ShowMesssage'
            },
            hide: {
                event: 'Vtiger.Qtip.HideMesssage'
            }
        };
        jQuery.extend(qtipParams,customParams);

        element.qtip(qtipParams);
        element.trigger('Vtiger.Qtip.ShowMesssage');
    },

    hideQtip : function(element) {
        element.trigger('Vtiger.Qtip.HideMesssage');
    },

	linkifyStr : function(str) {
		var options = {'TLDs':267};
		return anchorme.js(str,options);
	},

	htmlSubstring : function(content, maxlength) {
		var m, r = /<([^>\s]*)[^>]*>/g,
			stack = [],
			lasti = 0,
			result = '';

		//for each tag, while we don't have enough characters
		while ((m = r.exec(content)) && maxlength) {
			//get the text substring between the last tag and this one
			var temp = content.substring(lasti, m.index).substr(0, maxlength);
			//append to the result and count the number of characters added
			result += temp;
			maxlength -= temp.length;
			lasti = r.lastIndex;

			if (content) {
				result += m[0];
				if (m[1].indexOf('/') === 0) {
					//if this is a closing tag, then pop the stack (does not account for bad html)
					stack.pop();
				} else if (m[1].lastIndexOf('/') !== m[1].length - 1) {
					//if this is not a self closing tag then push it in the stack
					stack.push(m[1]);
				}
			}
		}

		//add the remainder of the string, if needed (there are no more tags in here)
		result += content.substr(lasti, maxlength);

		//fix the unclosed tags
		while (stack.length) {
			var unclosedtag = stack.pop();
			if(jQuery.inArray(unclosedtag,['br']) == -1){
				result += '</' + unclosedtag + '>';
			}
		}
		return result;
	},

    showValidationMessage : function(element,message,params) {
        if(element.hasClass('select2')) {
            element = app.helper.getSelect2FromSelect(element);
        }

        if(typeof params === 'undefined') {
            params = {};
        }

        var validationTooltipParams = {
            position: {
                my: 'bottom left',
                at: 'top left'
            },
            style: {
                classes: 'qtip-red qtip-shadow'
            }
        };

        jQuery.extend(validationTooltipParams,params);
        this.showQtip(element,message,validationTooltipParams);
        element.addClass('input-error');
    },

    hideValidationMessage : function(element) {
        if(element.hasClass('select2')) {
            element = app.helper.getSelect2FromSelect(element);
        }
        //should hide even message displyed by vtValidate
        element.trigger('Vtiger.Validation.Hide.Messsage');
        this.hideQtip(element);
        element.removeClass('input-error');
    },

    getMomentDateFormat : function() {
        var dateFormat = app.getDateFormat();
        return dateFormat.toUpperCase();
    },

    getMomentTimeFormat : function() {
        var hourFormat = app.getHourFormat();
        var timeFormat = 'HH:mm';
        if(hourFormat === 12) {
            timeFormat = 'hh:mm A';
        }
        return timeFormat;
    },

    getMomentCompatibleDateTimeFormat : function() {
        return this.getMomentDateFormat() + ' ' + this.getMomentTimeFormat();
    },

    convertFileSizeInToDisplayFormat : function(fileSizeInBytes) {
		 var i = -1;
		var byteUnits = [' kB', ' MB', ' GB', ' TB', 'PB', 'EB', 'ZB', 'YB'];
		do {
			fileSizeInBytes = fileSizeInBytes / 1024;
			i++;
		} while (fileSizeInBytes > 1024);

		return Math.max(fileSizeInBytes, 0.1).toFixed(1) + byteUnits[i];

	},

    enableTooltips : function(options) {
		if(typeof options == 'undefined') {
			options = {};
		}

        jQuery(function () {
            jQuery('[data-bs-toggle="tooltip"]').each(function(){
                bootstrap.Tooltip.getOrCreateInstance(this, options);
            });
        });
    },
    
    stripTags : function(string,allowed) {
        //https://stackoverflow.com/questions/5601903/jquery-almost-equivalent-of-phps-strip-tags#answer-46483672
        allowed = (((allowed || '') + '').toLowerCase().match(/<[a-z][a-z0-9]*>/g) || []).join('');
        var tags = /<\/?([a-z][a-z0-9]*)\b[^>]*>/gi;
        return string.replace(tags, function ($0, $1) {
            return allowed.indexOf('<' + $1.toLowerCase() + '>') > -1 ? $0 : '';
        });
    },
	
    addMask: function (container) {
            if (container.length && jQuery('#vt-mask').length == 0) {
                    var mask = '<div id="vt-mask" class="vt-page-mask" ></div>'
                    container.before(mask);
            }
    },

    removeMask: function () {
            if (jQuery('#vt-mask').length) {
                    jQuery('#vt-mask').remove();
            }
    },
        
    isPasswordStrong : function(password) {
            /*
            * ^					The password string will start this way
            * (?=.*[a-z])			The string must contain at least 1 lowercase alphabetical character
            * (?=.*[A-Z])			The string must contain at least 1 uppercase alphabetical character
            * (?=.*[0-9])			The string must contain at least 1 numeric character
            * (?=.*[!@#\$%\^&\*])	The string must contain at least one special character, but we are escaping reserved RegEx characters to avoid conflict
            * (?=.{8,})			The string must be eight characters or longer
            */
           var password_regex = jQuery('[name="pwd_regex"]').val();
           if((typeof password_regex != 'undefined') && (password_regex != '')){
                var strongPasswordRegex = new RegExp(password_regex);
                var isStrong = strongPasswordRegex.test(password)? true : false; 
                return isStrong;
           }
	   // If password regex is not set - consider it as strong.
           return true;
    },
}

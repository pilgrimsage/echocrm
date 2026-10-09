/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is: vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

Inventory_Edit_Js("SalesOrder_Edit_Js",{},{
    
    
    /**
	 * Function to get popup params
	 */
	getPopUpParams : function(container) {
		var params = this._super(container);
        var sourceFieldElement = jQuery('input[class="sourceField"]',container);
		if(!sourceFieldElement.length) {
			sourceFieldElement = jQuery('input.sourceField',container);
		}

		if(sourceFieldElement.attr('name') == 'contact_id' || sourceFieldElement.attr('name') == 'potential_id') {
			var form = this.getForm();
			var parentIdElement  = form.find('[name="account_id"]');
			if(parentIdElement.length > 0 && parentIdElement.val().length > 0 && parentIdElement.val() != 0) {
				var closestContainer = parentIdElement.closest('td');
				params['related_parent_id'] = parentIdElement.val();
				params['related_parent_module'] = closestContainer.find('[name="popupReferenceModule"]').val();
			} else if(sourceFieldElement.attr('name') == 'potential_id') {
				parentIdElement  = form.find('[name="contact_id"]');
				if(parentIdElement.length > 0 && parentIdElement.val().length > 0) {
					closestContainer = parentIdElement.closest('td');
					params['related_parent_id'] = parentIdElement.val();
					params['related_parent_module'] = closestContainer.find('[name="popupReferenceModule"]').val();
				}
			}
        }
        return params;
    },
    
    /**
	 * A credit note is issued to a customer against an invoice; a debit note to a vendor against a
	 * purchase order. Show only the fields of the chosen type and require the ones that apply.
	 */
	registerNoteTypeEvents : function() {
		var form = this.getForm();
		var typeField = form.find('[name="note_type"]');
		if (!typeField.length) {
			return;
		}
		var fieldsByType = {
			'Credit Note' : ['account_id', 'invoice_id'],
			'Debit Note' : ['vendor_id', 'purchaseorder_id']
		};
		var required = {'Credit Note' : ['account_id', 'invoice_id'], 'Debit Note' : ['vendor_id']};
		var cellOf = function(name) {
			var field = form.find('[name="' + name + '"]').first();
			return field.closest('.fieldValue').add(field.closest('.fieldValue').prev('.fieldLabel'));
		};
		var apply = function() {
			var selected = typeField.val() || 'Credit Note';
			jQuery.each(fieldsByType, function(type, names) {
				jQuery.each(names, function(index, name) {
					var show = (type === selected);
					cellOf(name).toggle(show);
					var input = form.find('[name="' + name + '"]');
					if (show && jQuery.inArray(name, required[type]) !== -1) {
						input.removeClass('ignore-validation').attr('data-rule-required', 'true');
					} else {
						input.addClass('ignore-validation').removeAttr('data-rule-required');
					}
				});
			});
		};
		typeField.on('change', apply);
		apply();
	},

    /**
	 * Function to search module names
	 */
	searchModuleNames : function(params) {
        var aDeferred = jQuery.Deferred();
		if(typeof params.module == 'undefined') {
			params.module = app.getModuleName();
		}
		if(typeof params.action == 'undefined') {
			params.action = 'BasicAjax';
		}
		
		if(typeof params.base_record == 'undefined') {
			var record = jQuery('[name="record"]');
			var recordId = app.getRecordId();
			if(record.length) {
				params.base_record = record.val();
			} else if(recordId) {
				params.base_record = recordId;
			} else if(app.view() == 'List') {
				var editRecordId = jQuery('#listview-table').find('tr.listViewEntries.edited').data('id');
				if(editRecordId) {
					params.base_record = editRecordId;
				}
			}
		}
        
        // Added for overlay edit as the module is different
        if(params.search_module == 'Products' || params.search_module == 'Services') {
            params.module = 'SalesOrder';
        }

		app.request.get({'data':params}).then(
			function(error, data){
                if(error == null) {
                    aDeferred.resolve(data);
                }
			},
			function(error){
				aDeferred.reject();
			}
		)
		return aDeferred.promise();
    },
    
    /**
	 * Function which will register event for Reference Fields Selection
	 */
	registerReferenceSelectionEvent : function(container) {
		this._super(container);
		var self = this;
		
		jQuery('input[name="account_id"]', container).on(Vtiger_Edit_Js.referenceSelectionEvent, function(e, data){
			self.referenceSelectionEventHandler(data, container);
		});
	},
        registerBasicEvents: function(container){
            this._super(container);
            this.registerNoteTypeEvents();
            this.registerForTogglingBillingandShippingAddress();
            this.registerEventForCopyAddress();
        },
    
});
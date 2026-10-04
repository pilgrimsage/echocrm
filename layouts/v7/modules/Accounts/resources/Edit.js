/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is: vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

Vtiger_Edit_Js("Accounts_Edit_Js",{
   
},{
   
	//This will store the editview form
	editViewForm : false,
   
	//Address field mapping within module
	addressFieldsMappingInModule : {
										'bill_street':'ship_street',
										'bill_pobox':'ship_pobox',
										'bill_city'	:'ship_city',
										'bill_state':'ship_state',
										'bill_code'	:'ship_code',
										'bill_country':'ship_country'
								},
   
   // mapping address fields of MemberOf field in the module              
   memberOfAddressFieldsMapping : {
                                        'bill_street':'bill_street',
										'bill_pobox':'bill_pobox',
										'bill_city'	:'bill_city',
										'bill_state':'bill_state',
										'bill_code'	:'bill_code',
										'bill_country':'bill_country',
                                        'ship_street' : 'ship_street',        
                                        'ship_pobox' : 'ship_pobox',
                                        'ship_city':'ship_city',
                                        'ship_state':'ship_state',
                                        'ship_code':'ship_code',
                                        'ship_country':'ship_country'
                                   },                          
	/**
	 * Function to swap array
	 * @param Array that need to be swapped
	 */ 
	swapObject : function(objectToSwap){
		var swappedArray = {};
		var newKey,newValue;
		for(var key in objectToSwap){
			newKey = objectToSwap[key];
			newValue = key;
			swappedArray[newKey] = newValue;
		}
		return swappedArray;
	},
	
	/**
	 * Function to copy address between fields
	 * @param strings which accepts value as either odd or even
	 */
	copyAddress : function(swapMode, container){
		var thisInstance = this;
		var addressMapping = this.addressFieldsMappingInModule;
		if(swapMode == "false"){
			for(var key in addressMapping) {
				var fromElement = container.find('[name="'+key+'"]');
				var toElement = container.find('[name="'+addressMapping[key]+'"]');
				toElement.val(fromElement.val());
			}
		} else if(swapMode){
			var swappedArray = thisInstance.swapObject(addressMapping);
			for(var key in swappedArray) {
				var fromElement = container.find('[name="'+key+'"]');
				var toElement = container.find('[name="'+swappedArray[key]+'"]');
				toElement.val(fromElement.val());
			}
		}
	},
	
	/**
	 * Function to register event for copying address between two fileds
	 */
	registerEventForCopyingAddress : function(container){
		var thisInstance = this;
		var swapMode;
		jQuery('[name="copyAddress"]').on('click',function(e){
			var element = jQuery(e.currentTarget);
			var target = element.data('target');
			if(target == "billing"){
				swapMode = "false";
			}else if(target == "shipping"){
				swapMode = "true";
			}
			thisInstance.copyAddress(swapMode, container);
		})
	},
	
	/**
	 * Function which will copy the address details - without Confirmation
	 */
	copyAddressDetails : function(data, container) {
		var thisInstance = this;
		thisInstance.getRecordDetails(data).then(
			function(data){
				var response = data['result'];
				thisInstance.mapAddressDetails(thisInstance.memberOfAddressFieldsMapping, response['data'], container);
			},
			function(error, err){

			});
	},
	
	/**
	 * Function which will map the address details of the selected record
	 */
	mapAddressDetails : function(addressDetails, result, container) {
		for(var key in addressDetails) {
			// While Quick Creat we don't have address fields, we should  add
            if(container.find('[name="'+key+'"]').length == 0) { 
                   container.append("<input type='hidden' name='"+key+"'>"); 
            } 
			container.find('[name="'+key+'"]').val(result[addressDetails[key]]);
			container.find('[name="'+key+'"]').trigger('change');
			container.find('[name="'+addressDetails[key]+'"]').val(result[addressDetails[key]]);
			container.find('[name="'+addressDetails[key]+'"]').trigger('change');
		}
	},
	
	/**
	 * Function which will register basic events which will be used in quick create as well
	 *
	 */
	/**
	 * GSTIN rules, mirroring Vtiger_GST_Utils (include/utils/GSTUtils.php), which stays the
	 * authority on save: 15 characters (state code, PAN, entity number, 'Z', check character)
	 * and a base-36 check character. Returns an error message, or '' when the value is fine.
	 */
	gstinProblem : function(value) {
		var gstin = (value || '').replace(/\s+/g, '').toUpperCase();
		if (gstin === '') {
			return '';
		}
		var match = /^(\d{2})[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/.exec(gstin);
		var stateCode = match ? parseInt(match[1], 10) : 0;
		if (!match || !((stateCode >= 1 && stateCode <= 38 && stateCode !== 25 && stateCode !== 28) || stateCode === 97)) {
			return 'Invalid GSTIN. It must be 15 characters: a 2-digit state code, the 10-character PAN, an entity number, the letter Z and a check character (for example 27AAPFU0939F1ZV).';
		}
		var alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		var sum = 0;
		for (var i = 0; i < 14; i++) {
			var product = alphabet.indexOf(gstin.charAt(i)) * (i % 2 === 0 ? 1 : 2);
			sum += Math.floor(product / 36) + (product % 36);
		}
		if (gstin.charAt(14) !== alphabet.charAt((36 - (sum % 36)) % 36)) {
			return 'Invalid GSTIN: the check character (last character) does not match. Please re-check the number for a typing mistake.';
		}
		return '';
	},

	/**
	 * Upper-cases and trims the GSTIN as it is typed, and refuses to save an invalid one with a
	 * message beside the field (the server rejects it too, but only with a bare error response).
	 */
	registerGstinValidation : function(container) {
		var thisInstance = this;
		var field = container.find('[name="gstin"]');
		if (field.length === 0) {
			return;
		}
		field.on('blur', function() {
			field.val(field.val().replace(/\s+/g, '').toUpperCase());
		});
		field.on('input', function() {
			vtUtils.hideValidationMessage(field);
		});
		app.event.on(Vtiger_Edit_Js.recordPresaveEvent, function(e) {
			var problem = thisInstance.gstinProblem(field.val());
			if (problem !== '') {
				vtUtils.showValidationMessage(field, problem);
				field.focus();
				e.preventDefault();
			}
		});
	},

	registerBasicEvents : function(container) {
		this._super(container);
		this.registerEventForCopyingAddress(container);
		this.registerGstinValidation(container);
	}
});
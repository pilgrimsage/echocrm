/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is: vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

jQuery.Class("Vtiger_CustomView_Js",{
},{
	contianer : false,

	advanceFilterInstance : false,

	columnSelectElement : false,

	reIntialize : function () {
		this.contianer = false;
		this.advanceFilterInstance = false;
		this.columnSelectElement = false;
	},

	getContainer : function() {
		if(this.container == false) {
			this.container = jQuery('#filterContainer');
		}
		return this.container
	},

	/**
	 * Function to get the view columns selection element
	 * @return : jQuery object of view columns selection element
	 */
	getColumnSelectElement : function() {
		if(this.columnSelectElement == false) {
			this.columnSelectElement = jQuery('#viewColumnsSelect');
		}
		return this.columnSelectElement;
	},

	/**
	 * Function to make the chosen columns draggable into the order they will be listed in; the order the
	 * filter was saved with (the hidden columnslist value) is applied first
	 */
	makeColumnListSortable : function() {
		var savedOrder = [];
		try {
			savedOrder = JSON.parse(jQuery('input[name="columnslist"]', this.getContainer()).val()) || [];
		} catch(e) {}
		vtUtils.makeSelect2Sortable(this.getColumnSelectElement(), savedOrder);
	},

	/**
	 * Function which will get the selected columns with order preserved
	 * @return : array of selected values in order
	 */
	getSelectedColumns : function() {
		return vtUtils.getSelect2OrderedValues(this.getColumnSelectElement());
	},

	doOperation : function (url) {
		var aDeferred = new jQuery.Deferred();
		app.helper.showProgress();
		app.request.get({'url':url}).then(function(error,data){
			app.helper.hideProgress();
			aDeferred.resolve(data);
		});

		return aDeferred.promise();
	}, 

	showCreateFilter : function(data){
		var self = this;
		self.reIntialize();
		app.helper.loadPageContentOverlay(data).then(function(data){
			data.find('.data').css('height','100%');
			var Options= {
			autoExpandScrollbar: true,
			scrollInertia: 200,
			autoHideScrollbar: true,

			mouseWheel: {
				enable: true,
				preventDefault: true,
				scrollAmount: 50
			}
		};
			app.helper.showVerticalScroll(jQuery('.customview-content '), Options);
			self.advanceFilterInstance = new Vtiger_AdvanceFilter_Js(data.find('.filterConditionsDiv'));
			self.registerFilterCreateEvents();
		});
	},

	saveFilter : function() {
		var aDeferred = jQuery.Deferred();
		var formElement = jQuery("#CustomView");
		var formData = formElement.serializeFormData();
        
		app.helper.showProgress();

		app.request.post({'data':formData}).then(
			function(error,data){
               if(error === null){
				app.helper.hideProgress();
				window.onbeforeunload = null;
				aDeferred.resolve(data);
				}
				else{
					app.helper.hideProgress();
					aDeferred.reject();
					app.helper.showErrorNotification({'message': app.vtranslate('JS_VIEW_ALREADY_EXISTS')});
				}
			}
		);
		return aDeferred.promise();
	},

	saveAndViewFilter : function(){
		this.saveFilter().then(function (response) {
			if (typeof response != "undefined") {
				app.helper.showSuccessNotification({'message':app.vtranslate('JS_LIST_SAVED')});
				var appName = app.getAppName();
				var url = response['listviewurl']+'&app='+appName;
				window.location.href = url;
			} else {
				app.helper.showErrorNotification({message: app.vtranslate('JS_FAILED_TO_SAVE')});
			}
		});
	},

	isAllUsersSelected : function() {
		var memberList = jQuery('#memberList').val();
		return (memberList != null && (memberList.indexOf('All::Users') != -1)) ? true : false
	},

	registerOnlyAllUsersInSharedList : function(){
		var self = this;
		jQuery('#memberList').on('change',function(e){
			var element = jQuery(e.currentTarget);
			if(self.isAllUsersSelected()){
				element.find('option').not('[value="All::Users"]').prop('disabled',true);
				element.val(['All::Users']).trigger('change.select2');
				element.select2('close');
			}else{
				element.find('option').removeProp('disabled');
			}
		});
	},

	/**
	 * Function which will register the select2 elements for columns selection
	 */
	registerSelect2ElementForColumnsSelection : function() {
		var selectElement = this.getColumnSelectElement();
		vtUtils.showSelect2ElementView(selectElement,{maximumSelectionLength: 15});
	},

	registerFilterCreateEvents : function() {
		var self = this;
		self.registerSelect2ElementForColumnsSelection();
		this.makeColumnListSortable();
		this.registerToogleShareList();
		this.registerOnlyAllUsersInSharedList();
		var customViewForm = jQuery('#CustomView');

		if(customViewForm.length > 0) {
			customViewForm.vtValidate({
				submitHandler : function(form){
					var form = jQuery(form); 
						  var selectElement = form.find('#viewColumnsSelect'); 
						  var mandatoryFieldsList = JSON.parse(jQuery('#mandatoryFieldsList').val()); 
						  var selectedOptions = selectElement.val(); 
						  var mandatoryFieldsMissing = true; 
						  for(var i=0; i<selectedOptions.length; i++) { 
						if(jQuery.inArray(selectedOptions[i], mandatoryFieldsList) >= 0) { 
							mandatoryFieldsMissing = false; 
								  break; 
						} 
					} 
						  if(mandatoryFieldsMissing){ 
						app.helper.showErrorNotification({message:"Select atleast one mandatory value."}); 
							  return false; 
					} 
					//handled advanced filters saved values.
					var advfilterlist = self.advanceFilterInstance.getValues();
					jQuery('#advfilterlist').val(JSON.stringify(advfilterlist));

					var selectValues = JSON.stringify(self.getSelectedColumns());
					jQuery('input[name="columnslist"]', self.getContainer()).val(selectValues);
					var allUsersStatusEle = jQuery('#allUsersStatusValue');
					if(self.isAllUsersSelected() && (jQuery('[data-toogle-members]').is(":checked"))){
						allUsersStatusEle.val(allUsersStatusEle.data('public'));
					}else{
						allUsersStatusEle.val(allUsersStatusEle.data('private'));
					}
					self.saveAndViewFilter();
					return false;
				}
			});
		}
	},

	registerToogleShareList : function() {
		jQuery('[data-toogle-members]').on('change',function(e){
			var element = jQuery(e.currentTarget);
			if(element.is(':checked')){
				jQuery('#memberList').addClass('fadeInx').data('rule-required',true);                
			}
			else {
				jQuery('#memberList').removeClass('fadeInx').data('rule-required',false);
			}
		});
	},

	registerEvents : function() {
		var self = this;
		jQuery(document).on('post.CreateFilter.click',function(e,params){
			self.doOperation(params.url).then(function(data){
				self.showCreateFilter(data);
				var form = jQuery('#CustomView');
				app.helper.registerLeavePageWithoutSubmit(form);
				app.helper.registerModalDismissWithoutSubmit(form);
			})
		});

		jQuery(document).on('post.DeleteFilter.click',function(e,params){
			var target = jQuery(e.target);
			app.helper.showConfirmation({'message': app.vtranslate('LBL_LIST_DELETE_CONFIRMATION')}).then(
				function(){
					app.helper.showProgress();
					app.request.post({'url':params.url}).then(function(){
						app.helper.hideProgress();
						target.trigger('post.DeletedFilter');
						// moduleFiltersId is Default All Filter Id
						var moduleFiltersId = jQuery('.module-filters input[name=allCvId]').val();
							jQuery(".listViewFilter ").find('.filterName').each(function(key, ele){
								var filterId = jQuery(ele).data('filter-id');
								if(filterId == moduleFiltersId){
									jQuery(ele).trigger('click');
									return false;
								}
							});
					});
				},
				function(){
				}
			);
		});

		jQuery(document).on('post.ToggleDefault.click',function(e,params){
			var target = jQuery(e.target);
			var url = target.data('url');
			var currentValue = target.data('isDefault');
			var params = {};
			params.url = url;
			params.data = {};
			if(currentValue) {
				params.data.setdefault = '0';
			}else{
				params.data.setdefault = '1';
			}
			app.request.post(params).then(function(error,data){
				target.trigger('post.ToggleDefault.saved',data);
			})
		});
	}
});

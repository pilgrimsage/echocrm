/*+***********************************************************************************
 * Settings > Configuration > Entity Identifier: pick which field(s) compose a
 * module's record name/identifier. Selection order matters (click order =
 * name order, e.g. firstname then lastname).
 *************************************************************************************/

jQuery(function($) {
	var container = $('#entityIdentifierContainer');
	if (container.length === 0) {
		return;
	}

	var selectedOrder = [];
	var fieldLabels = {};

	function renderSelectedList() {
		var list = $('#entityIdentifierSelectedList');
		list.empty();
		selectedOrder.forEach(function(fieldName, index) {
			list.append(
				$('<li>').addClass('entityIdentifierChip').append(
					$('<span>').addClass('chipIndex').text(index + 1),
					$('<span>').text(fieldLabels[fieldName] || fieldName),
					$('<i>').addClass('fa fa-times chipRemove').data('field', fieldName)
				)
			);
		});
		$('#entityIdentifierSaveButton').prop('disabled', selectedOrder.length === 0);
	}

	container.on('click', '.chipRemove', function() {
		var fieldName = $(this).data('field');
		selectedOrder = selectedOrder.filter(function(f) { return f !== fieldName; });
		container.find('.entityIdentifierFieldCheckbox[value="' + fieldName + '"]').prop('checked', false);
		renderSelectedList();
	});

	container.on('change', '.entityIdentifierFieldCheckbox', function() {
		var fieldName = $(this).val();
		if (this.checked) {
			if (selectedOrder.indexOf(fieldName) === -1) {
				selectedOrder.push(fieldName);
			}
		} else {
			selectedOrder = selectedOrder.filter(function(f) { return f !== fieldName; });
		}
		renderSelectedList();
	});

	$('#entityIdentifierModule').on('change', function() {
		var moduleName = $(this).val();
		selectedOrder = [];
		fieldLabels = {};
		$('#entityIdentifierFieldsList').empty();
		renderSelectedList();

		if (!moduleName) {
			$('#entityIdentifierFieldsRow').hide();
			return;
		}

		app.helper.showProgress();
		$.ajax({
			url: 'index.php?module=Vtiger&parent=Settings&view=EntityIdentifierIndex&mode=getModuleFields',
			type: 'GET',
			data: { sourceModule: moduleName },
			dataType: 'json'
		}).done(function(data) {
			app.helper.hideProgress();
			if (!data || !data.result) {
				return;
			}
			var fieldsList = $('#entityIdentifierFieldsList');
			data.result.fields.forEach(function(field) {
				fieldLabels[field.name] = field.label;
				var checkboxId = 'entityIdentifierField_' + field.name;
				fieldsList.append(
					$('<div>').addClass('entityIdentifierFieldOption').append(
						$('<input>').attr({type: 'checkbox', id: checkboxId, value: field.name})
							.addClass('entityIdentifierFieldCheckbox'),
						$('<label>').attr('for', checkboxId).text(' ' + field.label)
					)
				);
			});

			(data.result.currentFields || []).forEach(function(fieldName) {
				if (fieldLabels[fieldName]) {
					fieldsList.find('#entityIdentifierField_' + fieldName).prop('checked', true);
					selectedOrder.push(fieldName);
				}
			});
			renderSelectedList();
			$('#entityIdentifierFieldsRow').show();
		});
	});

	$('#entityIdentifierSaveButton').on('click', function() {
		var moduleName = $('#entityIdentifierModule').val();
		if (!moduleName || selectedOrder.length === 0) {
			return;
		}

		var moduleLabel = $('#entityIdentifierModule option:selected').text();
		var fieldLabelsOrdered = selectedOrder.map(function(f) { return fieldLabels[f]; }).join(', ');
		var message = app.vtranslate('JS_ENTITY_IDENTIFIER_CONFIRM') + ' <strong>' + moduleLabel + '</strong> ' +
			app.vtranslate('JS_ENTITY_IDENTIFIER_CONFIRM_TO') + ' <strong>' + fieldLabelsOrdered + '</strong>. ' +
			app.vtranslate('JS_ENTITY_IDENTIFIER_CONFIRM_REBUILD');

		app.helper.showConfirmation({message: message}).then(function() {
			app.helper.showProgress();
			$.ajax({
				url: 'index.php?module=Vtiger&parent=Settings&action=EntityIdentifierSave',
				type: 'POST',
				data: {
					sourceModule: moduleName,
					fieldNames: selectedOrder
				},
				dataType: 'json'
			}).done(function(data) {
				app.helper.hideProgress();
				if (data && data.success) {
					app.helper.showAlertBox({message: app.vtranslate('JS_ENTITY_IDENTIFIER_SAVED')});
				} else {
					app.helper.showAlertBox({message: (data && data.error && data.error.message) || app.vtranslate('JS_ERROR_OCCURED_PLS_TRY_AGAIN')});
				}
			}).fail(function() {
				app.helper.hideProgress();
				app.helper.showAlertBox({message: app.vtranslate('JS_ERROR_OCCURED_PLS_TRY_AGAIN')});
			});
		});
	});
});

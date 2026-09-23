/*+***********************************************************************************
 * Hides the raw CGST/SGST/IGST tax checkboxes on the Product/Service edit form
 * (they're now managed automatically from the GST Rate dropdown via
 * ProductsGSTHandler) and shows/hides the GST vs US Sales Tax fields based on
 * the admin's Tax System choice (Settings > Inventory > Tax > Tax System).
 *************************************************************************************/

(function($) {

	function hideTaxCheckboxRow(exactLabel) {
		$('.taxLabel').each(function() {
			// .taxLabel's own text node holds the tax name; the nested
			// span.paddingLeft10px ("(%)") must be excluded or overlapping
			// names (e.g. "Sales" vs "US Sales Tax") would both match.
			var label = $.trim($(this).clone().children().remove().end().text());
			if (label === exactLabel) {
				var labelTd = $(this).closest('td.fieldLabel');
				var valueTd = labelTd.next('td.fieldValue');
				labelTd.hide();
				valueTd.hide();
			}
		});
	}

	function hideFieldRow(fieldName) {
		var input = $('[name="' + fieldName + '"]').first();
		if (input.length === 0) {
			return;
		}
		input.closest('td.fieldValue').hide();
		input.closest('td.fieldValue').prev('td.fieldLabel').hide();
	}

	function applyVisibility(taxSystem) {
		// The dropdown already drives CGST/SGST/IGST behind the scenes - the
		// raw checkboxes/region editors are redundant and confusing here.
		hideTaxCheckboxRow('CGST');
		hideTaxCheckboxRow('SGST');
		hideTaxCheckboxRow('IGST');

		if (taxSystem === 'india') {
			hideTaxCheckboxRow('US Sales Tax');
			hideTaxCheckboxRow('VAT');
			hideTaxCheckboxRow('Sales');
			hideTaxCheckboxRow('Service');
		} else if (taxSystem === 'us') {
			hideFieldRow('gst_rate');
			hideFieldRow('hsn_sac_code');
			hideTaxCheckboxRow('VAT');
			hideTaxCheckboxRow('Service');
		}
	}

	$(document).ready(function() {
		if ($('[name="gst_rate"]').length === 0 && $('.taxLabel').length === 0) {
			return;
		}

		jQuery.ajax({
			url: 'index.php?module=Vtiger&action=GetTaxSystem',
			type: 'GET',
			dataType: 'json'
		}).done(function(data) {
			var taxSystem = (data && data.result && data.result.taxSystem) ? data.result.taxSystem : 'all';
			applyVisibility(taxSystem);
		});
	});

})(jQuery);

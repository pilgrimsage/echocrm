/*+***********************************************************************************
 * Auto-selects the Intra-State/Inter-State tax region on Quotes/SalesOrder/
 * PurchaseOrder/Invoice edit forms by comparing the Bill-To state against the
 * company's own state (Settings > Company Details). The actual CGST/SGST/IGST
 * percentage swap is handled entirely by vtiger's existing region-tax-override
 * mechanism (Edit.js) once the correct region is selected - this file only
 * picks the region, it does no tax math itself.
 *************************************************************************************/

(function($) {

	function normalizeState(state) {
		return ($.trim(state || '')).toLowerCase();
	}

	function findRegionValueByLabelSubstring(regionElement, labelSubstring) {
		var found = null;
		regionElement.find('option').each(function() {
			if ($(this).text().indexOf(labelSubstring) !== -1) {
				found = $(this).val();
				return false;
			}
		});
		return found;
	}

	function applyAutoRegion() {
		var regionElement = $('#region_id');
		if (regionElement.length === 0) {
			return;
		}

		var orgState = normalizeState(regionElement.data('org-state'));
		if (!orgState) {
			return;
		}

		var intraStateRegionId = findRegionValueByLabelSubstring(regionElement, 'Intra-State');
		var interStateRegionId = findRegionValueByLabelSubstring(regionElement, 'Inter-State');
		if (!intraStateRegionId || !interStateRegionId) {
			return;
		}

		var billStateElement = $('[name="bill_state"]');
		if (billStateElement.length === 0) {
			return;
		}

		billStateElement.on('change', function() {
			var billState = normalizeState($(this).val());
			if (!billState) {
				return;
			}

			var targetRegionId = (billState === orgState) ? intraStateRegionId : interStateRegionId;
			var currentRegionId = regionElement.val();

			if (currentRegionId == targetRegionId) {
				return;
			}

			if (!currentRegionId || currentRegionId == '0') {
				// Nothing selected yet (new record, no line items to replace) -
				// just set the default silently, vtiger's own product-selection
				// logic will read this value when line items are added.
				regionElement.val(targetRegionId);
			} else {
				// A region was already active - go through vtiger's own region
				// change handling so existing line items get recalculated (it
				// will ask the user to confirm replacing the current taxes).
				regionElement.val(targetRegionId).trigger('change');
			}
		});
	}

	$(document).ready(function() {
		applyAutoRegion();
	});

})(jQuery);

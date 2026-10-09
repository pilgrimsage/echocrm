{strip}
<div class="container-fluid p-4" style="max-width:1000px">
	<h4 class="mb-3">Opening stock and stock adjustments</h4>
	{if $ERROR}<div class="alert alert-danger">{$ERROR|escape:'html'}</div>{/if}
	{if $MESSAGE}<div class="alert alert-success">{$MESSAGE|escape:'html'}</div>{/if}
	<form method="post" action="index.php" id="adjustForm">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="action" value="StockAdjustmentSave"/>
		<div class="row g-3 mb-3">
			<div class="col-12 col-md-3"><label class="form-label">Kind</label>
				<select name="adjustment_type" class="form-select" id="adjType">{foreach from=['Opening Stock','Stock Count Correction','Write-off','Damage','Found'] item=T}<option value="{$T}">{$T}</option>{/foreach}</select></div>
			<div class="col-6 col-md-2"><label class="form-label">Date</label><input type="date" name="adjustment_date" value="{$TODAY}" class="form-control" required/></div>
			<div class="col-12 col-md-4"><label class="form-label">Other side of the entry</label>
				<select name="counter_ledger" class="form-select"><option value="">Automatic (opening balances / stock write-offs)</option>{foreach from=$LEDGERS key=ID item=NAME}<option value="{$ID}">{$NAME|escape:'html'}</option>{/foreach}</select></div>
			<div class="col-12 col-md-3"><label class="form-label">Narration</label><input type="text" name="narration" class="form-control" maxlength="200"/></div>
		</div>
		<div class="form-text mb-2">Opening stock and anything added needs a cost per unit; write-offs and damage take stock out at the current average cost (type the quantity as a positive number). For a count correction use a positive quantity to add and a negative one to remove.</div>
		<table class="table table-sm align-middle" id="adjLines">
			<thead><tr><th>Product</th><th style="width:130px" class="text-end">Quantity</th><th style="width:150px" class="text-end">Cost per unit</th><th style="width:160px" class="text-muted">Old stock field</th></tr></thead>
			<tbody>
			{section name=r start=0 loop=5}
				<tr><td><select name="product[]" class="form-select form-select-sm adjProduct"><option value="">Select product</option>{foreach from=$PRODUCTS key=ID item=P}<option value="{$ID}" data-old="{$P.old_qty}" data-moves="{$P.moves}">{$P.name|escape:'html'}</option>{/foreach}</select></td>
				<td><input type="number" step="0.001" name="qty[]" class="form-control form-control-sm text-end"/></td>
				<td><input type="number" step="0.01" min="0" name="cost[]" class="form-control form-control-sm text-end"/></td>
				<td class="small text-muted adjOld"></td></tr>
			{/section}
			</tbody>
		</table>
		<div class="d-flex gap-2"><button type="button" class="btn btn-outline-secondary btn-sm" id="addAdjLine">Add line</button><button class="btn btn-primary" type="submit">Record and post</button></div>
	</form>
	<h6 class="mt-4">Recent adjustments</h6>
	<table class="table table-sm"><thead><tr><th>Date</th><th>Kind</th><th>Narration</th><th class="text-end">Lines</th><th class="text-end">Net value</th></tr></thead><tbody>
		{foreach from=$RECENT item=A}<tr><td>{$A.adjustment_date}</td><td>{$A.adjustment_type}</td><td>{$A.narration|escape:'html'}</td><td class="text-end">{$A.lines}</td><td class="text-end">{$A.value|number_format:2}</td></tr>{foreachelse}<tr><td colspan="5" class="text-muted text-center">None yet.</td></tr>{/foreach}</tbody></table>
</div>
<script type="text/javascript">
(function () {
	var body = document.querySelector('#adjLines tbody');
	function note(select) {
		var option = select.options[select.selectedIndex];
		var cell = select.closest('tr').querySelector('.adjOld');
		cell.textContent = option && option.value ? ('Product field: ' + option.getAttribute('data-old') + (option.getAttribute('data-moves') === '0' ? ' (no stock history)' : '')) : '';
		var qty = select.closest('tr').querySelector('input[name="qty[]"]');
		if (option && option.value && option.getAttribute('data-moves') === '0' && !qty.value && parseFloat(option.getAttribute('data-old')) > 0) { qty.value = option.getAttribute('data-old'); }
	}
	body.addEventListener('change', function (e) { if (e.target.classList.contains('adjProduct')) { note(e.target); } });
	document.getElementById('addAdjLine').addEventListener('click', function () { var row = body.rows[0].cloneNode(true); row.querySelectorAll('input').forEach(function (i) { i.value = ''; }); row.querySelector('select').value = ''; row.querySelector('.adjOld').textContent = ''; body.appendChild(row); });
})();
</script>
{/strip}

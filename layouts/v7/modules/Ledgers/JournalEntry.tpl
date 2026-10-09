{strip}
<div class="container-fluid p-4" style="max-width:980px">
	<h4 class="mb-3">New journal entry</h4>
	{if $ERROR}<div class="alert alert-danger">{$ERROR|escape:'html'}</div>{/if}
	<form method="post" action="index.php" id="journalForm">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="action" value="JournalSave"/>
		<div class="row g-3 mb-3">
			<div class="col-12 col-md-3"><label class="form-label">Date <span class="text-danger">*</span></label><input type="date" name="entry_date" value="{$TODAY}" class="form-control" required/></div>
			<div class="col-12 col-md-9"><label class="form-label">Narration</label><input type="text" name="narration" class="form-control" maxlength="250"/></div>
		</div>
		<table class="table table-sm align-middle" id="journalLines">
			<thead><tr><th>Ledger</th><th style="width:150px" class="text-end">Debit</th><th style="width:150px" class="text-end">Credit</th><th>Memo</th>{if $COST_CENTRES}<th style="width:200px">Cost centre / project</th>{/if}</tr></thead>
			<tbody>
			{section name=r start=0 loop=4}
				<tr>
					<td><select name="ledger[]" class="form-select form-select-sm"><option value="">Select ledger</option>{foreach from=$LEDGERS key=ID item=NAME}<option value="{$ID}">{$NAME|escape:'html'}</option>{/foreach}</select></td>
					<td><input type="number" step="0.01" min="0" name="debit[]" class="form-control form-control-sm text-end jd"/></td>
					<td><input type="number" step="0.01" min="0" name="credit[]" class="form-control form-control-sm text-end jc"/></td>
					<td><input type="text" name="memo[]" class="form-control form-control-sm" maxlength="200"/></td>
					{if $COST_CENTRES}<td><select name="cost[]" class="form-select form-select-sm"><option value="">None</option>{foreach from=$COST_CENTRES key=ID item=NAME}<option value="{$ID}">{$NAME|escape:'html'}</option>{/foreach}</select></td>{/if}
				</tr>
			{/section}
			</tbody>
			<tfoot><tr class="fw-bold"><td class="text-end">Totals</td><td class="text-end" id="totalDebit">0.00</td><td class="text-end" id="totalCredit">0.00</td><td id="balanceNote"></td></tr></tfoot>
		</table>
		<div class="d-flex gap-2">
			<button type="button" class="btn btn-outline-secondary btn-sm" id="addLine">Add line</button>
			<button type="submit" class="btn btn-primary" id="saveEntry">Post entry</button>
			<a href="index.php?module=Ledgers&view=Journal" class="btn btn-outline-secondary">Cancel</a>
		</div>
	</form>
</div>
<script type="text/javascript">
(function () {
	var body = document.querySelector('#journalLines tbody');
	function sum(selector) { var t = 0; document.querySelectorAll(selector).forEach(function (i) { t += parseFloat(i.value) || 0; }); return Math.round(t * 100) / 100; }
	function update() {
		var d = sum('.jd'), c = sum('.jc');
		document.getElementById('totalDebit').textContent = d.toFixed(2);
		document.getElementById('totalCredit').textContent = c.toFixed(2);
		var note = document.getElementById('balanceNote');
		var ok = d > 0 && d === c;
		note.textContent = ok ? 'Balanced' : (d === c ? '' : 'Difference ' + Math.abs(d - c).toFixed(2));
		note.className = ok ? 'text-success' : 'text-danger';
		document.getElementById('saveEntry').disabled = !ok;
	}
	body.addEventListener('input', update);
	document.getElementById('addLine').addEventListener('click', function () { body.appendChild(body.rows[0].cloneNode(true)); body.lastElementChild.querySelectorAll('input').forEach(function (i) { i.value = ''; }); body.lastElementChild.querySelectorAll('select').forEach(function (x) { x.value = ''; }); });
	update();
})();
</script>
{/strip}

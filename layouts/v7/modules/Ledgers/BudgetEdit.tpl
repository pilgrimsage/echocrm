{strip}
<div class="container-fluid p-4">
	<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2"><h4 class="mb-0">{$BUDGET.budget_name|escape:'html'} <span class="text-muted fs-6">{$YEAR_LABEL} - {$BUDGET.status}</span></h4><a class="btn btn-outline-secondary btn-sm" href="index.php?module=Ledgers&view=Budgets">Back to budgets</a></div>
	{if $MESSAGE}<div class="alert alert-success py-2">{$MESSAGE|escape:'html'}</div>{/if}
	{if $ERROR}<div class="alert alert-danger py-2">{$ERROR|escape:'html'}</div>{/if}
	<div class="text-muted small mb-2">Type an amount in a month, or type the year's total in the Year box and press "Spread" to divide it evenly. Income and expense ledgers only; leave the cost centre empty to budget the whole ledger. A row with no ledger is ignored.</div>
	<form method="post" action="index.php" id="budgetForm">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="action" value="BudgetAct"/><input type="hidden" name="do" value="save"/><input type="hidden" name="budget" value="{$BUDGET.budget_id}"/>
		<div class="table-responsive"><table class="table table-sm align-middle" id="grid" style="min-width:1500px">
			<thead><tr><th style="min-width:230px">Ledger</th><th style="min-width:170px">Cost centre / project</th>{foreach from=$MONTHS item=M}<th class="text-end" style="min-width:86px">{$M}</th>{/foreach}<th style="min-width:150px">Year</th></tr></thead>
			<tbody>
			{foreach from=$LINES item=L}
				<tr>
					<td><select name="ledger[{$L.line_id}]" class="form-select form-select-sm">{foreach from=$LEDGERS key=ID item=N}<option value="{$ID}" {if $ID eq $L.ledger_id}selected{/if}>{$N|escape:'html'}</option>{/foreach}</select></td>
					<td><select name="cost[{$L.line_id}]" class="form-select form-select-sm"><option value="">Whole ledger</option>{foreach from=$COST_CENTRES key=ID item=N}<option value="{$ID}" {if $ID eq $L.cost_centre_id}selected{/if}>{$N|escape:'html'}</option>{/foreach}</select></td>
					{section name=k start=1 loop=13}{assign var=KEY value="m`$smarty.section.k.index`"}<td><input type="number" step="0.01" min="0" name="m[{$L.line_id}][]" value="{$L.$KEY}" class="form-control form-control-sm text-end bm"/></td>{/section}
					<td><div class="d-flex gap-1 align-items-center"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end by" value="{$L.total}"/><button type="button" class="btn btn-outline-secondary btn-sm spread">Spread</button></div></td>
				</tr>
			{/foreach}
			</tbody>
			<tfoot><tr class="fw-bold"><td colspan="2">Total of the rows above</td>{section name=k start=0 loop=12}<td class="text-end colTotal">0.00</td>{/section}<td class="text-end" id="grand">0.00</td></tr></tfoot>
		</table></div>
		<div class="d-flex gap-2 mt-2"><button type="button" class="btn btn-outline-secondary btn-sm" id="addRow">Add row</button><button class="btn btn-primary" type="submit">Save budget</button></div>
	</form>
</div>
<script type="text/javascript">
(function () {
	var body = document.querySelector('#grid tbody');
	function num(v) { var n = parseFloat(v); return isNaN(n) ? 0 : n; }
	function totals() {
		var cols = new Array(12).fill(0), grand = 0;
		body.querySelectorAll('tr').forEach(function (tr) {
			var sum = 0;
			tr.querySelectorAll('.bm').forEach(function (input, i) { var v = num(input.value); cols[i] += v; sum += v; });
			var year = tr.querySelector('.by'); if (year && document.activeElement !== year) { year.value = sum ? sum.toFixed(2) : ''; }
			grand += sum;
		});
		document.querySelectorAll('.colTotal').forEach(function (td, i) { td.textContent = cols[i].toFixed(2); });
		document.getElementById('grand').textContent = grand.toFixed(2);
	}
	body.addEventListener('input', totals);
	body.addEventListener('click', function (e) {
		if (!e.target.classList.contains('spread')) { return; }
		var tr = e.target.closest('tr'), total = num(tr.querySelector('.by').value), each = Math.floor(total / 12 * 100) / 100, rest = Math.round((total - each * 12) * 100) / 100;
		tr.querySelectorAll('.bm').forEach(function (input, i) { input.value = (i === 11 ? each + rest : each).toFixed(2); });
		totals();
	});
	var counter = 0;
	document.getElementById('addRow').addEventListener('click', function () {
		var template = body.rows.length ? body.rows[0] : null; var tr;
		if (template) { tr = template.cloneNode(true); tr.querySelectorAll('input').forEach(function (i) { i.value = ''; }); tr.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; }); }
		else { alert('Save once to add the first rows, or start the budget from last year\'s actuals.'); return; }
		counter++; tr.querySelectorAll('.bm').forEach(function (i) { i.name = 'm[new' + counter + '][]'; }); tr.querySelector('select[name^="ledger"]').name = 'ledger[new' + counter + ']'; tr.querySelector('select[name^="cost"]').name = 'cost[new' + counter + ']';
		body.appendChild(tr); totals();
	});
	totals();
})();
</script>
{/strip}

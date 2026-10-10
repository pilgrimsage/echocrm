{strip}
<div class="container-fluid p-4">
	<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2"><h4 class="mb-0">Budget against actual</h4><a class="btn btn-outline-secondary btn-sm" href="index.php?module=Ledgers&view=Budgets">Budgets</a></div>
	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="view" value="BudgetReport"/>
		<div class="col-12 col-md-3"><label class="form-label">Budget</label><select name="budget" class="form-select">{foreach from=$BUDGETS item=B}<option value="{$B.budget_id}" {if $B.budget_id eq $BUDGET_ID}selected{/if}>{$B.budget_name|escape:'html'} ({$B.label}{if $B.status eq 'Active'}, active{/if})</option>{/foreach}</select></div>
		<div class="col-6 col-md-2"><label class="form-label">From month</label><select name="from_month" class="form-select">{foreach from=$MONTH_NAMES item=N key=I}<option value="{$I+1}" {if $I+1 eq $FROM_MONTH}selected{/if}>{$N}</option>{/foreach}</select></div>
		<div class="col-6 col-md-2"><label class="form-label">To month</label><select name="to_month" class="form-select">{foreach from=$MONTH_NAMES item=N key=I}<option value="{$I+1}" {if $I+1 eq $TO_MONTH}selected{/if}>{$N}</option>{/foreach}</select></div>
		{if $COST_CENTRES}<div class="col-12 col-md-3"><label class="form-label">Cost centre / project</label><select name="cc" class="form-select"><option value="">All lines</option>{foreach from=$COST_CENTRES key=ID item=N}<option value="{$ID}" {if $ID eq $CC}selected{/if}>{$N|escape:'html'}</option>{/foreach}</select></div>{/if}
		<div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit">Show</button></div>
	</form>
	{if !$REPORT}<div class="alert alert-info">No budget yet. <a href="index.php?module=Ledgers&view=Budgets">Create one</a>.</div>{else}
	{foreach from=['Income','Expenses'] item=G}
	<h6 class="mt-3">{$G}</h6>
	<div class="table-responsive"><table class="table table-sm table-hover align-middle">
		<thead><tr><th>Ledger</th><th>Cost centre</th><th class="text-end">Budget</th><th class="text-end">Actual</th><th class="text-end">Variance</th><th class="text-end">% of budget</th><th class="text-end">Year budget</th><th class="text-end">Year used</th></tr></thead>
		<tbody>
		{foreach from=$REPORT.lines item=L}{if $L.group eq $G}
			<tr><td><a href="index.php?module=Ledgers&view=Statement&record={$L.ledger_id}&from={$REPORT.from}&to={$REPORT.to}">{$L.ledger|escape:'html'}</a></td><td>{$L.cost|escape:'html'}</td>
			<td class="text-end">{$L.budget|number_format:2}</td><td class="text-end">{$L.actual|number_format:2}</td>
			<td class="text-end {if $L.variance < 0}text-danger{elseif $L.variance > 0}text-success{/if}">{$L.variance|number_format:2}</td>
			<td class="text-end">{if $L.percent !== null}{$L.percent}%{/if}</td><td class="text-end">{$L.annual|number_format:2}</td>
			<td class="text-end {if $G eq 'Expenses' && $L.annual_used !== null && $L.annual_used > 100}text-danger fw-bold{/if}">{if $L.annual_used !== null}{$L.annual_used}%{/if}</td></tr>
		{/if}{foreachelse}{/foreach}
		</tbody>
		<tfoot><tr class="fw-bold"><td colspan="2">Total {$G|lower}</td><td class="text-end">{$REPORT.totals.$G.budget|number_format:2}</td><td class="text-end">{$REPORT.totals.$G.actual|number_format:2}</td><td></td><td></td><td class="text-end">{$REPORT.totals.$G.annual|number_format:2}</td><td></td></tr></tfoot>
	</table></div>
	{/foreach}
	<div class="row g-3 mt-2">
		<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Budgeted result</div><div class="fs-5">{$REPORT.net_budget|number_format:2}</div></div></div>
		<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Actual result</div><div class="fs-5 fw-bold {if $REPORT.net_actual < $REPORT.net_budget}text-danger{else}text-success{/if}">{$REPORT.net_actual|number_format:2}</div></div></div>
	</div>
	<div class="text-muted small mt-2">Variance is favourable (green) when income is above the plan or expenses are below it. Actuals are what was posted in the months shown, year-end closing entries left out; a line with a cost centre compares with that centre and everything below it.</div>
	{/if}
</div>
{/strip}

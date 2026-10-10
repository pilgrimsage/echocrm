{strip}
<div class="container-fluid p-4" style="max-width:1000px">
	<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2"><h4 class="mb-0">Budgets</h4><a class="btn btn-outline-primary btn-sm" href="index.php?module=Ledgers&view=BudgetReport">Budget against actual</a></div>
	{if $MESSAGE}<div class="alert alert-success py-2">{$MESSAGE|escape:'html'}</div>{/if}
	{if $ERROR}<div class="alert alert-danger py-2">{$ERROR|escape:'html'}</div>{/if}
	<p class="text-muted small">A budget plans income and expenses by ledger (and, if you like, by cost centre or project) for each month of a financial year. A year can have several versions; the one marked Active is used by the reports and by the spending control.</p>
	<table class="table align-middle">
		<thead><tr><th>Budget</th><th>Year</th><th class="text-end">Lines</th><th>Status</th><th></th></tr></thead>
		<tbody>
		{foreach from=$BUDGETS item=B}
			<tr><td class="fw-semibold">{$B.budget_name|escape:'html'}</td><td>{$B.label}</td><td class="text-end">{$B.lines}</td>
			<td>{if $B.status eq 'Active'}<span class="badge bg-success">Active</span>{elseif $B.status eq 'Draft'}<span class="badge bg-warning text-dark">Draft</span>{else}<span class="badge bg-secondary">{$B.status}</span>{/if}</td>
			<td class="text-end text-nowrap">
				<a class="btn btn-outline-secondary btn-sm" href="index.php?module=Ledgers&view=BudgetReport&budget={$B.budget_id}">Against actual</a>
				{if $CAN_EDIT}<a class="btn btn-outline-secondary btn-sm" href="index.php?module=Ledgers&view=BudgetEdit&budget={$B.budget_id}">Edit</a>
				<form method="post" action="index.php" class="d-inline"><input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="action" value="BudgetAct"/><input type="hidden" name="budget" value="{$B.budget_id}"/>
					{if $B.status neq 'Active'}<button name="do" value="activate" class="btn btn-outline-success btn-sm">Make active</button>{/if}
					<button name="do" value="delete" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this budget?');">Delete</button></form>{/if}</td></tr>
		{foreachelse}<tr><td colspan="5" class="text-center text-muted py-4">No budgets yet.</td></tr>{/foreach}
		</tbody>
	</table>
	{if $CAN_EDIT}
	<h6 class="mt-4">New budget</h6>
	<form method="post" action="index.php" class="border rounded p-3 row g-3 align-items-end">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="action" value="BudgetAct"/><input type="hidden" name="do" value="create"/>
		<div class="col-12 col-md-4"><label class="form-label">Name</label><input type="text" name="budget_name" class="form-control" placeholder="Original" required/></div>
		<div class="col-6 col-md-3"><label class="form-label">Financial year</label><select name="year_start" class="form-select">{foreach from=$YEARS item=Y}<option value="{$Y.start}" {if $Y.start eq $CURRENT_YEAR}selected{/if}>{$Y.label}</option>{/foreach}</select></div>
		<div class="col-6 col-md-3"><label class="form-label">Start from</label><select name="copy" class="form-select" id="copy"><option value="blank">An empty budget</option><option value="actuals">Last year's actuals</option>{if $BUDGETS}<option value="budget">Another budget</option>{/if}</select></div>
		<div class="col-6 col-md-2"><label class="form-label">Growth %</label><input type="number" step="0.1" name="growth" value="0" class="form-control"/></div>
		<div class="col-12 col-md-4" id="fromBudget" style="display:none"><label class="form-label">Copy this budget</label><select name="from_budget" class="form-select">{foreach from=$BUDGETS item=B}<option value="{$B.budget_id}">{$B.budget_name|escape:'html'} ({$B.label})</option>{/foreach}</select></div>
		<div class="col-12"><button class="btn btn-primary" type="submit">Create and edit</button></div>
	</form>
	<script type="text/javascript">document.getElementById('copy').addEventListener('change', function () { document.getElementById('fromBudget').style.display = this.value === 'budget' ? '' : 'none'; });</script>
	{/if}
</div>
{/strip}

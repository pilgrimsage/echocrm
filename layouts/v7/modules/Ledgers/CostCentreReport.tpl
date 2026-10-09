{strip}
<div class="container-fluid p-4">
	<h4 class="mb-3">{vtranslate('LBL_COST_CENTRE_REPORT', 'CostCentres')}</h4>
	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="view" value="CostCentreReport"/>
		<div class="col-6 col-md-3"><label class="form-label">From</label><input type="date" name="from" value="{$FROM}" class="form-control"/></div>
		<div class="col-6 col-md-3"><label class="form-label">To</label><input type="date" name="to" value="{$TO}" class="form-control"/></div>
		<div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit">Show</button></div>
	</form>
	{if !$CENTRES}<div class="alert alert-info">No cost centres or projects yet. Add them under Cost Centres, then choose one on invoices, purchase orders and bank transactions.</div>{else}
	<div class="table-responsive"><table class="table table-sm table-hover align-middle">
		<thead><tr><th>Cost centre / project</th><th>Type</th><th class="text-end">Income</th><th class="text-end">Expenses</th><th class="text-end">Result</th>
			<th class="text-end">Result incl. below</th><th class="text-end">Budget</th><th style="width:140px">Budget used</th></tr></thead>
		<tbody>
		{foreach from=$CENTRES item=C}
			<tr {if $C.status neq 'Active'}class="text-muted"{/if}>
				<td><a href="index.php?module=Ledgers&view=ProfitLoss&cc={$C.id}&from={$FROM}&to={$TO}">{$C.name|escape:'html'}</a>{if $C.status neq 'Active'} <span class="badge bg-secondary">{$C.status|escape:'html'}</span>{/if}</td>
				<td>{vtranslate($C.type, 'CostCentres')}</td>
				<td class="text-end">{$C.income|number_format:2}</td><td class="text-end">{$C.expenses|number_format:2}</td>
				<td class="text-end {if $C.net < 0}text-danger{/if}">{$C.net|number_format:2}</td>
				<td class="text-end {if $C.tree_net < 0}text-danger{/if}">{$C.tree_net|number_format:2}</td>
				<td class="text-end">{if $C.budget > 0}{$C.budget|number_format:2}{/if}</td>
				<td>{if $C.used !== null}<div class="progress" style="height:14px"><div class="progress-bar {if $C.used > 100}bg-danger{elseif $C.used > 80}bg-warning{else}bg-success{/if}" style="width:{if $C.used > 100}100{else}{$C.used}{/if}%">{$C.used}%</div></div>{/if}</td>
			</tr>
		{/foreach}
		</tbody>
		<tfoot><tr class="text-muted"><td colspan="2">Not assigned to any cost centre</td><td class="text-end">{$UNTAGGED.income|number_format:2}</td><td class="text-end">{$UNTAGGED.expenses|number_format:2}</td><td class="text-end">{$UNTAGGED.net|number_format:2}</td><td colspan="3"></td></tr></tfoot>
	</table></div>
	<div class="text-muted small">Income and expenses are the postings tagged with the cost centre in the period; "incl. below" adds its sub-centres. Budget used compares the expenses including sub-centres with the budget.</div>
	{/if}
</div>
{/strip}

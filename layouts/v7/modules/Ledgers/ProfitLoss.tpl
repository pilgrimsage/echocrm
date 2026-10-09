{strip}
<div class="container-fluid p-4">
	<h4 class="mb-3">Profit &amp; Loss</h4>
	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="view" value="ProfitLoss"/>
		<div class="col-6 col-md-3"><label class="form-label">From</label><input type="date" name="from" value="{$FROM}" class="form-control"/></div>
		<div class="col-6 col-md-3"><label class="form-label">To</label><input type="date" name="to" value="{$TO}" class="form-control"/></div>
		{if $COST_CENTRES}<div class="col-12 col-md-4"><label class="form-label">Cost centre / project</label>
			<select name="cc" class="form-select"><option value="">All (company)</option>{foreach from=$COST_CENTRES key=ID item=NAME}<option value="{$ID}" {if $ID eq $CC}selected{/if}>{$NAME|escape:'html'}</option>{/foreach}</select></div>{/if}
		<div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit">Show</button></div>
	</form>
	{if $YEARS}<div class="mb-3 small">Financial year: {foreach from=$YEARS item=Y name=y}{if $smarty.foreach.y.iteration <= 6}<a class="me-2" href="index.php?module=Ledgers&view=ProfitLoss&from={$Y.start}&to={$Y.end}{if $CC}&cc={$CC}{/if}">{$Y.label}{if $Y.closed} (closed){/if}</a>{/if}{/foreach}</div>{/if}
	<div class="row g-4">
		<div class="col-12 col-lg-6">
			<h6>Income</h6>
			<table class="table table-sm"><tbody>
			{foreach from=$INCOME item=ROW}<tr><td><a href="index.php?module=Ledgers&view=Statement&record={$ROW.id}&from={$FROM}&to={$TO}">{$ROW.name|escape:'html'}</a></td><td class="text-end">{$ROW.amount|number_format:2}</td></tr>
			{foreachelse}<tr><td class="text-muted">No income in this period.</td><td></td></tr>{/foreach}
			</tbody><tfoot><tr class="fw-bold"><td>Total income</td><td class="text-end">{$TOTAL_INCOME|number_format:2}</td></tr></tfoot></table>
		</div>
		<div class="col-12 col-lg-6">
			<h6>Expenses</h6>
			<table class="table table-sm"><tbody>
			{foreach from=$EXPENSES item=ROW}<tr><td><a href="index.php?module=Ledgers&view=Statement&record={$ROW.id}&from={$FROM}&to={$TO}">{$ROW.name|escape:'html'}</a></td><td class="text-end">{$ROW.amount|number_format:2}</td></tr>
			{foreachelse}<tr><td class="text-muted">No expenses in this period.</td><td></td></tr>{/foreach}
			</tbody><tfoot><tr class="fw-bold"><td>Total expenses</td><td class="text-end">{$TOTAL_EXPENSES|number_format:2}</td></tr></tfoot></table>
		</div>
	</div>
	<div class="border rounded p-3 mt-2 fs-5 {if $PROFIT lt 0}text-danger{else}text-success{/if}">{if $PROFIT lt 0}Net loss{else}Net profit{/if}: <strong>{$PROFIT|number_format:2}</strong></div>
</div>
{/strip}

{strip}
<div class="container-fluid p-4">
	<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2"><h4 class="mb-0">Stock valuation</h4>
		<a class="btn btn-primary btn-sm" href="index.php?module=Ledgers&view=StockAdjustment">Opening stock / adjustment</a></div>
	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="view" value="StockValuation"/>
		<div class="col-8 col-md-3"><label class="form-label">As at</label><input type="date" name="to" value="{$TO}" class="form-control"/></div>
		<div class="col-4 col-md-2"><button class="btn btn-primary w-100" type="submit">Show</button></div>
	</form>
	<div class="row g-3 mb-3">
		<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Stock value (average cost)</div><div class="fs-5 fw-bold">{$TOTAL|number_format:2}</div></div></div>
		<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Inventory ledger</div><div class="fs-5">{$LEDGER|number_format:2}</div></div></div>
		<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Difference</div><div class="fs-5 {if $TOTAL neq $LEDGER}text-danger{else}text-success{/if}">{($LEDGER-$TOTAL)|number_format:2}</div></div></div>
		<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Units on hand</div><div class="fs-5">{$QTY}</div></div></div>
	</div>
	<div class="table-responsive"><table class="table table-sm table-hover align-middle">
		<thead><tr><th>Product</th><th>Code</th><th class="text-end">Quantity</th><th class="text-end">Average cost</th><th class="text-end">Value</th><th></th></tr></thead>
		<tbody>
		{foreach from=$ROWS item=R name=r}{if $smarty.foreach.r.iteration <= 500}
			<tr {if $R.qty < 0}class="table-danger"{/if}><td><a href="index.php?module=Products&view=Detail&record={$R.id}">{$R.name|escape:'html'}</a></td><td>{$R.code|escape:'html'}</td>
			<td class="text-end">{$R.qty}</td><td class="text-end">{$R.avg_cost|number_format:2}</td><td class="text-end">{$R.value|number_format:2}</td>
			<td class="text-end"><a href="index.php?module=Ledgers&view=StockLedger&product={$R.id}">stock ledger</a></td></tr>
		{/if}{foreachelse}<tr><td colspan="6" class="text-center text-muted py-4">No stock history yet. Enter opening stock, or receive a purchase order.</td></tr>{/foreach}
		</tbody>
		<tfoot><tr class="fw-bold"><td colspan="2">Total{if $ROWS|count > 500} (first 500 products shown){/if}</td><td class="text-end">{$QTY}</td><td></td><td class="text-end">{$TOTAL|number_format:2}</td><td></td></tr></tfoot>
	</table></div>
	<h6 class="mt-4">Checks</h6>
	{foreach from=$CHECKS item=C}<div class="border rounded p-2 mb-2 {if $C.count > 0}border-warning{/if}"><div class="d-flex justify-content-between"><span class="fw-semibold">{$C.title|escape:'html'}</span>{if $C.count > 0}<span class="badge bg-warning text-dark">{$C.count}</span>{else}<span class="badge bg-success">OK</span>{/if}</div>
		<div class="small text-muted">{$C.hint|escape:'html'}</div>{if $C.rows}<div class="small mt-1">{foreach from=$C.rows item=X}<a class="me-3" href="{$X.url}">{$X.label|escape:'html'}</a>{/foreach}</div>{/if}</div>{/foreach}
</div>
{/strip}

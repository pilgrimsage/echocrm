{strip}
<div class="container-fluid p-4">
	<h4 class="mb-3">Stock ledger</h4>
	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="view" value="StockLedger"/>
		<div class="col-12 col-md-4"><label class="form-label">Product name or code starts with</label><input type="text" name="q" value="{$Q|escape:'html'}" class="form-control"/></div>
		<div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit">Find</button></div>
	</form>
	{if $MATCHES}<div class="list-group mb-3" style="max-width:520px">{foreach from=$MATCHES item=M}<a class="list-group-item list-group-item-action" href="index.php?module=Ledgers&view=StockLedger&product={$M.id}">{$M.name|escape:'html'}</a>{/foreach}</div>{/if}
	{if $PRODUCT_ID}
	<h5>{$PRODUCT_NAME|escape:'html'}</h5>
	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="view" value="StockLedger"/><input type="hidden" name="product" value="{$PRODUCT_ID}"/>
		<div class="col-6 col-md-2"><label class="form-label">From</label><input type="date" name="from" value="{$FROM}" class="form-control"/></div>
		<div class="col-6 col-md-2"><label class="form-label">To</label><input type="date" name="to" value="{$TO}" class="form-control"/></div>
		<div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit">Show</button></div>
	</form>
	<div class="row g-3 mb-3">
		<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Opening</div><div>{$BOOK.opening.qty} units, {$BOOK.opening.value|number_format:2}</div></div></div>
		<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Closing</div><div class="fw-bold">{$BOOK.closing.qty} units, {$BOOK.closing.value|number_format:2}</div></div></div>
	</div>
	<div class="table-responsive"><table class="table table-sm table-hover align-middle">
		<thead><tr><th>Date</th><th>Type</th><th>Document</th><th class="text-end">Qty</th><th class="text-end">Unit cost</th><th class="text-end">Value</th><th class="text-end">Balance qty</th><th class="text-end">Balance value</th><th class="text-end">Avg cost</th></tr></thead>
		<tbody>
		{foreach from=$BOOK.rows item=R}
			<tr><td>{$R.display_date}</td><td>{$R.move_type}</td><td>{if $R.url}<a href="{if $R.source_module eq 'Adjustment'}{$R.url}{else}{$R.url}{/if}">{$R.source_module} {$R.source_id}</a>{else}{$R.source_module}{/if}</td>
			<td class="text-end {if $R.qty < 0}text-danger{/if}">{$R.qty}</td><td class="text-end">{$R.unit_cost|number_format:2}</td><td class="text-end">{$R.value|number_format:2}</td>
			<td class="text-end">{$R.balance_qty}</td><td class="text-end">{$R.balance_value|number_format:2}</td><td class="text-end">{$R.avg_cost|number_format:2}</td></tr>
		{foreachelse}<tr><td colspan="9" class="text-center text-muted py-4">No stock moves in this period.</td></tr>{/foreach}
		</tbody>
	</table></div>
	{/if}
</div>
{/strip}

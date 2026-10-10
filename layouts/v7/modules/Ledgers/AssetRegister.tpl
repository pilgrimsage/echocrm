{strip}
<div class="container-fluid p-4">
	<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2"><h4 class="mb-0">Fixed asset register</h4>
		<div class="d-flex gap-2"><a class="btn btn-outline-secondary btn-sm" href="index.php?module=FixedAssets&view=List">Assets</a><a class="btn btn-primary btn-sm" href="index.php?module=Ledgers&view=DepreciationRun">Run depreciation</a></div></div>
	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="view" value="AssetRegister"/>
		<div class="col-8 col-md-3"><label class="form-label">As at</label><input type="date" name="to" value="{$TO}" class="form-control"/></div>
		<div class="col-4 col-md-2"><button class="btn btn-primary w-100" type="submit">Show</button></div>
	</form>
	<div class="table-responsive"><table class="table table-sm table-hover align-middle">
		<thead><tr><th>Asset</th><th>Class</th><th>Purchased</th><th>Status</th><th class="text-end">Cost</th><th class="text-end">Accumulated</th><th class="text-end">Book value</th></tr></thead>
		<tbody>
		{foreach from=$ROWS item=R}<tr {if $R.status eq 'Disposed'}class="text-muted"{/if}><td><a href="index.php?module=FixedAssets&view=Detail&record={$R.id}">{$R.no} {$R.name|escape:'html'}</a></td><td>{$R.class|escape:'html'}</td><td>{$R.purchase_date}</td><td>{$R.status}</td>
			<td class="text-end">{$R.cost|number_format:2}</td><td class="text-end">{$R.accumulated|number_format:2}</td><td class="text-end">{$R.book|number_format:2}</td></tr>
		{foreachelse}<tr><td colspan="7" class="text-center text-muted py-4">No assets yet.</td></tr>{/foreach}
		</tbody>
		<tfoot><tr class="fw-bold"><td colspan="4">Total</td><td class="text-end">{$TOTAL.cost|number_format:2}</td><td class="text-end">{$TOTAL.accumulated|number_format:2}</td><td class="text-end">{$TOTAL.book|number_format:2}</td></tr></tfoot>
	</table></div>
	<h6 class="mt-4">By class, compared with the ledgers</h6>
	<div class="table-responsive"><table class="table table-sm align-middle">
		<thead><tr><th>Class</th><th class="text-end">Register cost</th><th class="text-end">Ledger</th><th class="text-end">Register accumulated</th><th class="text-end">Ledger</th><th class="text-end">Book value</th></tr></thead>
		<tbody>{foreach from=$CLASSES key=NAME item=C}<tr><td>{$NAME|escape:'html'}</td><td class="text-end">{$C.cost|number_format:2}</td><td class="text-end {if $C.ledger_cost !== null && $C.ledger_cost neq $C.cost}text-danger{/if}">{if $C.ledger_cost !== null}{$C.ledger_cost|number_format:2}{/if}</td>
			<td class="text-end">{$C.accumulated|number_format:2}</td><td class="text-end {if $C.ledger_accumulated !== null && $C.ledger_accumulated neq $C.accumulated}text-danger{/if}">{if $C.ledger_accumulated !== null}{$C.ledger_accumulated|number_format:2}{/if}</td><td class="text-end">{$C.book|number_format:2}</td></tr>{/foreach}</tbody>
	</table></div>
	<div class="text-muted small">A red ledger figure means the ledger holds something the register does not (an asset bought through a purchase order and booked directly, a manual entry...).</div>
</div>
{/strip}

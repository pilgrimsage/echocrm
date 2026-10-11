{strip}
<div class="container-fluid p-4" style="max-width:1000px">
	<h4 class="mb-3">Tax withheld at source</h4>
	{if not $ENABLED}<div class="alert alert-warning">Not switched on. Run <code>php bin/create-withholding.php</code>.</div>{/if}
	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="view" value="WithholdingRegister"/>
		<div class="col-6 col-md-3"><label class="form-label">From</label><input type="date" name="from" value="{$FROM}" class="form-control"/></div>
		<div class="col-6 col-md-3"><label class="form-label">To</label><input type="date" name="to" value="{$TO}" class="form-control"/></div>
		<div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit">Show</button></div>
	</form>
	<div class="row g-3 mb-3">
		<div class="col-12 col-md-6"><div class="card"><div class="card-body"><div class="small text-muted">TDS Payable at {$TO} (withheld from vendors, not yet remitted)</div><div class="fs-4">{$PAYABLE|number_format:2}</div>
			<div class="small text-muted">Remit it with a bank payment to the TDS Payable ledger.</div></div></div></div>
		<div class="col-12 col-md-6"><div class="card"><div class="card-body"><div class="small text-muted">TDS Receivable at {$TO} (withheld by customers, to claim)</div><div class="fs-4">{$RECEIVABLE|number_format:2}</div></div></div></div>
	</div>
	<div class="table-responsive"><table class="table table-sm"><thead><tr><th>We</th><th>Section</th><th>Party</th><th class="text-end">Payments</th><th class="text-end">Paid / received</th><th class="text-end">Tax withheld</th></tr></thead><tbody>
		{foreach from=$ROWS item=R}<tr><td>{if $R.direction eq 'Paid'}withheld from vendor{else}were withheld by customer{/if}</td><td>{$R.section|escape:'html'}</td><td>{$R.party|escape:'html'}</td><td class="text-end">{$R.payments}</td><td class="text-end">{$R.amount|number_format:2}</td><td class="text-end">{$R.withheld|number_format:2}</td></tr>
		{foreachelse}<tr><td colspan="6" class="text-muted text-center py-3">No tax withheld in this period.</td></tr>{/foreach}</tbody>
		{if $ROWS}<tfoot><tr class="fw-bold"><td colspan="5">Withheld from vendors</td><td class="text-end">{$TOTALS.Paid|number_format:2}</td></tr><tr class="fw-bold"><td colspan="5">Withheld by customers</td><td class="text-end">{$TOTALS.Received|number_format:2}</td></tr></tfoot>{/if}</table></div>
	<a href="index.php?module=Ledgers&view=WithholdingSections">Withholding sections</a>
</div>
{/strip}

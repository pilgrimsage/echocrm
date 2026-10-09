{strip}
<div class="container-fluid p-4">
	<h4 class="mb-3">{if $TYPE eq 'vendor'}Payables ageing (what we owe vendors){else}Receivables ageing (what customers owe us){/if}</h4>
	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="view" value="Ageing"/><input type="hidden" name="type" value="{$TYPE}"/>
		<div class="col-8 col-md-3"><label class="form-label">As at</label><input type="date" name="to" value="{$TO}" class="form-control"/></div>
		<div class="col-4 col-md-2"><button class="btn btn-primary w-100" type="submit">Show</button></div>
	</form>
	<div class="table-responsive"><table class="table table-sm table-hover align-middle">
		<thead><tr><th>{if $TYPE eq 'vendor'}Vendor{else}Customer{/if}</th><th class="text-end">Not due</th><th class="text-end">1-30</th><th class="text-end">31-60</th><th class="text-end">61-90</th><th class="text-end">Over 90</th><th class="text-end">Total</th><th class="text-end">Docs</th></tr></thead>
		<tbody>
		{foreach from=$ROWS item=R}
			<tr><td><a href="index.php?module=Ledgers&view=PartyStatement&type={$TYPE}&party={$R.party}">{$R.name|escape:'html'}</a></td>
			<td class="text-end">{$R.current_due|number_format:2}</td><td class="text-end">{$R.d30|number_format:2}</td><td class="text-end">{$R.d60|number_format:2}</td><td class="text-end">{$R.d90|number_format:2}</td><td class="text-end {if $R.d90plus > 0}text-danger{/if}">{$R.d90plus|number_format:2}</td><td class="text-end fw-semibold">{$R.total|number_format:2}</td><td class="text-end">{$R.documents}</td></tr>
		{foreachelse}<tr><td colspan="8" class="text-center text-muted py-4">Nothing outstanding.</td></tr>{/foreach}
		</tbody>
		{if $GRAND}<tfoot><tr class="fw-bold"><td>Total</td><td class="text-end">{$GRAND.current_due|number_format:2}</td><td class="text-end">{$GRAND.d30|number_format:2}</td><td class="text-end">{$GRAND.d60|number_format:2}</td><td class="text-end">{$GRAND.d90|number_format:2}</td><td class="text-end">{$GRAND.d90plus|number_format:2}</td><td class="text-end">{$GRAND.total|number_format:2}</td><td class="text-end">{$GRAND.documents}</td></tr></tfoot>{/if}
	</table></div>
	<div class="text-muted small">Shows the 200 largest balances. Days are counted from the document's due date (invoice date when none).</div>
</div>
{/strip}

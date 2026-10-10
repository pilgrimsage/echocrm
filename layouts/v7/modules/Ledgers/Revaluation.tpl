{strip}
<div class="container-fluid p-4" style="max-width:1000px">
	<h4 class="mb-3">Revalue foreign currency balances</h4>
	{if $MESSAGE}<div class="alert alert-success">{$MESSAGE|escape:'html'}</div>{/if}
	{if $ERROR}<div class="alert alert-danger">{$ERROR|escape:'html'}</div>{/if}
	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="view" value="Revaluation"/>
		<div class="col-12 col-md-3"><label class="form-label">As at</label><input type="date" name="as_at" value="{$AS_AT}" max="{date('Y-m-d')}" class="form-control"/></div>
		{foreach from=$GROUPS key=CID item=G}
		<div class="col-6 col-md-3"><label class="form-label">Closing rate {$G.code|escape:'html'} (per 1 base)</label><input type="number" step="0.000001" min="0" name="rate[{$CID}]" value="{$RATES[$CID]}" class="form-control"/></div>
		{/foreach}
		<div class="col-6 col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Preview</button></div>
	</form>
	{if not $GROUPS}<div class="text-muted py-3">No open foreign currency invoices or purchase orders at this date.</div>{else}
	<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Document</th><th>Type</th><th>Currency</th><th class="text-end">Open</th><th class="text-end">Booked rate</th><th class="text-end">Booked value</th><th class="text-end">Value at closing</th><th class="text-end">Gain / (loss)</th></tr></thead><tbody>
		{foreach from=$ROWS item=R}<tr><td>{$R.no|escape:'html'}</td><td>{if $R.side eq 'receivable'}Invoice{else}Purchase order{/if}</td><td>{$R.currency|escape:'html'}</td><td class="text-end">{$R.open|number_format:2}</td><td class="text-end">{$R.rate}</td><td class="text-end">{$R.book|number_format:2}</td><td class="text-end">{$R.now|number_format:2}</td><td class="text-end {if $R.gain lt 0}text-danger{/if}">{$R.gain|number_format:2}</td></tr>{/foreach}</tbody>
		<tfoot><tr class="fw-bold"><td colspan="7">Net unrealised {if $NET ge 0}gain{else}loss{/if}</td><td class="text-end">{$NET|number_format:2}</td></tr></tfoot></table></div>
	<form method="post" action="index.php"><input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="action" value="RevaluationAct"/><input type="hidden" name="as_at" value="{$AS_AT}"/>
		{foreach from=$RATES key=CID item=RATE}<input type="hidden" name="rate[{$CID}]" value="{$RATE}"/>{/foreach}
		<div class="small text-muted mb-2">One entry is posted on {$AS_AT} (receivable / payable per customer or vendor against Exchange Gain / Loss) and reversed the next day, so payments still clear the balance at the rate it was booked at. Posting again for the same date replaces it.</div>
		<button class="btn btn-primary" type="submit">Post revaluation</button></form>
	{/if}
	{if $HISTORY}<h6 class="mt-4">Earlier revaluations</h6>
	<table class="table table-sm" style="max-width:640px"><thead><tr><th>As at</th><th class="text-end">Documents</th><th class="text-end">Net gain / (loss)</th><th></th></tr></thead><tbody>
		{foreach from=$HISTORY item=H}<tr><td>{$H.as_at}</td><td class="text-end">{$H.documents}</td><td class="text-end">{$H.net|number_format:2}</td>
			<td class="text-end"><form method="post" action="index.php" class="d-inline"><input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="action" value="RevaluationAct"/><input type="hidden" name="do" value="undo"/><input type="hidden" name="as_at" value="{$H.as_at}"/><button class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove this revaluation and its reversal?');">Remove</button></form></td></tr>{/foreach}</tbody></table>{/if}
</div>
{/strip}

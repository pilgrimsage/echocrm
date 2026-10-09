{strip}
<div class="container-fluid p-4">
	<h4 class="mb-3">{vtranslate('LBL_LEDGER_STATEMENT', $MODULE)}</h4>
	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="view" value="Statement"/>
		<div class="col-12 col-md-5"><label class="form-label">{vtranslate('SINGLE_Ledgers', $MODULE)}</label>
			<select name="record" class="form-select">{foreach from=$LEDGERS key=ID item=NAME}<option value="{$ID}" {if $ID eq $LEDGER_ID}selected{/if}>{$NAME|escape:'html'}</option>{/foreach}</select></div>
		<div class="col-6 col-md-2"><label class="form-label">From</label><input type="date" name="from" value="{$FROM}" class="form-control"/></div>
		<div class="col-6 col-md-2"><label class="form-label">To</label><input type="date" name="to" value="{$TO}" class="form-control"/></div>
		<div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit">Show</button></div>
	</form>
	{if $BOOK}
	<div class="row g-3 mb-3">
		<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Opening balance</div><div class="fs-5">{$BOOK.opening|number_format:2}</div></div></div>
		<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Debits</div><div class="fs-5">{$BOOK.debit|number_format:2}</div></div></div>
		<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Credits</div><div class="fs-5">{$BOOK.credit|number_format:2}</div></div></div>
		<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Closing balance</div><div class="fs-5 fw-bold">{$BOOK.closing|number_format:2}</div></div></div>
	</div>
	<div class="table-responsive"><table class="table table-sm table-hover align-middle">
		<thead><tr><th>Date</th><th>Entry</th><th>Narration</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead>
		<tbody>
		{foreach from=$BOOK.rows item=ROW}
			<tr>
				<td>{$ROW.display_date}</td>
				<td>{$ROW.entry_no}{if $ROW.status eq 'Reversed'} <span class="badge bg-secondary">Reversed</span>{/if}</td>
				<td>{if $ROW.url}<a href="{$ROW.url}">{$ROW.narration|escape:'html'}</a>{else}{$ROW.narration|escape:'html'}{/if} <span class="text-muted">{$ROW.memo|escape:'html'}</span></td>
				<td class="text-end">{if $ROW.debit > 0}{$ROW.debit|number_format:2}{/if}</td>
				<td class="text-end">{if $ROW.credit > 0}{$ROW.credit|number_format:2}{/if}</td>
				<td class="text-end">{$ROW.running|number_format:2}</td>
			</tr>
		{foreachelse}
			<tr><td colspan="6" class="text-center text-muted py-4">No postings to this ledger in the period.</td></tr>
		{/foreach}
		</tbody>
	</table></div>
	<div class="text-muted small">Balances run in the ledger's normal direction ({vtranslate($BOOK.group, $MODULE)}).</div>
	{/if}
</div>
{/strip}

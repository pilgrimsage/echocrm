{strip}
<div class="container-fluid p-4">
	<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
		<h4 class="mb-0">Journal</h4>
		{if $CAN_EDIT}<a class="btn btn-primary btn-sm" href="index.php?module=Ledgers&view=JournalEntry">New journal entry</a>{/if}
	</div>
	{if $ERROR}<div class="alert alert-danger">{$ERROR|escape:'html'}</div>{/if}
	{if $LOCK_DATE}<div class="alert alert-warning py-2">Books are locked up to <strong>{$LOCK_DATE}</strong>.</div>{/if}
	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="view" value="Journal"/>
		<div class="col-6 col-md-2"><label class="form-label">From</label><input type="date" name="from" value="{$FROM}" class="form-control"/></div>
		<div class="col-6 col-md-2"><label class="form-label">To</label><input type="date" name="to" value="{$TO}" class="form-control"/></div>
		<div class="col-8 col-md-3"><label class="form-label">Source</label>
			<select name="source" class="form-select"><option value="">All</option>
			{foreach from=['Invoice','PurchaseOrder','SalesOrder','Payments','BankTransactions','BankAccounts','Ledgers','Manual'] item=S}<option value="{$S}" {if $S eq $SOURCE}selected{/if}>{$S}</option>{/foreach}</select></div>
		<div class="col-4 col-md-2"><button class="btn btn-primary w-100" type="submit">Show</button></div>
	</form>
	{foreach from=$ENTRIES item=E}
	<div class="border rounded mb-3">
		<div class="d-flex flex-wrap justify-content-between align-items-center bg-light px-3 py-2 gap-2">
			<div><strong>{$E.no}</strong> &nbsp;{$E.display_date} &nbsp;
				{if $E.url}<a href="{$E.url}">{$E.narration|escape:'html'}</a>{else}{$E.narration|escape:'html'}{/if}
				{if $E.entry_type eq 'manual'}<span class="badge bg-info text-dark">Manual</span>{/if}
				{if $E.entry_type eq 'reversal'}<span class="badge bg-warning text-dark">Reversal</span>{/if}
				{if $E.status eq 'Reversed'}<span class="badge bg-secondary">Reversed</span>{/if}</div>
			{if $E.can_reverse && $CAN_EDIT}
			<form method="post" action="index.php" class="d-flex gap-2 align-items-center">
				<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="action" value="JournalReverse"/><input type="hidden" name="entry" value="{$E.entry_id}"/>
				<input type="date" name="date" value="{$TO}" class="form-control form-control-sm" style="width:auto"/>
				<button type="submit" class="btn btn-outline-danger btn-sm">Reverse</button>
			</form>
			{/if}
		</div>
		<table class="table table-sm mb-0"><tbody>
		{foreach from=$E.lines item=L}
			<tr><td style="width:55%"><a href="index.php?module=Ledgers&view=Statement&record={$L.ledgersid}">{$L.ledger_name|escape:'html'}</a> <span class="text-muted">{$L.memo|escape:'html'}</span></td>
			<td class="text-end" style="width:22%">{if $L.debit > 0}{$L.debit|number_format:2}{/if}</td><td class="text-end" style="width:22%">{if $L.credit > 0}{$L.credit|number_format:2}{/if}</td></tr>
		{/foreach}
		</tbody></table>
	</div>
	{foreachelse}
		<div class="alert alert-info">No entries in this period.</div>
	{/foreach}
</div>
{/strip}

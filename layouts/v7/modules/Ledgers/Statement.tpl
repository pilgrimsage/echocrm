{strip}
<div class="container-fluid p-4">
	<h4 class="mb-3">{vtranslate('LBL_LEDGER_STATEMENT', $MODULE)}</h4>
	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="Ledgers"/>
		<input type="hidden" name="view" value="Statement"/>
		<div class="col-12 col-md-5">
			<label class="form-label">{vtranslate('SINGLE_Ledgers', $MODULE)}</label>
			<select name="record" class="form-select">{foreach from=$LEDGERS key=ID item=NAME}<option value="{$ID}" {if $ID eq $LEDGER_ID}selected{/if}>{$NAME|escape:'html'}</option>{/foreach}</select>
		</div>
		<div class="col-6 col-md-2"><label class="form-label">From</label><input type="date" name="from" value="{$FROM}" class="form-control"/></div>
		<div class="col-6 col-md-2"><label class="form-label">To</label><input type="date" name="to" value="{$TO}" class="form-control"/></div>
		<div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit">Show</button></div>
	</form>
	<div class="row g-3 mb-3">
		<div class="col-4"><div class="border rounded p-3"><div class="text-muted small">Money in</div><div class="fs-5 text-success">{$TOTAL_IN|number_format:2}</div></div></div>
		<div class="col-4"><div class="border rounded p-3"><div class="text-muted small">Money out</div><div class="fs-5 text-danger">{$TOTAL_OUT|number_format:2}</div></div></div>
		<div class="col-4"><div class="border rounded p-3"><div class="text-muted small">Net</div><div class="fs-5 fw-bold">{($TOTAL_IN-$TOTAL_OUT)|number_format:2}</div></div></div>
	</div>
	<div class="table-responsive">
		<table class="table table-sm table-hover align-middle">
			<thead><tr><th>Date</th><th>Transaction</th><th>Ledger</th><th>Account</th><th>Reference / narration</th><th class="text-end">In</th><th class="text-end">Out</th></tr></thead>
			<tbody>
			{foreach from=$ROWS item=ROW}
				<tr>
					<td>{$ROW.display_date}</td>
					<td><a href="index.php?module=BankTransactions&view=Detail&record={$ROW.id}">{$ROW.transaction_no|escape:'html'}</a></td>
					<td>{$ROW.ledger_name|escape:'html'}</td>
					<td>{$ROW.account_name|escape:'html'}</td>
					<td>{$ROW.reference_no|escape:'html'} <span class="text-muted">{$ROW.narration|escape:'html'}</span></td>
					<td class="text-end">{if $ROW.direction eq 'In'}{$ROW.amount|number_format:2}{/if}</td>
					<td class="text-end">{if $ROW.direction eq 'Out'}{$ROW.amount|number_format:2}{/if}</td>
				</tr>
			{foreachelse}
				<tr><td colspan="7" class="text-center text-muted py-4">No transactions posted to this ledger in the period.</td></tr>
			{/foreach}
			</tbody>
		</table>
	</div>
</div>
{/strip}

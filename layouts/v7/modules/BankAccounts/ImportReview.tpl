{strip}
<div class="container-fluid p-4">
	<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
		<h4 class="mb-0">Statement review - {$BATCH.account_name|escape:'html'}</h4>
		<div class="d-flex gap-2"><a class="btn btn-outline-secondary btn-sm" href="index.php?module=BankAccounts&view=Statement&record={$BATCH.bank_account}">Account statement</a>
			<a class="btn btn-outline-primary btn-sm" href="index.php?module=BankAccounts&view=ImportStatement&account={$BATCH.bank_account}">Import another file</a></div>
	</div>
	<div class="text-muted small mb-3">{$BATCH.filename|escape:'html'} - lines dated {$BATCH.from_date} to {$BATCH.to_date}{if $BATCH.closing_balance !== null} - closing balance on the file {$BATCH.closing_balance|number_format:2}{/if}</div>
	{if $MESSAGE}<div class="alert alert-success py-2">{$MESSAGE|escape:'html'}</div>{/if}
	{if $ERROR}<div class="alert alert-danger py-2">{$ERROR|escape:'html'}</div>{/if}
	<div class="row g-2 mb-3">
		{foreach from=$BATCH.counts key=S item=N}<div class="col-6 col-md-2"><div class="border rounded p-2 text-center"><div class="fs-5">{$N}</div><div class="small text-muted">{$S}</div></div></div>{/foreach}
		<div class="col-12 col-md-2"><div class="border rounded p-2 text-center"><div class="fs-6">{$BATCH.reconciled_balance|number_format:2}</div><div class="small text-muted">Reconciled balance in books</div></div></div>
	</div>
	<div class="d-flex flex-wrap gap-2 mb-3">
		{if $BATCH.counts.Suggested > 0}<form method="post" action="index.php"><input type="hidden" name="module" value="BankAccounts"/><input type="hidden" name="action" value="ImportAct"/><input type="hidden" name="batch" value="{$BATCH.batch_id}"/><input type="hidden" name="do" value="confirm_all"/><button class="btn btn-success btn-sm">Confirm all {$BATCH.counts.Suggested} suggested matches</button></form>{/if}
		<form method="post" action="index.php"><input type="hidden" name="module" value="BankAccounts"/><input type="hidden" name="action" value="ImportAct"/><input type="hidden" name="batch" value="{$BATCH.batch_id}"/><input type="hidden" name="do" value="create_ruled"/><button class="btn btn-outline-success btn-sm" title="Books the unmatched lines that one of your saved rules recognises">Book lines from saved rules</button></form>
		<form method="post" action="index.php"><input type="hidden" name="module" value="BankAccounts"/><input type="hidden" name="action" value="ImportAct"/><input type="hidden" name="batch" value="{$BATCH.batch_id}"/><input type="hidden" name="do" value="rematch"/><button class="btn btn-outline-secondary btn-sm">Run matching again</button></form>
		<a class="btn btn-outline-secondary btn-sm" href="index.php?module=BankAccounts&view=ImportReview&batch={$BATCH.batch_id}&show={if $SHOW eq 'all'}open{else}all{/if}">{if $SHOW eq 'all'}Show only open lines{else}Show all lines{/if}</a>
	</div>
	<div class="table-responsive"><table class="table table-sm align-middle">
		<thead><tr><th>Date</th><th>Description</th><th>Reference</th><th class="text-end">In</th><th class="text-end">Out</th><th>Status / match</th><th style="width:44%">Action</th></tr></thead>
		<tbody>
		{foreach from=$BATCH.lines item=L}{if $SHOW eq 'all' || ($L.status neq 'Matched' && $L.status neq 'Created' && $L.status neq 'Ignored')}
			<tr class="{if $L.status eq 'Suggested'}table-info{elseif $L.status eq 'Unmatched'}table-warning{elseif $L.status eq 'Ignored'}text-muted{/if}">
				<td>{$L.display_date}</td><td>{$L.description|escape:'html'}</td><td>{$L.reference|escape:'html'}</td>
				<td class="text-end">{if $L.direction eq 'In'}{$L.amount|number_format:2}{/if}</td><td class="text-end">{if $L.direction eq 'Out'}{$L.amount|number_format:2}{/if}</td>
				<td>{$L.status}{if $L.transaction_no} <a href="index.php?module=BankTransactions&view=Detail&record={$L.matched_transaction}">{$L.transaction_no}</a> <span class="small text-muted">{$L.txn_date} {$L.txn_narration|escape:'html'}</span>{/if}</td>
				<td>
				{if $L.status eq 'Suggested'}
					<form method="post" action="index.php" class="d-inline"><input type="hidden" name="module" value="BankAccounts"/><input type="hidden" name="action" value="ImportAct"/><input type="hidden" name="batch" value="{$BATCH.batch_id}"/><input type="hidden" name="line" value="{$L.line_id}"/>
						<button name="do" value="confirm" class="btn btn-success btn-sm">Confirm</button> <button name="do" value="reject" class="btn btn-outline-secondary btn-sm">Not this one</button></form>
				{elseif $L.status eq 'Unmatched'}
					{if $L.candidates}<form method="post" action="index.php" class="d-flex gap-1 mb-1"><input type="hidden" name="module" value="BankAccounts"/><input type="hidden" name="action" value="ImportAct"/><input type="hidden" name="batch" value="{$BATCH.batch_id}"/><input type="hidden" name="line" value="{$L.line_id}"/><input type="hidden" name="do" value="choose"/>
						<select name="transaction" class="form-select form-select-sm">{foreach from=$L.candidates item=C}<option value="{$C.id}">{$C.transaction_no} {$C.transaction_date} {$C.narration|escape:'html'|truncate:30}</option>{/foreach}</select><button class="btn btn-outline-success btn-sm">Match</button></form>{/if}
					<form method="post" action="index.php" class="d-flex flex-wrap gap-1"><input type="hidden" name="module" value="BankAccounts"/><input type="hidden" name="action" value="ImportAct"/><input type="hidden" name="batch" value="{$BATCH.batch_id}"/><input type="hidden" name="line" value="{$L.line_id}"/>
						<select name="ledger" class="form-select form-select-sm" style="max-width:230px"><option value="">Ledger...</option>{foreach from=$LEDGERS key=ID item=NAME}<option value="{$ID}" {if $ID eq $L.rule_ledger}selected{/if}>{$NAME|escape:'html'}</option>{/foreach}</select>
						<input type="text" name="rule_text" value="{$L.rule_text|escape:'html'}" placeholder="Remember lines containing..." class="form-control form-control-sm" style="max-width:190px"/>
						<span class="form-check small align-self-center"><input type="checkbox" class="form-check-input" name="remember" value="1" id="r{$L.line_id}"/><label class="form-check-label" for="r{$L.line_id}">remember</label></span>
						<button name="do" value="create" class="btn btn-primary btn-sm">Book it</button> <button name="do" value="ignore" class="btn btn-outline-secondary btn-sm">Ignore</button></form>
					{if $L.rule_ledger_name}<div class="small text-muted mt-1">Rule "{$L.rule_text|escape:'html'}" suggests {$L.rule_ledger_name|escape:'html'}</div>{/if}
				{elseif $L.status eq 'Matched' || $L.status eq 'Created' || $L.status eq 'Ignored'}
					<form method="post" action="index.php" class="d-inline"><input type="hidden" name="module" value="BankAccounts"/><input type="hidden" name="action" value="ImportAct"/><input type="hidden" name="batch" value="{$BATCH.batch_id}"/><input type="hidden" name="line" value="{$L.line_id}"/><button name="do" value="undo" class="btn btn-link btn-sm text-muted">Undo</button></form>
				{/if}
				</td>
			</tr>
		{/if}{/foreach}
		</tbody>
	</table></div>
</div>
{/strip}

{strip}
<div class="container-fluid p-4" id="bankStatement" data-reconciled-balance="{$RECONCILED_BALANCE}">
	<div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
		<h4 class="mb-0">{vtranslate('LBL_STATEMENT', $MODULE)}</h4>
		{if $STATEMENT}
		<div class="d-flex gap-2">
			<a class="btn btn-outline-secondary btn-sm" href="index.php?module=BankAccounts&action=StatementExport&record={$ACCOUNT_ID}&from={$FROM}&to={$TO}{if $UNRECONCILED_ONLY}&unreconciled=1{/if}">CSV</a>
			<a class="btn btn-primary btn-sm" href="index.php?module=BankAccounts&view=Transfer&from_account={$ACCOUNT_ID}">{vtranslate('LBL_TRANSFER_MONEY', $MODULE)}</a>
		</div>
		{/if}
	</div>

	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="BankAccounts"/>
		<input type="hidden" name="view" value="Statement"/>
		<div class="col-12 col-md-4">
			<label class="form-label">{vtranslate('SINGLE_BankAccounts', $MODULE)}</label>
			<select name="record" class="form-select">
				{foreach from=$ACCOUNTS key=ID item=NAME}<option value="{$ID}" {if $ID eq $ACCOUNT_ID}selected{/if}>{$NAME|escape:'html'}</option>{/foreach}
			</select>
		</div>
		<div class="col-6 col-md-2"><label class="form-label">From</label><input type="date" name="from" value="{$FROM}" class="form-control"/></div>
		<div class="col-6 col-md-2"><label class="form-label">To</label><input type="date" name="to" value="{$TO}" class="form-control"/></div>
		<div class="col-12 col-md-2">
			<div class="form-check mb-2"><input type="checkbox" class="form-check-input" name="unreconciled" value="1" id="unrec" {if $UNRECONCILED_ONLY}checked{/if}/><label class="form-check-label" for="unrec">Not reconciled only</label></div>
		</div>
		<div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit">Show</button></div>
	</form>

	{if !$STATEMENT}
		<div class="alert alert-info">No bank account yet. Add one first.</div>
	{else}
		<div class="row g-3 mb-3">
			<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Opening balance</div><div class="fs-5">{$STATEMENT.opening|number_format:2}</div></div></div>
			<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Money in</div><div class="fs-5 text-success">{$STATEMENT.in|number_format:2}</div></div></div>
			<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Money out</div><div class="fs-5 text-danger">{$STATEMENT.out|number_format:2}</div></div></div>
			<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Closing balance</div><div class="fs-5 fw-bold">{$STATEMENT.closing|number_format:2}</div></div></div>
		</div>

		<div class="table-responsive">
			<table class="table table-sm table-hover align-middle">
				<thead><tr>
					{if $CAN_EDIT}<th style="width:32px"><input type="checkbox" id="selectAllRows" title="Select all"/></th>{/if}
					<th>Date</th><th>Transaction</th><th>Type</th><th>Reference / narration</th><th class="text-end">In</th><th class="text-end">Out</th><th class="text-end">Balance</th><th>Reconciled</th>
				</tr></thead>
				<tbody>
				{foreach from=$STATEMENT.rows item=ROW}
					<tr>
						{if $CAN_EDIT}<td><input type="checkbox" class="rowSelect" value="{$ROW.id}"/></td>{/if}
						<td>{$ROW.display_date}</td>
						<td><a href="index.php?module=BankTransactions&view=Detail&record={$ROW.id}">{$ROW.transaction_no|escape:'html'}</a></td>
						<td>{vtranslate($ROW.transaction_type, 'BankTransactions')}</td>
						<td>{$ROW.reference_no|escape:'html'} <span class="text-muted">{$ROW.narration|escape:'html'}</span></td>
						<td class="text-end">{if $ROW.direction eq 'In'}{$ROW.amount|number_format:2}{/if}</td>
						<td class="text-end">{if $ROW.direction eq 'Out'}{$ROW.amount|number_format:2}{/if}</td>
						<td class="text-end">{$ROW.running|number_format:2}</td>
						<td class="reconciledCell">{if $ROW.reconciled}<span class="badge bg-success">{$ROW.reconciled_date}</span>{else}<span class="badge bg-secondary">No</span>{/if}</td>
					</tr>
				{foreachelse}
					<tr><td colspan="9" class="text-center text-muted py-4">No transactions in this period.</td></tr>
				{/foreach}
				</tbody>
			</table>
		</div>

		{if $CAN_EDIT}
		<div class="border rounded p-3 mt-3">
			<div class="d-flex flex-wrap gap-2 align-items-end">
				<div>
					<div class="fw-semibold mb-1">Reconcile with the bank statement</div>
					<div class="small text-muted">Tick the transactions that appear on the bank's statement, then mark them reconciled. Reconciled transactions are locked.</div>
				</div>
				<div class="ms-auto d-flex gap-2">
					<button class="btn btn-success btn-sm" id="markReconciled" type="button">Mark selected reconciled</button>
					<button class="btn btn-outline-secondary btn-sm" id="markUnreconciled" type="button">Un-reconcile selected</button>
				</div>
			</div>
			<div class="row g-2 mt-2 align-items-end">
				<div class="col-12 col-md-4"><label class="form-label">Closing balance on the bank's statement</label><input type="number" step="0.01" id="bankClosing" class="form-control"/></div>
				<div class="col-6 col-md-4"><div class="text-muted small">Reconciled balance in the books</div><div class="fs-5" id="reconciledBalance">{$RECONCILED_BALANCE|number_format:2}</div></div>
				<div class="col-6 col-md-4"><div class="text-muted small">Difference</div><div class="fs-5" id="reconcileDifference">-</div></div>
			</div>
		</div>
		{/if}
	{/if}
</div>
<script type="text/javascript">
(function () {
	var root = document.getElementById('bankStatement');
	if (!root) { return; }
	var reconciled = parseFloat(root.getAttribute('data-reconciled-balance')) || 0;
	function money(value) { return value.toLocaleString(undefined, {ldelim}minimumFractionDigits: 2, maximumFractionDigits: 2{rdelim}); }
	function showDifference() {
		var closing = document.getElementById('bankClosing');
		var out = document.getElementById('reconcileDifference');
		if (!closing || !out) { return; }
		if (closing.value === '') { out.textContent = '-'; out.className = 'fs-5'; return; }
		var diff = Math.round((parseFloat(closing.value) - reconciled) * 100) / 100;
		out.textContent = money(diff);
		out.className = 'fs-5 ' + (diff === 0 ? 'text-success' : 'text-danger');
	}
	var closingInput = document.getElementById('bankClosing');
	if (closingInput) { closingInput.addEventListener('input', showDifference); }
	var all = document.getElementById('selectAllRows');
	if (all) { all.addEventListener('change', function () { root.querySelectorAll('.rowSelect').forEach(function (box) { box.checked = all.checked; }); }); }
	function send(state) {
		var ids = Array.prototype.map.call(root.querySelectorAll('.rowSelect:checked'), function (box) { return box.value; });
		if (!ids.length) { app.helper.showErrorNotification({ldelim}message: 'Select the transactions first.'{rdelim}); return; }
		app.request.post({ldelim}data: {ldelim}module: 'BankAccounts', action: 'Reconcile', ids: ids, state: state ? 1 : 0{rdelim}{rdelim}).then(function (error, data) {
			if (error) { app.helper.showErrorNotification({ldelim}message: error.message || 'Could not update.'{rdelim}); return; }
			window.location.reload();
		});
	}
	var mark = document.getElementById('markReconciled'), unmark = document.getElementById('markUnreconciled');
	if (mark) { mark.addEventListener('click', function () { send(true); }); }
	if (unmark) { unmark.addEventListener('click', function () { send(false); }); }
	showDifference();
})();
</script>
{/strip}

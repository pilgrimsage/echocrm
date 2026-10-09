{strip}
<div class="container-fluid p-4" style="max-width:760px">
	<h4 class="mb-3">{vtranslate('LBL_TRANSFER_MONEY', $MODULE)}</h4>
	{if $ERROR}<div class="alert alert-danger">{$ERROR|escape:'html'}</div>{/if}
	{if count($ACCOUNTS) lt 2}
		<div class="alert alert-info">A transfer needs at least two active accounts.</div>
	{else}
	<form method="post" action="index.php" class="row g-3">
		<input type="hidden" name="module" value="BankAccounts"/>
		<input type="hidden" name="action" value="Transfer"/>
		<div class="col-12 col-md-6">
			<label class="form-label">From account <span class="text-danger">*</span></label>
			<select name="from_account" class="form-select" required>
				<option value="">Select</option>
				{foreach from=$ACCOUNTS key=ID item=ACCOUNT}<option value="{$ID}" {if $ID eq $FROM_ACCOUNT}selected{/if}>{$ACCOUNT.name|escape:'html'} ({$ACCOUNT.balance|number_format:2})</option>{/foreach}
			</select>
		</div>
		<div class="col-12 col-md-6">
			<label class="form-label">To account <span class="text-danger">*</span></label>
			<select name="to_account" class="form-select" required>
				<option value="">Select</option>
				{foreach from=$ACCOUNTS key=ID item=ACCOUNT}<option value="{$ID}">{$ACCOUNT.name|escape:'html'} ({$ACCOUNT.balance|number_format:2})</option>{/foreach}
			</select>
		</div>
		<div class="col-6 col-md-4"><label class="form-label">Amount <span class="text-danger">*</span></label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required/></div>
		<div class="col-6 col-md-4"><label class="form-label">Date <span class="text-danger">*</span></label><input type="date" name="transfer_date" value="{$TODAY}" class="form-control" required/></div>
		<div class="col-12 col-md-4"><label class="form-label">Reference / UTR</label><input type="text" name="reference_no" class="form-control" maxlength="100"/></div>
		<div class="col-12"><label class="form-label">Narration</label><input type="text" name="narration" class="form-control" maxlength="200"/></div>
		<div class="col-12 d-flex gap-2">
			<button type="submit" class="btn btn-primary">Transfer</button>
			<a href="index.php?module=BankAccounts&view=List" class="btn btn-outline-secondary">Cancel</a>
		</div>
	</form>
	{/if}
</div>
{/strip}

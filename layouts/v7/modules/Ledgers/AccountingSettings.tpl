{strip}
<div class="container-fluid p-4" style="max-width:900px">
	<h4 class="mb-3">Accounting settings</h4>
	{if $SAVED}<div class="alert alert-success">Saved.</div>{/if}
	{if $MESSAGE}<div class="alert alert-info">{$MESSAGE|escape:'html'}</div>{/if}
	<form method="post" action="index.php" class="row g-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="action" value="SettingsSave"/>
		<div class="col-12"><label class="form-label">Lock the books up to</label>
			<input type="date" name="lock_date" value="{$LOCK_DATE}" class="form-control"/>
			<div class="form-text">Nothing dated on or before this day can be posted, changed, deleted or reversed (invoices, purchase orders, notes, payments, bank transactions and journal entries). Leave empty to keep the books open.</div></div>
		<div class="col-12"><label class="form-label">Books start on</label>
			<input type="date" name="books_start" value="{$BOOKS_START}" class="form-control"/>
			<div class="form-text">Ledger opening balances are booked on this date (now: {$OPENING_DATE}). Leave empty for 1 April of the current financial year.</div></div>
		<div class="col-12"><hr/><h6>Posting accounts</h6>
			<div class="form-text mb-2">Which ledger each kind of posting goes to. Change a line to post to your own ledger (for example Sales to "Service Revenue"). History already posted stays where it was until you rebuild the postings.</div>
			<div class="row g-2">
			{foreach from=$ROLES key=KEY item=ROLE}
				<div class="col-12 col-md-6"><label class="form-label small mb-0">{$ROLE.label|escape:'html'}</label>
					<select name="posting[{$KEY}]" class="form-select form-select-sm">{foreach from=$LEDGERS key=ID item=NAME}<option value="{$ID}" {if $ID eq $ROLE.ledger}selected{/if}>{$NAME|escape:'html'}</option>{/foreach}</select></div>
			{/foreach}
			</div></div>
		<div class="col-12"><button class="btn btn-primary" type="submit">Save</button></div>
	</form>
	<hr class="my-4"/>
	<h6>Start from an industry chart of accounts</h6>
	<div class="form-text mb-3">Adds the ledgers typical for the business. Existing ledgers are kept, nothing is removed, and posting accounts you have already set are not changed.</div>
	<div class="row g-3">
	{foreach from=$TEMPLATES key=TID item=T}
		<div class="col-12 col-md-6"><form method="post" action="index.php" class="border rounded p-3 h-100 d-flex flex-column">
			<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="action" value="TemplateApply"/><input type="hidden" name="template" value="{$TID}"/>
			<div class="fw-semibold">{$T.label|escape:'html'}</div><div class="small text-muted flex-grow-1">{$T.description|escape:'html'}</div>
			<div><button class="btn btn-outline-primary btn-sm mt-2" type="submit">Add these ledgers</button></div>
		</form></div>
	{/foreach}
	</div>
</div>
{/strip}

{strip}
<div class="container-fluid p-4" style="max-width:720px">
	<h4 class="mb-3">Accounting settings</h4>
	{if $SAVED}<div class="alert alert-success">Saved.</div>{/if}
	<form method="post" action="index.php" class="row g-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="action" value="SettingsSave"/>
		<div class="col-12"><label class="form-label">Lock the books up to</label>
			<input type="date" name="lock_date" value="{$LOCK_DATE}" class="form-control"/>
			<div class="form-text">Nothing dated on or before this day can be posted, changed, deleted or reversed (invoices, purchase orders, notes, payments, bank transactions and journal entries). Leave empty to keep the books open.</div></div>
		<div class="col-12"><label class="form-label">Books start on</label>
			<input type="date" name="books_start" value="{$BOOKS_START}" class="form-control"/>
			<div class="form-text">Ledger opening balances are booked on this date (now: {$OPENING_DATE}). Leave empty for 1 April of the current financial year.</div></div>
		<div class="col-12"><button class="btn btn-primary" type="submit">Save</button></div>
	</form>
</div>
{/strip}

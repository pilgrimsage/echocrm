{strip}
<div class="container-fluid p-4" style="max-width:1000px">
	<h4 class="mb-3">Import bank statement</h4>
	{if $ERROR}<div class="alert alert-danger">{$ERROR|escape:'html'}</div>{/if}
{if !$TOKEN}
	<form method="post" action="index.php" enctype="multipart/form-data" class="row g-3">
		<input type="hidden" name="module" value="BankAccounts"/><input type="hidden" name="action" value="ImportUpload"/>
		<div class="col-12 col-md-5"><label class="form-label">Bank account <span class="text-danger">*</span></label>
			<select name="account" class="form-select" required><option value="">Select</option>{foreach from=$ACCOUNTS key=ID item=NAME}<option value="{$ID}" {if $ID eq $ACCOUNT_ID}selected{/if}>{$NAME|escape:'html'}</option>{/foreach}</select></div>
		<div class="col-12 col-md-7"><label class="form-label">Statement file (CSV, up to 5 MB) <span class="text-danger">*</span></label><input type="file" name="statement" accept=".csv,.txt" class="form-control" required/></div>
		<div class="col-12 text-muted small">Download the statement as CSV from your bank's portal. Next you confirm which column is which, then the lines are matched with the transactions you already recorded. Lines imported before are skipped.</div>
		<div class="col-12"><button class="btn btn-primary" type="submit">Upload</button> <a class="btn btn-outline-secondary" href="index.php?module=BankAccounts&view=List">Cancel</a></div>
	</form>
{else}
	<p class="text-muted">File: <strong>{$FILENAME|escape:'html'}</strong>. Check the preview and say which column holds what.</p>
	<div class="table-responsive mb-3"><table class="table table-sm table-bordered">
		{foreach from=$PREVIEW item=ROW key=I}<tr class="{if $I eq $HEADER_ROW}table-primary fw-semibold{/if}"><th class="text-muted" style="width:30px">{$I+1}</th>{foreach from=$ROW item=CELL}<td>{$CELL|escape:'html'}</td>{/foreach}</tr>{/foreach}
	</table></div>
	<form method="post" action="index.php" class="row g-3">
		<input type="hidden" name="module" value="BankAccounts"/><input type="hidden" name="action" value="ImportStage"/>
		<input type="hidden" name="account" value="{$ACCOUNT_ID}"/><input type="hidden" name="file" value="{$TOKEN}"/><input type="hidden" name="name" value="{$FILENAME|escape:'html'}"/>
		<div class="col-6 col-md-3"><label class="form-label">Header row number</label><input type="number" min="1" name="header_row_display" value="{$HEADER_ROW+1}" class="form-control" onchange="this.form.header_row.value=this.value-1"/><input type="hidden" name="header_row" value="{$HEADER_ROW}"/></div>
		<div class="col-6 col-md-3"><label class="form-label">Date format</label><select name="date_format" class="form-select"><option value="auto">Detect</option><option value="d-m-Y">31-12-2026</option><option value="d/m/Y">31/12/2026</option><option value="m/d/Y">12/31/2026</option><option value="Y-m-d">2026-12-31</option><option value="d-M-Y">31-Dec-2026</option><option value="d/m/y">31/12/26</option></select></div>
		{foreach from=['date'=>'Date','description'=>'Description / narration','reference'=>'Reference / cheque / UTR','debit'=>'Debit / withdrawal','credit'=>'Credit / deposit','amount'=>'Amount (one signed column)','balance'=>'Balance'] key=KEY item=LABEL}
		<div class="col-6 col-md-3"><label class="form-label">{$LABEL}</label>
			<select name="map_{$KEY}" class="form-select"><option value="">- none -</option>{section name=c start=0 loop=$COLUMNS}<option value="{$smarty.section.c.index}" {if $MAPPING[$KEY] !== null && $MAPPING[$KEY] == $smarty.section.c.index}selected{/if}>Column {$smarty.section.c.index+1}{if $PREVIEW[$HEADER_ROW][$smarty.section.c.index]} - {$PREVIEW[$HEADER_ROW][$smarty.section.c.index]|escape:'html'|truncate:24}{/if}</option>{/section}</select></div>
		{/foreach}
		<div class="col-6 col-md-3"><label class="form-label">With one amount column</label><select name="amount_sign" class="form-select"><option value="negative_is_out">Negative = money out</option><option value="positive_is_out">Positive = money out</option></select></div>
		<div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Import and match</button><a class="btn btn-outline-secondary" href="index.php?module=BankAccounts&view=ImportStatement&account={$ACCOUNT_ID}">Start over</a></div>
	</form>
{/if}
</div>
{/strip}

{strip}
<div class="container-fluid p-4" style="max-width:760px">
	<h4 class="mb-1">Make invoice {$INVOICE.invoice_no|escape:'html'} recurring</h4>
	<div class="text-muted mb-3">{$INVOICE.accountname|escape:'html'} - {$INVOICE.total|number_format:2} - {$INVOICE.subject|escape:'html'}</div>
	{if $ERROR}<div class="alert alert-danger">{$ERROR|escape:'html'}</div>{/if}
	<form method="post" action="index.php" class="row g-3">
		<input type="hidden" name="module" value="Invoice"/><input type="hidden" name="action" value="RecurringSave"/><input type="hidden" name="record" value="{$INVOICE_ID}"/>
		<div class="col-12 col-md-4"><label class="form-label">Repeat</label><select name="frequency" class="form-select">{foreach from=$FREQUENCIES item=F}<option value="{$F}" {if $F eq 'Monthly'}selected{/if}>{$F}</option>{/foreach}</select></div>
		<div class="col-12 col-md-4"><label class="form-label">First new invoice on</label><input type="date" name="start_date" value="{$START}" class="form-control" required/></div>
		<div class="col-12 col-md-4"><label class="form-label">New invoices are</label><select name="invoice_status" class="form-select"><option value="Created">Created (draft, I will approve)</option><option value="Approved">Approved (booked straight away)</option><option value="Sent">Sent</option></select></div>
		<div class="col-12 col-md-4"><label class="form-label">Stop after (date)</label><input type="date" name="end_date" class="form-control"/></div>
		<div class="col-12 col-md-4"><label class="form-label">or after this many invoices</label><input type="number" min="1" name="max_count" class="form-control"/></div>
		<div class="col-12"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control" maxlength="200"/></div>
		<div class="col-12 text-muted small">Each invoice copies this one (customer, items, prices, taxes, terms, cost centre). The invoice date is the scheduled date and the due date keeps this invoice's payment period. Leave both stop fields empty to continue until you end it.</div>
		<div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Create schedule</button><a class="btn btn-outline-secondary" href="index.php?module=Invoice&view=Detail&record={$INVOICE_ID}">Cancel</a></div>
	</form>
</div>
{/strip}

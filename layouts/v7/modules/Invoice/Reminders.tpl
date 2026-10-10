{strip}
<div class="container-fluid p-4">
	<h4 class="mb-3">Payment reminders</h4>
	{if $MESSAGE}<div class="alert alert-success py-2">{$MESSAGE|escape:'html'}</div>{/if}
	{if $ERROR}<div class="alert alert-danger py-2">{$ERROR|escape:'html'}</div>{/if}
	{if !$MAIL_READY}<div class="alert alert-warning py-2">No outgoing mail server is set up, so reminders cannot be sent yet (Settings &gt; Outgoing Server). Attempts are logged as "No mail server" and can be repeated.</div>{/if}
	<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
		{if $CAN_EDIT && $DUE_COUNT > 0}<form method="post" action="index.php"><input type="hidden" name="module" value="Invoice"/><input type="hidden" name="action" value="ReminderAct"/><button name="do" value="send_all" class="btn btn-primary btn-sm" onclick="return confirm('Send {$DUE_COUNT} reminder(s) now?');">Send all {$DUE_COUNT} due reminders</button></form>{/if}
		{if $IS_ADMIN}<form method="post" action="index.php" class="d-flex align-items-center gap-2"><input type="hidden" name="module" value="Invoice"/><input type="hidden" name="action" value="ReminderAct"/><input type="hidden" name="do" value="auto"/>
			<div class="form-check"><input type="checkbox" class="form-check-input" name="enabled" value="1" id="auto" {if $AUTO}checked{/if}/><label class="form-check-label" for="auto">Send due reminders automatically every day</label></div><button class="btn btn-outline-secondary btn-sm">Save</button></form>{/if}
	</div>
	<h6>Unpaid invoices</h6>
	<div class="table-responsive"><table class="table table-sm align-middle">
		<thead><tr><th>Invoice</th><th>Customer</th><th>Due</th><th class="text-end">Balance</th><th class="text-end">Days</th><th>Last reminder sent</th><th>Next reminder</th><th>To</th><th></th></tr></thead>
		<tbody>
		{foreach from=$ROWS item=R}
			<tr class="{if $R.days > 0}table-warning{/if}"><td><a href="index.php?module=Invoice&view=Detail&record={$R.invoiceid}">{$R.invoice_no|escape:'html'}</a></td><td>{$R.accountname|escape:'html'}</td><td>{$R.due}</td><td class="text-end">{$R.balance|number_format:2}</td>
			<td class="text-end">{if $R.days > 0}{$R.days} overdue{elseif $R.days eq 0}due today{else}in {-$R.days}{/if}</td><td>{$R.last_sent}</td>
			<td>{if $R.rule}{$R.rule.label|escape:'html'}{else}<span class="text-muted">-</span>{/if}</td><td>{if $R.email}{$R.email|escape:'html'}{else}<span class="text-danger">no email</span>{/if}</td>
			<td class="text-end">{if $CAN_EDIT && $R.rule}<form method="post" action="index.php" class="d-inline"><input type="hidden" name="module" value="Invoice"/><input type="hidden" name="action" value="ReminderAct"/><input type="hidden" name="invoice" value="{$R.invoiceid}"/><button name="do" value="send" class="btn btn-outline-primary btn-sm">Send now</button></form>{/if}</td></tr>
		{foreachelse}<tr><td colspan="9" class="text-center text-muted py-4">No unpaid invoices.</td></tr>{/foreach}
		</tbody>
	</table></div>
	<h6 class="mt-4">Reminder rules</h6>
	<div class="text-muted small mb-2">Days relative to the due date (negative = before). Placeholders: {ldelim}customer{rdelim} {ldelim}invoice_no{rdelim} {ldelim}invoice_date{rdelim} {ldelim}due_date{rdelim} {ldelim}amount_due{rdelim} {ldelim}days_overdue{rdelim} {ldelim}company{rdelim}. When several rules are due for an invoice only the latest is sent.</div>
	{foreach from=$RULES item=U}
	<form method="post" action="index.php" class="border rounded p-2 mb-2 row g-2 align-items-start"><input type="hidden" name="module" value="Invoice"/><input type="hidden" name="action" value="ReminderAct"/><input type="hidden" name="rule" value="{$U.rule_id}"/>
		<div class="col-12 col-md-3"><input type="text" name="label" value="{$U.label|escape:'html'}" class="form-control form-control-sm mb-1"/><div class="d-flex gap-2 align-items-center"><input type="number" name="days_offset" value="{$U.days_offset}" class="form-control form-control-sm" style="width:90px"/><span class="small">days</span>
			<div class="form-check"><input type="checkbox" class="form-check-input" name="active" value="1" {if $U.active}checked{/if}/><label class="form-check-label small">on</label></div></div></div>
		<div class="col-12 col-md-4"><input type="text" name="subject" value="{$U.subject|escape:'html'}" class="form-control form-control-sm"/></div>
		<div class="col-12 col-md-4"><textarea name="body" rows="4" class="form-control form-control-sm">{$U.body|escape:'html'}</textarea></div>
		<div class="col-12 col-md-1">{if $IS_ADMIN}<button name="do" value="save_rule" class="btn btn-primary btn-sm mb-1">Save</button><button name="do" value="delete_rule" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this rule?');">Delete</button>{/if}</div>
	</form>
	{/foreach}
	{if $IS_ADMIN}
	<form method="post" action="index.php" class="border rounded p-2 mb-2 row g-2 align-items-start bg-light"><input type="hidden" name="module" value="Invoice"/><input type="hidden" name="action" value="ReminderAct"/><input type="hidden" name="do" value="save_rule"/><input type="hidden" name="active" value="1"/>
		<div class="col-12 col-md-3"><input type="text" name="label" placeholder="New rule name" class="form-control form-control-sm mb-1"/><input type="number" name="days_offset" placeholder="days" class="form-control form-control-sm" style="width:90px"/></div>
		<div class="col-12 col-md-4"><input type="text" name="subject" placeholder="Subject" class="form-control form-control-sm"/></div>
		<div class="col-12 col-md-4"><textarea name="body" rows="3" placeholder="Message" class="form-control form-control-sm"></textarea></div>
		<div class="col-12 col-md-1"><button class="btn btn-outline-primary btn-sm">Add</button></div>
	</form>
	{/if}
	<h6 class="mt-4">Recently sent</h6>
	<table class="table table-sm"><thead><tr><th>When</th><th>Invoice</th><th>Rule</th><th>To</th><th>Result</th></tr></thead><tbody>
		{foreach from=$LOG item=L}<tr class="{if $L.status eq 'Sent'}{elseif $L.status eq 'Skipped'}text-muted{else}table-warning{/if}"><td>{$L.sent_time}</td><td>{$L.invoice_no|escape:'html'}</td><td>{$L.label|escape:'html'}</td><td>{$L.to_address|escape:'html'}</td><td>{$L.status} {$L.message|escape:'html'}</td></tr>
		{foreachelse}<tr><td colspan="5" class="text-muted text-center">Nothing sent yet.</td></tr>{/foreach}</tbody></table>
</div>
{/strip}

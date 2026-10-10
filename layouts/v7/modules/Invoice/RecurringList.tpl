{strip}
<div class="container-fluid p-4">
	<h4 class="mb-3">Recurring invoices</h4>
	{if $MESSAGE}<div class="alert alert-success py-2">{$MESSAGE|escape:'html'}</div>{/if}
	{if $ERROR}<div class="alert alert-danger py-2">{$ERROR|escape:'html'}</div>{/if}
	<p class="text-muted small">Open an invoice and choose "Make recurring" to create a schedule. A daily job raises each invoice on its date as a copy of the template (customer, items, taxes, terms) with its own number. New invoices can start as drafts to check, or be approved straight away.</p>
	<div class="table-responsive"><table class="table table-sm align-middle">
		<thead><tr><th>Template</th><th>Customer</th><th>Every</th><th>Next invoice</th><th>Until</th><th class="text-end">Raised</th><th>Status</th><th>New invoices are</th><th></th></tr></thead>
		<tbody>
		{foreach from=$SCHEDULES item=S}
			<tr>
				<td><a href="index.php?module=Invoice&view=Detail&record={$S.template_invoice}">{$S.invoice_no|escape:'html'}</a> <span class="text-muted">{$S.total|number_format:2}</span></td>
				<td>{$S.accountname|escape:'html'}</td><td>{$S.frequency}</td>
				<td>{if $S.status eq 'Ended'}-{else}{$S.next_date}{/if}</td><td>{if $S.end_date}{$S.end_date}{elseif $S.max_count}{$S.max_count} times{else}no end{/if}</td>
				<td class="text-end">{$S.raised}</td>
				<td>{if $S.status eq 'Active'}<span class="badge bg-success">Active</span>{elseif $S.status eq 'Paused'}<span class="badge bg-warning text-dark">Paused</span>{else}<span class="badge bg-secondary">Ended</span>{/if}</td>
				<td>{$S.invoice_status}</td>
				<td class="text-end text-nowrap">
					<a class="btn btn-link btn-sm" href="index.php?module=Invoice&view=RecurringList&log={$S.rec_id}">history</a>
					{if $CAN_EDIT && $S.status neq 'Ended'}
					<form method="post" action="index.php" class="d-inline"><input type="hidden" name="module" value="Invoice"/><input type="hidden" name="action" value="RecurringAct"/><input type="hidden" name="schedule" value="{$S.rec_id}"/>
						{if $S.status eq 'Active'}<button name="do" value="run" class="btn btn-outline-primary btn-sm" title="Raises what is due today or earlier">Run now</button> <button name="do" value="pause" class="btn btn-outline-secondary btn-sm">Pause</button>{else}<button name="do" value="resume" class="btn btn-outline-success btn-sm">Resume</button>{/if}
						<button name="do" value="end" class="btn btn-outline-danger btn-sm" onclick="return confirm('End this schedule? It cannot be restarted.');">End</button></form>
					{/if}
				</td>
			</tr>
		{foreachelse}<tr><td colspan="9" class="text-center text-muted py-4">No recurring invoices yet.</td></tr>{/foreach}
		</tbody>
	</table></div>
	{if $LOG_ID}
	<h6 class="mt-4">History of schedule {$LOG_ID}</h6>
	<table class="table table-sm" style="max-width:760px"><thead><tr><th>Scheduled</th><th>Run</th><th>Invoice</th><th>Result</th></tr></thead><tbody>
		{foreach from=$LOG item=L}<tr class="{if !$L.ok}table-danger{/if}"><td>{$L.scheduled_date}</td><td>{$L.run_time}</td><td>{if $L.invoice_id}<a href="index.php?module=Invoice&view=Detail&record={$L.invoice_id}">{$L.invoice_no|escape:'html'}</a>{/if}</td><td>{$L.message|escape:'html'}</td></tr>
		{foreachelse}<tr><td colspan="4" class="text-muted text-center">Nothing raised yet.</td></tr>{/foreach}</tbody></table>
	{/if}
</div>
{/strip}

{strip}
<div class="container-fluid p-4">
	<h4 class="mb-3">Audit trail</h4>
	{if not $ENABLED}<div class="alert alert-warning">The audit trail is not switched on. Run <code>php bin/create-integrity.php</code>.</div>{/if}
	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="view" value="AuditTrail"/>
		<div class="col-6 col-md-2"><label class="form-label">From</label><input type="date" name="from" value="{$F.from}" class="form-control"/></div>
		<div class="col-6 col-md-2"><label class="form-label">To</label><input type="date" name="to" value="{$F.to}" class="form-control"/></div>
		<div class="col-6 col-md-2"><label class="form-label">User</label><select name="user" class="form-select"><option value="">Anyone</option>{foreach from=$USERS key=UID item=UN}<option value="{$UID}" {if $F.user eq $UID}selected{/if}>{$UN|escape:'html'}</option>{/foreach}</select></div>
		<div class="col-6 col-md-2"><label class="form-label">Action</label><select name="action" class="form-select"><option value="">Any</option>{foreach from=['created','deleted','reversed','reversal','rebuild'] item=A}<option value="{$A}" {if $F.action eq $A}selected{/if}>{$A}</option>{/foreach}</select></div>
		<div class="col-6 col-md-2"><label class="form-label">Entry no.</label><input type="number" name="entry" value="{$F.entry}" class="form-control"/></div>
		<div class="col-6 col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Show</button></div>
	</form>
	<div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>When</th><th>User</th><th>Action</th><th>Entry</th><th>Entry date</th><th>Source</th><th class="text-end">Amount</th><th>Narration</th></tr></thead><tbody>
		{foreach from=$ROWS item=R}<tr><td class="text-nowrap">{$R.at}</td><td>{$R.user_name|escape:'html'}</td><td><span class="badge {if $R.action eq 'deleted'}bg-danger{elseif $R.action eq 'created'}bg-success{else}bg-secondary{/if}">{$R.action}</span></td>
			<td>{if $R.entry_id}JV{$R.entry_id}{/if}</td><td class="text-nowrap">{$R.entry_date}</td><td>{$R.source_module|escape:'html'} {if $R.source_id}#{$R.source_id}{/if}</td><td class="text-end">{$R.total|number_format:2}</td><td>{$R.narration|escape:'html'}</td></tr>
		{foreachelse}<tr><td colspan="8" class="text-muted text-center py-3">Nothing logged yet.</td></tr>{/foreach}</tbody></table></div>
	<div class="d-flex justify-content-between small text-muted"><span>{$COUNT} changes</span>
		<span>{if $PAGE gt 1}<a href="index.php?module=Ledgers&view=AuditTrail&{$QUERY}&page={$PAGE-1}">Newer</a>{/if} page {$PAGE} of {$PAGES} {if $PAGE lt $PAGES}<a href="index.php?module=Ledgers&view=AuditTrail&{$QUERY}&page={$PAGE+1}">Older</a>{/if}</span></div>
</div>
{/strip}

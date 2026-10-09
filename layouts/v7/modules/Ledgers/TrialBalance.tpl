{strip}
<div class="container-fluid p-4">
	<h4 class="mb-3">Trial Balance</h4>
	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="view" value="TrialBalance"/>
		<div class="col-8 col-md-3"><label class="form-label">As at</label><input type="date" name="to" value="{$TO}" class="form-control"/></div>
		{if $COST_CENTRES}<div class="col-12 col-md-4"><label class="form-label">Cost centre / project</label>
			<select name="cc" class="form-select"><option value="">All (company)</option>{foreach from=$COST_CENTRES key=ID item=NAME}<option value="{$ID}" {if $ID eq $CC}selected{/if}>{$NAME|escape:'html'}</option>{/foreach}</select></div>{/if}
		<div class="col-4 col-md-2"><button class="btn btn-primary w-100" type="submit">Show</button></div>
	</form>
	<div class="table-responsive">
		<table class="table table-sm table-hover align-middle">
			<thead><tr><th>Ledger</th><th>Group</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
			<tbody>
			{foreach from=$ROWS item=ROW}
				<tr>
					<td><a href="index.php?module=Ledgers&view=Statement&record={$ROW.id}&to={$TO}">{$ROW.name|escape:'html'}</a></td>
					<td>{vtranslate($ROW.grp, $MODULE)}</td>
					<td class="text-end">{if $ROW.dr}{$ROW.dr|number_format:2}{/if}</td>
					<td class="text-end">{if $ROW.cr}{$ROW.cr|number_format:2}{/if}</td>
				</tr>
			{foreachelse}
				<tr><td colspan="4" class="text-center text-muted py-4">Nothing posted yet.</td></tr>
			{/foreach}
			</tbody>
			<tfoot><tr class="fw-bold"><td colspan="2">Total</td><td class="text-end">{$TOTAL_DEBIT|number_format:2}</td><td class="text-end">{$TOTAL_CREDIT|number_format:2}</td></tr></tfoot>
		</table>
	</div>
	{if $TOTAL_DEBIT neq $TOTAL_CREDIT && !$CC}<div class="alert alert-danger">The trial balance does not balance (difference {($TOTAL_DEBIT-$TOTAL_CREDIT)|number_format:2}). Run <code>php bin/create-journal.php</code> to rebuild the postings.</div>
	{else}<div class="text-success small">Debits and credits agree.</div>{/if}
</div>
{/strip}

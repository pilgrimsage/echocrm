{strip}
<div class="container-fluid p-4">
	<h4 class="mb-3">{if $TYPE eq 'vendor'}Vendor statement{else}Customer statement{/if}</h4>
	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="view" value="PartyStatement"/><input type="hidden" name="type" value="{$TYPE}"/>
		<div class="col-12 col-md-4"><label class="form-label">{if $TYPE eq 'vendor'}Vendor{else}Customer{/if} name starts with</label><input type="text" name="q" value="{$Q|escape:'html'}" class="form-control" placeholder="Type a name"/></div>
		<div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit">Find</button></div>
	</form>
	{if $MATCHES}
		<div class="list-group mb-3" style="max-width:520px">{foreach from=$MATCHES item=M}<a class="list-group-item list-group-item-action" href="index.php?module=Ledgers&view=PartyStatement&type={$TYPE}&party={$M.id}">{$M.name|escape:'html'}</a>{/foreach}</div>
	{elseif $Q neq '' && !$PARTY_ID}<div class="alert alert-info">No match.</div>{/if}
	{if $PARTY_ID}
		<h5>{$PARTY_NAME|escape:'html'}</h5>
		<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
			<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="view" value="PartyStatement"/><input type="hidden" name="type" value="{$TYPE}"/><input type="hidden" name="party" value="{$PARTY_ID}"/>
			<div class="col-6 col-md-2"><label class="form-label">From</label><input type="date" name="from" value="{$FROM}" class="form-control"/></div>
			<div class="col-6 col-md-2"><label class="form-label">To</label><input type="date" name="to" value="{$TO}" class="form-control"/></div>
			<div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit">Show</button></div>
		</form>
		<div class="row g-3 mb-3">
			<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Opening balance</div><div class="fs-5">{$OPENING|number_format:2}</div></div></div>
			<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Debits</div><div class="fs-5">{$TOTAL_DEBIT|number_format:2}</div></div></div>
			<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Credits</div><div class="fs-5">{$TOTAL_CREDIT|number_format:2}</div></div></div>
			<div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">{if $TYPE eq 'vendor'}We owe them{else}They owe us{/if}</div><div class="fs-5 fw-bold">{$CLOSING|number_format:2}</div></div></div>
		</div>
		<div class="table-responsive"><table class="table table-sm table-hover align-middle">
			<thead><tr><th>Date</th><th>Entry</th><th>Document</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead>
			<tbody>
			{foreach from=$ROWS item=ROW}
				<tr><td>{$ROW.display_date}</td><td>{$ROW.entry_no}</td>
				<td>{if $ROW.url}<a href="{$ROW.url}">{$ROW.narration|escape:'html'}</a>{else}{$ROW.narration|escape:'html'}{/if}</td>
				<td class="text-end">{if $ROW.debit > 0}{$ROW.debit|number_format:2}{/if}</td><td class="text-end">{if $ROW.credit > 0}{$ROW.credit|number_format:2}{/if}</td><td class="text-end">{$ROW.running|number_format:2}</td></tr>
			{foreachelse}<tr><td colspan="6" class="text-center text-muted py-4">No postings in this period.</td></tr>{/foreach}
			</tbody>
		</table></div>
	{/if}
</div>
{/strip}

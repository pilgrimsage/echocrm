{strip}
<div class="container-fluid p-4" style="max-width:1000px">
	<h4 class="mb-3">Books health check</h4>
	{if $MESSAGE}<div class="alert alert-success">{$MESSAGE|escape:'html'}</div>{/if}
	{if $ERROR}<div class="alert alert-danger">{$ERROR|escape:'html'}</div>{/if}
	{if $PROBLEMS eq 0}<div class="alert alert-success">Everything checked is in order: entries balance, every document and payment is on the books, and the monthly totals agree with the journal.</div>{/if}
	{foreach from=$CHECKS item=C}
	<div class="card mb-3"><div class="card-body">
		<div class="d-flex justify-content-between align-items-start gap-3">
			<div><span class="badge {if $C[2] eq 0}bg-success{else}bg-danger{/if} me-2">{$C[2]}</span><strong>{$C[1]|escape:'html'}</strong>
				{if $C[2] gt 0}<div class="small text-muted mt-1">{$C[3]|escape:'html'}</div>{/if}</div>
			{if $C[2] gt 0 and $C[5]}<form method="post" action="index.php"><input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="action" value="BooksHealthAct"/><input type="hidden" name="fix" value="{$C[5]}"/>
				<button class="btn btn-primary btn-sm text-nowrap" type="submit">{if $C[5] eq 'repost'}Post them{elseif $C[5] eq 'remove'}Remove them{else}Rebuild{/if}</button></form>{/if}
		</div>
		{if $C[2] gt 0}<ul class="small mb-0 mt-2">{foreach from=$C[4] item=S}<li>{$S|escape:'html'}</li>{/foreach}{if $C[2] gt count($C[4])}<li class="text-muted">and {$C[2] - count($C[4])} more</li>{/if}</ul>{/if}
	</div></div>
	{/foreach}
	<a href="index.php?module=Ledgers&view=AuditTrail">Audit trail</a>
</div>
{/strip}

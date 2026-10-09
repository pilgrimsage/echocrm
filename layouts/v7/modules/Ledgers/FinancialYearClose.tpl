{strip}
<div class="container-fluid p-4" style="max-width:980px">
	<h4 class="mb-1">{if $CHECK.blocking && $CHECK.blocking[0] eq 'This year is already closed.'}Reopen{else}Close{/if} financial year {$PREVIEW.year.label}</h4>
	<div class="text-muted mb-3">{$PREVIEW.year.start} to {$PREVIEW.year.end}</div>
	{if $ERROR}<div class="alert alert-danger">{$ERROR|escape:'html'}</div>{/if}
	{assign var=CLOSED value=($CHECK.blocking && $CHECK.blocking[0] eq 'This year is already closed.')}
	{if $CLOSED}
		<div class="alert alert-info">This year is closed. Reopening removes its closing entry and moves the lock date back to the year before. Only the most recently closed year can be reopened.</div>
		<form method="post" action="index.php"><input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="action" value="FinancialYearAct"/><input type="hidden" name="do" value="reopen"/><input type="hidden" name="year" value="{$PREVIEW.year.start}"/>
			<button class="btn btn-danger" type="submit">Reopen year</button> <a class="btn btn-outline-secondary" href="index.php?module=Ledgers&view=FinancialYears">Back</a></form>
	{else}
		{foreach from=$CHECK.blocking item=B}<div class="alert alert-danger py-2">{$B|escape:'html'}</div>{/foreach}
		{foreach from=$CHECK.warnings item=W}<div class="alert alert-warning py-2">{$W.0|escape:'html'} <a href="{$W.1}">Look</a></div>{/foreach}
		<div class="row g-4 mb-3">
			<div class="col-12 col-lg-6"><h6>Income cleared</h6><table class="table table-sm"><tbody>{foreach from=$PREVIEW.income item=R}<tr><td>{$R.name|escape:'html'}</td><td class="text-end">{$R.amount|number_format:2}</td></tr>{foreachelse}<tr><td class="text-muted">None.</td><td></td></tr>{/foreach}</tbody><tfoot><tr class="fw-bold"><td>Total income</td><td class="text-end">{$PREVIEW.total_income|number_format:2}</td></tr></tfoot></table></div>
			<div class="col-12 col-lg-6"><h6>Expenses cleared</h6><table class="table table-sm"><tbody>{foreach from=$PREVIEW.expenses item=R}<tr><td>{$R.name|escape:'html'}</td><td class="text-end">{$R.amount|number_format:2}</td></tr>{foreachelse}<tr><td class="text-muted">None.</td><td></td></tr>{/foreach}</tbody><tfoot><tr class="fw-bold"><td>Total expenses</td><td class="text-end">{$PREVIEW.total_expenses|number_format:2}</td></tr></tfoot></table></div>
		</div>
		<div class="border rounded p-3 mb-3 fs-5 {if $PREVIEW.profit < 0}text-danger{else}text-success{/if}">{if $PREVIEW.profit < 0}Loss{else}Profit{/if} moved to Retained Earnings: <strong>{$PREVIEW.profit|number_format:2}</strong></div>
		{if !$CHECK.blocking}
		<form method="post" action="index.php"><input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="action" value="FinancialYearAct"/><input type="hidden" name="do" value="close"/><input type="hidden" name="year" value="{$PREVIEW.year.start}"/>
			{if $CHECK.warnings}<div class="form-check mb-3"><input type="checkbox" class="form-check-input" name="acknowledge" value="1" id="ack"/><label class="form-check-label" for="ack">I have seen the warnings and want to close the year anyway</label></div>{/if}
			<div class="small text-muted mb-2">After closing, nothing dated on or before {$PREVIEW.year.end} can be added or changed (the lock date is set to that day). Reopening is possible for the latest closed year.</div>
			<button class="btn btn-primary" type="submit">Close year {$PREVIEW.year.label}</button> <a class="btn btn-outline-secondary" href="index.php?module=Ledgers&view=FinancialYears">Cancel</a></form>
		{else}<a class="btn btn-outline-secondary" href="index.php?module=Ledgers&view=FinancialYears">Back</a>{/if}
	{/if}
</div>
{/strip}

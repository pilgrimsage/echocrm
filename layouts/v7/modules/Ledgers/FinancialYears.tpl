{strip}
<div class="container-fluid p-4" style="max-width:980px">
	<h4 class="mb-3">Financial years</h4>
	{if $MESSAGE}<div class="alert alert-success">{$MESSAGE|escape:'html'}</div>{/if}
	{if $ERROR}<div class="alert alert-danger">{$ERROR|escape:'html'}</div>{/if}
	<p class="text-muted small">Closing a year clears its income and expenses into Retained Earnings with one entry dated its last day, and locks the books up to that day. Balance sheet balances carry forward by themselves. The year starts in the month set under Accounting Settings.</p>
	<table class="table align-middle">
		<thead><tr><th>Year</th><th>From</th><th>To</th><th>Status</th><th class="text-end">Profit / (loss)</th><th></th></tr></thead>
		<tbody>
		{foreach from=$YEARS item=Y}
			<tr>
				<td class="fw-semibold">{$Y.label}</td><td>{$Y.start}</td><td>{$Y.end}</td>
				<td>{if $Y.closed}<span class="badge bg-secondary">Closed {$Y.closed_time}</span>{elseif $Y.ended}<span class="badge bg-warning text-dark">Ended, not closed</span>{else}<span class="badge bg-success">Current</span>{/if}</td>
				<td class="text-end">{if $Y.closed}{$Y.profit|number_format:2}{/if}</td>
				<td class="text-end">
					<a class="btn btn-outline-secondary btn-sm" href="index.php?module=Ledgers&view=ProfitLoss&from={$Y.start}&to={$Y.end}">Profit &amp; Loss</a>
					<a class="btn btn-outline-secondary btn-sm" href="index.php?module=Ledgers&view=BalanceSheet&to={$Y.end}">Balance sheet</a>
					{if $IS_ADMIN}
						{if !$Y.closed && $Y.ended}<a class="btn btn-primary btn-sm" href="index.php?module=Ledgers&view=FinancialYearClose&year={$Y.start}">Close year</a>{/if}
						{if $Y.closed && $Y.start eq $LATEST_CLOSED}<a class="btn btn-outline-danger btn-sm" href="index.php?module=Ledgers&view=FinancialYearClose&year={$Y.start}">Reopen</a>{/if}
					{/if}
				</td>
			</tr>
		{/foreach}
		</tbody>
	</table>
</div>
{/strip}

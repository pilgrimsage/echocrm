{strip}
<div class="container-fluid p-4">
	<h4 class="mb-3">Balance Sheet</h4>
	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="view" value="BalanceSheet"/>
		<div class="col-8 col-md-3"><label class="form-label">As at</label><input type="date" name="to" value="{$TO}" class="form-control"/></div>
		<div class="col-4 col-md-2"><button class="btn btn-primary w-100" type="submit">Show</button></div>
	</form>
	<div class="row g-4">
		<div class="col-12 col-lg-6">
			<h6>Assets</h6>
			<table class="table table-sm"><tbody>
			{foreach from=$SECTIONS.Assets item=ROW}<tr><td><a href="index.php?module=Ledgers&view=Statement&record={$ROW.id}&to={$TO}">{$ROW.name|escape:'html'}</a></td><td class="text-end">{$ROW.amount|number_format:2}</td></tr>
			{foreachelse}<tr><td class="text-muted">None.</td><td></td></tr>{/foreach}
			</tbody><tfoot><tr class="fw-bold"><td>Total assets</td><td class="text-end">{$TOTALS.Assets|number_format:2}</td></tr></tfoot></table>
		</div>
		<div class="col-12 col-lg-6">
			<h6>Liabilities</h6>
			<table class="table table-sm"><tbody>
			{foreach from=$SECTIONS.Liabilities item=ROW}<tr><td><a href="index.php?module=Ledgers&view=Statement&record={$ROW.id}&to={$TO}">{$ROW.name|escape:'html'}</a></td><td class="text-end">{$ROW.amount|number_format:2}</td></tr>
			{foreachelse}<tr><td class="text-muted">None.</td><td></td></tr>{/foreach}
			</tbody><tfoot><tr class="fw-bold"><td>Total liabilities</td><td class="text-end">{$TOTALS.Liabilities|number_format:2}</td></tr></tfoot></table>
			<h6 class="mt-3">Equity</h6>
			<table class="table table-sm"><tbody>
			{foreach from=$SECTIONS.Equity item=ROW}<tr><td><a href="index.php?module=Ledgers&view=Statement&record={$ROW.id}&to={$TO}">{$ROW.name|escape:'html'}</a></td><td class="text-end">{$ROW.amount|number_format:2}</td></tr>{/foreach}
			<tr><td>Profit / (loss) to date</td><td class="text-end">{$PROFIT|number_format:2}</td></tr>
			</tbody><tfoot><tr class="fw-bold"><td>Total equity</td><td class="text-end">{$TOTALS.Equity|number_format:2}</td></tr></tfoot></table>
			<div class="d-flex justify-content-between fw-bold border-top pt-2"><span>Liabilities + equity</span><span>{$LIABILITIES_AND_EQUITY|number_format:2}</span></div>
		</div>
	</div>
	{if $TOTALS.Assets neq $LIABILITIES_AND_EQUITY}<div class="alert alert-danger mt-3">The balance sheet does not balance (difference {($TOTALS.Assets-$LIABILITIES_AND_EQUITY)|number_format:2}). Check the Trial Balance.</div>{else}<div class="text-success small mt-3">Assets equal liabilities plus equity.</div>{/if}
</div>
{/strip}

{strip}
<div class="container-fluid p-4" style="max-width:900px">
	<h4 class="mb-3">Run depreciation</h4>
	{if $MESSAGE}<div class="alert alert-success">{$MESSAGE|escape:'html'}</div>{/if}
	{if $ERROR}<div class="alert alert-danger">{$ERROR|escape:'html'}</div>{/if}
	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="view" value="DepreciationRun"/>
		<div class="col-8 col-md-4"><label class="form-label">Depreciate up to the end of (month)</label><input type="month" name="period" value="{$PERIOD}" class="form-control"/></div>
		<div class="col-4 col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Preview</button></div>
	</form>
	<table class="table table-sm" style="max-width:520px"><thead><tr><th>Month</th><th class="text-end">Assets</th><th class="text-end">Depreciation</th></tr></thead><tbody>
		{foreach from=$PREVIEW key=P item=D}<tr><td>{$P}</td><td class="text-end">{$D.assets}</td><td class="text-end">{$D.total|number_format:2}</td></tr>{foreachelse}<tr><td colspan="3" class="text-muted text-center py-3">Nothing to post: depreciation is up to date to this month.</td></tr>{/foreach}</tbody>
		{if $PREVIEW}<tfoot><tr class="fw-bold"><td colspan="2">Total</td><td class="text-end">{$TOTAL|number_format:2}</td></tr></tfoot>{/if}</table>
	{if $PREVIEW}
	<form method="post" action="index.php"><input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="action" value="DepreciationPost"/><input type="hidden" name="period" value="{$PERIOD}"/>
		<div class="small text-muted mb-2">One entry per month is posted (expense per class and cost centre against accumulated depreciation). Months already posted are never changed. Straight line takes (cost - salvage) / life per month; declining balance the book value times the annual rate / 12.</div>
		<button class="btn btn-primary" type="submit">Post depreciation</button></form>
	{/if}
	<div class="mt-3"><a href="index.php?module=Ledgers&view=AssetRegister">Asset register</a> &nbsp; <a href="index.php?module=Ledgers&view=AssetClasses">Asset classes</a></div>
</div>
{/strip}

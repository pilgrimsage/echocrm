{strip}
<div class="container-fluid p-4" style="max-width:900px">
	<h4 class="mb-3">Withholding sections</h4>
	{if $MESSAGE}<div class="alert alert-success">{$MESSAGE|escape:'html'}</div>{/if}
	{if $ERROR}<div class="alert alert-danger">{$ERROR|escape:'html'}</div>{/if}
	{if not $ENABLED}<div class="alert alert-warning">Not switched on. Run <code>php bin/create-withholding.php</code>.</div>{else}
	<p class="text-muted small">Sections for tax withheld at source (TDS or the equivalent in your country). Choose a section on a payment and the tax withheld is worked out as the rate times the taxable part of the payment (tax excluded); you can type another amount. The starter rates are examples: check them against the current rules.</p>
	{foreach from=$SECTIONS item=S}
	<form method="post" action="index.php" class="border rounded p-2 mb-2 row g-2 align-items-end">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="action" value="WithholdingSave"/><input type="hidden" name="section_id" value="{$S.section_id}"/>
		<div class="col-4 col-md-2"><label class="form-label small mb-0">Code</label><input type="text" name="code" value="{$S.code|escape:'html'}" class="form-control form-control-sm"/></div>
		<div class="col-8 col-md-5"><label class="form-label small mb-0">Description</label><input type="text" name="description" value="{$S.description|escape:'html'}" class="form-control form-control-sm"/></div>
		<div class="col-4 col-md-2"><label class="form-label small mb-0">Rate %</label><input type="number" step="0.001" name="rate" value="{$S.rate}" class="form-control form-control-sm"/></div>
		<div class="col-4 col-md-1"><div class="form-check"><input class="form-check-input" type="checkbox" name="active" value="1" {if $S.active}checked{/if}/><label class="form-check-label small">Active</label></div></div>
		<div class="col-4 col-md-2">{if $IS_ADMIN}<button class="btn btn-primary btn-sm" type="submit">Save</button>{/if}</div>
	</form>
	{/foreach}
	{if $IS_ADMIN}<h6 class="mt-4">New section</h6>
	<form method="post" action="index.php" class="border rounded p-2 row g-2 align-items-end">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="action" value="WithholdingSave"/><input type="hidden" name="section_id" value="0"/><input type="hidden" name="active" value="1"/>
		<div class="col-4 col-md-2"><label class="form-label small mb-0">Code</label><input type="text" name="code" class="form-control form-control-sm" required/></div>
		<div class="col-8 col-md-6"><label class="form-label small mb-0">Description</label><input type="text" name="description" class="form-control form-control-sm"/></div>
		<div class="col-4 col-md-2"><label class="form-label small mb-0">Rate %</label><input type="number" step="0.001" name="rate" class="form-control form-control-sm" required/></div>
		<div class="col-4 col-md-2"><button class="btn btn-primary btn-sm" type="submit">Add</button></div>
	</form>{/if}
	{/if}
</div>
{/strip}

{strip}
<div class="container-fluid p-4" style="max-width:1100px">
	<h4 class="mb-3">Asset classes</h4>
	{if $MESSAGE}<div class="alert alert-success">{$MESSAGE|escape:'html'}</div>{/if}
	{if $ERROR}<div class="alert alert-danger">{$ERROR|escape:'html'}</div>{/if}
	<p class="text-muted small">Each class says which ledgers its assets post to and how they depreciate unless the asset says otherwise. Add a class for each kind of asset your business owns (for example "Cold Storage", "Lab Equipment", "Rental Fleet").</p>
	{foreach from=$CLASSES item=C}
		<form method="post" action="index.php" class="border rounded p-3 mb-2 row g-2 align-items-end">
			<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="action" value="AssetClassSave"/><input type="hidden" name="class_id" value="{$C.class_id}"/>
			<div class="col-12 col-md-3"><label class="form-label small mb-0">Class</label><input type="text" name="class_name" value="{$C.class_name|escape:'html'}" class="form-control form-control-sm"/></div>
			<div class="col-6 col-md-3"><label class="form-label small mb-0">Asset ledger</label><select name="asset_ledger" class="form-select form-select-sm">{foreach from=$LEDGERS key=ID item=N}<option value="{$ID}" {if $ID eq $C.asset_ledger}selected{/if}>{$N|escape:'html'}</option>{/foreach}</select></div>
			<div class="col-6 col-md-3"><label class="form-label small mb-0">Accumulated depreciation ledger</label><select name="accum_ledger" class="form-select form-select-sm">{foreach from=$LEDGERS key=ID item=N}<option value="{$ID}" {if $ID eq $C.accum_ledger}selected{/if}>{$N|escape:'html'}</option>{/foreach}</select></div>
			<div class="col-6 col-md-3"><label class="form-label small mb-0">Depreciation expense ledger</label><select name="expense_ledger" class="form-select form-select-sm">{foreach from=$LEDGERS key=ID item=N}<option value="{$ID}" {if $ID eq $C.expense_ledger}selected{/if}>{$N|escape:'html'}</option>{/foreach}</select></div>
			<div class="col-4 col-md-2"><label class="form-label small mb-0">Method</label><select name="dep_method" class="form-select form-select-sm"><option value="SLM" {if $C.dep_method eq 'SLM'}selected{/if}>Straight line</option><option value="WDV" {if $C.dep_method eq 'WDV'}selected{/if}>Declining balance</option></select></div>
			<div class="col-4 col-md-2"><label class="form-label small mb-0">Life (years)</label><input type="number" step="0.01" name="life_years" value="{$C.life_years}" class="form-control form-control-sm"/></div>
			<div class="col-4 col-md-2"><label class="form-label small mb-0">Rate % a year</label><input type="number" step="0.001" name="dep_rate" value="{$C.dep_rate}" class="form-control form-control-sm"/></div>
			<div class="col-12 col-md-2">{if $IS_ADMIN}<button class="btn btn-primary btn-sm" type="submit">Save</button>{/if}</div>
		</form>
	{/foreach}
	{if $IS_ADMIN}
	<h6 class="mt-4">New class</h6>
	<form method="post" action="index.php" class="border rounded p-3 row g-2 align-items-end">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="action" value="AssetClassSave"/><input type="hidden" name="class_id" value="0"/>
		<div class="col-12 col-md-3"><label class="form-label small mb-0">Class</label><input type="text" name="class_name" class="form-control form-control-sm" required/></div>
		<div class="col-6 col-md-3"><label class="form-label small mb-0">Asset ledger</label><select name="asset_ledger" class="form-select form-select-sm">{foreach from=$LEDGERS key=ID item=N}<option value="{$ID}">{$N|escape:'html'}</option>{/foreach}</select></div>
		<div class="col-6 col-md-3"><label class="form-label small mb-0">Accumulated depreciation ledger</label><select name="accum_ledger" class="form-select form-select-sm">{foreach from=$LEDGERS key=ID item=N}<option value="{$ID}">{$N|escape:'html'}</option>{/foreach}</select></div>
		<div class="col-6 col-md-3"><label class="form-label small mb-0">Depreciation expense ledger</label><select name="expense_ledger" class="form-select form-select-sm">{foreach from=$LEDGERS key=ID item=N}<option value="{$ID}">{$N|escape:'html'}</option>{/foreach}</select></div>
		<div class="col-4 col-md-2"><label class="form-label small mb-0">Method</label><select name="dep_method" class="form-select form-select-sm"><option value="SLM">Straight line</option><option value="WDV">Declining balance</option></select></div>
		<div class="col-4 col-md-2"><label class="form-label small mb-0">Life (years)</label><input type="number" step="0.01" name="life_years" class="form-control form-control-sm"/></div>
		<div class="col-4 col-md-2"><label class="form-label small mb-0">Rate % a year</label><input type="number" step="0.001" name="dep_rate" class="form-control form-control-sm"/></div>
		<div class="col-12 col-md-2"><button class="btn btn-primary btn-sm" type="submit">Add class</button></div>
	</form>
	{/if}
</div>
{/strip}

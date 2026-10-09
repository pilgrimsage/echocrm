{strip}
<div class="container-fluid p-4">
	<h4 class="mb-3">GST returns</h4>
	<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
		<input type="hidden" name="module" value="Ledgers"/><input type="hidden" name="view" value="GstReturns"/><input type="hidden" name="section" value="{$SECTION}"/>
		<div class="col-8 col-md-3"><label class="form-label">Return period (month)</label><input type="month" name="period" value="{$PERIOD}" class="form-control"/></div>
		<div class="col-4 col-md-2"><button class="btn btn-primary w-100" type="submit">Show</button></div>
	</form>
	<ul class="nav nav-tabs mb-3">
		{foreach from=['gstr1'=>'GSTR-1 (outward supplies)','gstr3b'=>'GSTR-3B (summary)','purchases'=>'Purchase register (input tax)','checks'=>'Data checks'] key=KEY item=LABEL}
			<li class="nav-item"><a class="nav-link {if $SECTION eq $KEY}active{/if}" href="index.php?module=Ledgers&view=GstReturns&section={$KEY}&period={$PERIOD}">{$LABEL}</a></li>
		{/foreach}
	</ul>

{if $SECTION eq 'gstr1'}
	<div class="alert alert-secondary py-2 small">Posted invoices (Approved, Sent, Credit Invoice, Paid) dated in the month, and approved/sent credit notes. Exports, reverse charge, e-commerce and amendments are not covered. Check the CSV columns against the current GST offline utility before uploading.</div>
	{foreach from=['b2b'=>'4A, 4B - B2B invoices (registered recipients)','b2cl'=>'5 - B2C large (unregistered, inter-state, over 2.5 lakh)','cdnr'=>'9B - Credit notes to registered recipients','cdnur'=>'9B - Credit notes to unregistered recipients'] key=SEC item=TITLE}
	<div class="d-flex justify-content-between align-items-center mt-4 mb-1"><h6 class="mb-0">{$TITLE} <span class="badge bg-secondary">{$G1[$SEC]|count}</span></h6>
		<a class="btn btn-outline-secondary btn-sm" href="index.php?module=Ledgers&action=GstExport&section={$SEC}&period={$PERIOD}">CSV</a></div>
	<div class="table-responsive"><table class="table table-sm table-hover align-middle">
		<thead><tr>{if $SEC eq 'b2b' || $SEC eq 'cdnr' || $SEC eq 'cdnur'}<th>GSTIN</th><th>Name</th>{/if}<th>Number</th><th>Date</th><th class="text-end">Value</th><th>Place of supply</th><th class="text-end">Rate %</th><th class="text-end">Taxable</th><th class="text-end">IGST</th><th class="text-end">CGST</th><th class="text-end">SGST</th><th class="text-end">Other</th></tr></thead>
		<tbody>
		{foreach from=$G1[$SEC] item=R name=rows}{if $smarty.foreach.rows.iteration <= 300}
			<tr>{if $SEC eq 'b2b' || $SEC eq 'cdnr' || $SEC eq 'cdnur'}<td>{$R.gstin}</td><td>{$R.name|escape:'html'}</td>{/if}
			<td><a href="index.php?module={if $SEC eq 'cdnr' || $SEC eq 'cdnur'}SalesOrder{else}Invoice{/if}&view=Detail&record={$R.id}">{$R.doc_no|escape:'html'}</a></td><td>{$R.date}</td><td class="text-end">{$R.value|number_format:2}</td><td>{$R.pos|escape:'html'}</td>
			<td class="text-end">{$R.rate}</td><td class="text-end">{$R.taxable|number_format:2}</td><td class="text-end">{$R.igst|number_format:2}</td><td class="text-end">{$R.cgst|number_format:2}</td><td class="text-end">{$R.sgst|number_format:2}</td><td class="text-end">{$R.other|number_format:2}</td></tr>
		{/if}{foreachelse}<tr><td colspan="12" class="text-center text-muted py-3">None in this period.</td></tr>{/foreach}
		</tbody>
		<tfoot><tr class="fw-bold"><td colspan="{if $SEC eq 'b2b' || $SEC eq 'cdnr' || $SEC eq 'cdnur'}7{else}5{/if}">Total{if $G1[$SEC]|count > 300} (first 300 rows shown, download the CSV for all){/if}</td><td class="text-end">{$G1.totals[$SEC].taxable|number_format:2}</td><td class="text-end">{$G1.totals[$SEC].igst|number_format:2}</td><td class="text-end">{$G1.totals[$SEC].cgst|number_format:2}</td><td class="text-end">{$G1.totals[$SEC].sgst|number_format:2}</td><td class="text-end">{$G1.totals[$SEC].other|number_format:2}</td></tr></tfoot>
	</table></div>
	{/foreach}

	<div class="d-flex justify-content-between align-items-center mt-4 mb-1"><h6 class="mb-0">7 - B2C small (unregistered), by place of supply and rate</h6><a class="btn btn-outline-secondary btn-sm" href="index.php?module=Ledgers&action=GstExport&section=b2cs&period={$PERIOD}">CSV</a></div>
	<div class="table-responsive"><table class="table table-sm table-hover">
		<thead><tr><th>Place of supply</th><th class="text-end">Rate %</th><th class="text-end">Taxable</th><th class="text-end">IGST</th><th class="text-end">CGST</th><th class="text-end">SGST</th><th class="text-end">Other</th></tr></thead>
		<tbody>{foreach from=$G1.b2cs item=R}<tr><td>{$R.pos|escape:'html'}</td><td class="text-end">{$R.rate}</td><td class="text-end">{$R.taxable|number_format:2}</td><td class="text-end">{$R.igst|number_format:2}</td><td class="text-end">{$R.cgst|number_format:2}</td><td class="text-end">{$R.sgst|number_format:2}</td><td class="text-end">{$R.other|number_format:2}</td></tr>
		{foreachelse}<tr><td colspan="7" class="text-center text-muted py-3">None in this period.</td></tr>{/foreach}</tbody>
		<tfoot><tr class="fw-bold"><td colspan="2">Total</td><td class="text-end">{$G1.totals.b2cs.taxable|number_format:2}</td><td class="text-end">{$G1.totals.b2cs.igst|number_format:2}</td><td class="text-end">{$G1.totals.b2cs.cgst|number_format:2}</td><td class="text-end">{$G1.totals.b2cs.sgst|number_format:2}</td><td class="text-end">{$G1.totals.b2cs.other|number_format:2}</td></tr></tfoot>
	</table></div>

	<div class="d-flex justify-content-between align-items-center mt-4 mb-1"><h6 class="mb-0">12 - HSN-wise summary of outward supplies</h6><a class="btn btn-outline-secondary btn-sm" href="index.php?module=Ledgers&action=GstExport&section=hsn&period={$PERIOD}">CSV</a></div>
	<div class="table-responsive"><table class="table table-sm table-hover">
		<thead><tr><th>HSN / SAC</th><th>Unit</th><th class="text-end">Quantity</th><th class="text-end">Rate %</th><th class="text-end">Taxable</th><th class="text-end">IGST</th><th class="text-end">CGST</th><th class="text-end">SGST</th><th class="text-end">Other</th></tr></thead>
		<tbody>{foreach from=$G1.hsn item=R}<tr {if !$R.hsn}class="table-warning"{/if}><td>{if $R.hsn}{$R.hsn|escape:'html'}{else}<em>missing</em>{/if}</td><td>{$R.unit|escape:'html'}</td><td class="text-end">{$R.qty}</td><td class="text-end">{$R.rate}</td><td class="text-end">{$R.taxable|number_format:2}</td><td class="text-end">{$R.igst|number_format:2}</td><td class="text-end">{$R.cgst|number_format:2}</td><td class="text-end">{$R.sgst|number_format:2}</td><td class="text-end">{$R.other|number_format:2}</td></tr>
		{foreachelse}<tr><td colspan="9" class="text-center text-muted py-3">None in this period.</td></tr>{/foreach}</tbody>
	</table></div>

	<div class="d-flex justify-content-between align-items-center mt-4 mb-1"><h6 class="mb-0">13 - Documents issued</h6><a class="btn btn-outline-secondary btn-sm" href="index.php?module=Ledgers&action=GstExport&section=docs&period={$PERIOD}">CSV</a></div>
	<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Nature</th><th>From</th><th>To</th><th class="text-end">Total</th><th class="text-end">Cancelled</th><th class="text-end">Net issued</th></tr></thead>
		<tbody>{foreach from=$G1.documents item=D}<tr><td>{$D.type}</td><td>{$D.from|escape:'html'}</td><td>{$D.to|escape:'html'}</td><td class="text-end">{$D.total}</td><td class="text-end">{$D.cancelled}</td><td class="text-end">{$D.net}</td></tr>{/foreach}</tbody></table></div>

{elseif $SECTION eq 'gstr3b'}
	<div class="alert alert-secondary py-2 small">Indicative figures from the books: all input tax is treated as eligible, reverse charge and exports are not covered, and set-off follows the standard order. Confirm before filing.</div>
	<h6>3.1 Outward supplies (invoices less credit notes)</h6>
	<table class="table table-sm" style="max-width:760px"><thead><tr><th></th><th class="text-end">Taxable value</th><th class="text-end">IGST</th><th class="text-end">CGST</th><th class="text-end">SGST</th><th class="text-end">Other tax</th></tr></thead><tbody>
		<tr><td>(a) Outward taxable supplies</td><td class="text-end">{$G3B.taxable_supplies|number_format:2}</td><td class="text-end">{$G3B.outward.igst|number_format:2}</td><td class="text-end">{$G3B.outward.cgst|number_format:2}</td><td class="text-end">{$G3B.outward.sgst|number_format:2}</td><td class="text-end">{$G3B.outward.other|number_format:2}</td></tr>
		<tr><td>(c, e) Nil-rated, exempt, non-GST (0% lines)</td><td class="text-end">{$G3B.nil|number_format:2}</td><td colspan="4"></td></tr></tbody></table>
	<h6 class="mt-4">4 Eligible input tax credit</h6>
	<table class="table table-sm" style="max-width:760px"><thead><tr><th></th><th class="text-end">IGST</th><th class="text-end">CGST</th><th class="text-end">SGST</th><th class="text-end">Other tax</th></tr></thead><tbody>
		<tr><td>(A) ITC available (purchases)</td><td class="text-end">{$G3B.itc.igst.available|number_format:2}</td><td class="text-end">{$G3B.itc.cgst.available|number_format:2}</td><td class="text-end">{$G3B.itc.sgst.available|number_format:2}</td><td class="text-end">{$G3B.itc.other.available|number_format:2}</td></tr>
		<tr><td>(B) ITC reversed (debit notes to vendors)</td><td class="text-end">{$G3B.itc.igst.reversed|number_format:2}</td><td class="text-end">{$G3B.itc.cgst.reversed|number_format:2}</td><td class="text-end">{$G3B.itc.sgst.reversed|number_format:2}</td><td class="text-end">{$G3B.itc.other.reversed|number_format:2}</td></tr>
		<tr class="fw-semibold"><td>(C) Net ITC available</td><td class="text-end">{$G3B.itc.igst.net|number_format:2}</td><td class="text-end">{$G3B.itc.cgst.net|number_format:2}</td><td class="text-end">{$G3B.itc.sgst.net|number_format:2}</td><td class="text-end">{$G3B.itc.other.net|number_format:2}</td></tr></tbody></table>
	<h6 class="mt-4">6 Payment of tax</h6>
	<table class="table table-sm" style="max-width:760px"><thead><tr><th></th><th class="text-end">IGST</th><th class="text-end">CGST</th><th class="text-end">SGST</th></tr></thead><tbody>
		<tr><td>Tax payable (outward)</td><td class="text-end">{$G3B.outward.igst|number_format:2}</td><td class="text-end">{$G3B.outward.cgst|number_format:2}</td><td class="text-end">{$G3B.outward.sgst|number_format:2}</td></tr>
		<tr><td>Paid through input tax credit</td><td class="text-end">{$G3B.credit_used.igst|number_format:2}</td><td class="text-end">{$G3B.credit_used.cgst|number_format:2}</td><td class="text-end">{$G3B.credit_used.sgst|number_format:2}</td></tr>
		<tr class="fw-bold"><td>Payable in cash</td><td class="text-end">{$G3B.cash.igst|number_format:2}</td><td class="text-end">{$G3B.cash.cgst|number_format:2}</td><td class="text-end">{$G3B.cash.sgst|number_format:2}</td></tr>
		<tr class="text-muted"><td>Credit carried forward</td><td class="text-end">{$G3B.credit_left.igst|number_format:2}</td><td class="text-end">{$G3B.credit_left.cgst|number_format:2}</td><td class="text-end">{$G3B.credit_left.sgst|number_format:2}</td></tr></tbody></table>
	<div class="fs-5">Total cash payable: <strong>{$G3B.cash_total|number_format:2}</strong></div>

{elseif $SECTION eq 'purchases'}
	<div class="d-flex justify-content-between align-items-center mb-1"><h6 class="mb-0">Purchases (approved / delivered / received purchase orders created in the month)</h6><a class="btn btn-outline-secondary btn-sm" href="index.php?module=Ledgers&action=GstExport&section=purchases&period={$PERIOD}">CSV</a></div>
	{foreach from=['purchases'=>'Purchases','debit_notes'=>'Debit notes to vendors (reverse input tax)'] key=KEY item=TITLE}
	<h6 class="mt-3">{$TITLE}</h6>
	<div class="table-responsive"><table class="table table-sm table-hover align-middle">
		<thead><tr><th>Supplier GSTIN</th><th>Supplier</th><th>Number</th><th>Date</th><th class="text-end">Value</th><th>Place of supply</th><th class="text-end">Taxable</th><th class="text-end">IGST</th><th class="text-end">CGST</th><th class="text-end">SGST</th><th class="text-end">Other</th></tr></thead>
		<tbody>{foreach from=$PURCHASES[$KEY] item=R name=p}{if $smarty.foreach.p.iteration <= 300}<tr><td>{if $R.gstin}{$R.gstin}{else}<span class="text-danger">missing</span>{/if}</td><td>{$R.name|escape:'html'}</td>
			<td><a href="index.php?module={if $KEY eq 'purchases'}PurchaseOrder{else}SalesOrder{/if}&view=Detail&record={$R.id}">{$R.doc_no|escape:'html'}</a></td><td>{$R.date}</td><td class="text-end">{$R.value|number_format:2}</td><td>{$R.pos|escape:'html'}</td>
			<td class="text-end">{$R.taxable|number_format:2}</td><td class="text-end">{$R.igst|number_format:2}</td><td class="text-end">{$R.cgst|number_format:2}</td><td class="text-end">{$R.sgst|number_format:2}</td><td class="text-end">{$R.other|number_format:2}</td></tr>{/if}
		{foreachelse}<tr><td colspan="11" class="text-center text-muted py-3">None in this period.</td></tr>{/foreach}</tbody>
	</table></div>
	{/foreach}
	<div class="fw-semibold">Input tax: IGST {$PURCHASES.totals.igst|number_format:2}, CGST {$PURCHASES.totals.cgst|number_format:2}, SGST {$PURCHASES.totals.sgst|number_format:2}; reversed by debit notes: IGST {$PURCHASES.reversals.igst|number_format:2}, CGST {$PURCHASES.reversals.cgst|number_format:2}, SGST {$PURCHASES.reversals.sgst|number_format:2}.</div>

{else}
	<div class="alert alert-secondary py-2 small">Problems in this month's data that would hold up the return. Fix them at the source and reload.</div>
	{foreach from=$CHECKS item=C}
	<div class="border rounded p-3 mb-3 {if $C.count > 0}border-warning{else}border-success{/if}">
		<div class="d-flex justify-content-between"><div class="fw-semibold">{$C.title|escape:'html'}</div>
			{if $C.count > 0}<span class="badge bg-warning text-dark">{$C.count}</span>{else}<span class="badge bg-success">OK</span>{/if}</div>
		<div class="small text-muted">{$C.hint|escape:'html'}</div>
		{if $C.rows}<div class="mt-2 small">{foreach from=$C.rows item=R}<a class="me-3" href="{$R.url}">{$R.label|escape:'html'}</a>{/foreach}{if $C.count > 25} and {$C.count-25} more{/if}</div>{/if}
	</div>
	{/foreach}
{/if}
</div>
{/strip}

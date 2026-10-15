{strip}
<div class="acctWidget">
	<div class="acctWidgetBig">{$DATA.total_display}</div>
	<div class="acctWidgetMuted">{if $VENDOR}{vtranslate('LBL_ACCT_WIDGET_WE_OWE', 'Ledgers')}{else}{vtranslate('LBL_ACCT_WIDGET_OWED_TO_US', 'Ledgers')}{/if} &middot; {$DATA.documents} {vtranslate('LBL_ACCT_WIDGET_DOCUMENTS', 'Ledgers')} &middot; {vtranslate('LBL_ACCT_WIDGET_OVERDUE', 'Ledgers')} {$DATA.overdue_display}</div>
	<div class="acctAgeBars">
		{foreach from=$DATA.buckets item=BUCKET}{if $BUCKET.share gt 0}<span style="width:{$BUCKET.share}%; background:{$BUCKET.color}" title="{$BUCKET.label}: {$BUCKET.display}"></span>{/if}{/foreach}
	</div>
	<table>
		{foreach from=$DATA.buckets item=BUCKET}
			<tr><td><span class="customWidgetSwatch" style="background:{$BUCKET.color}"></span> {vtranslate($BUCKET.label, 'Ledgers')}</td><td>{$BUCKET.display}</td></tr>
		{/foreach}
	</table>
	<div class="customWidgetLink"><a href="{$LIST_URL}">{vtranslate('LBL_CUSTOM_WIDGET_OPEN_LIST', 'Vtiger')} &rarr;</a></div>
</div>
{/strip}

{strip}
<div class="acctWidget">
	<div class="acctWidgetBig{if $DATA.negative} negative{/if}">{$DATA.total_display}</div>
	<div class="acctWidgetMuted">{vtranslate('LBL_ACCT_WIDGET_ALL_ACCOUNTS', 'Ledgers')}</div>
	{if $DATA.rows}
		<table>
			{foreach from=$DATA.rows item=ROW}
				<tr><td><a href="index.php?module=BankAccounts&view=Detail&record={$ROW.id}">{$ROW.name|escape:'html'}</a> <span class="acctWidgetMuted">{$ROW.type|escape:'html'}</span></td><td{if $ROW.negative} class="negative"{/if}>{$ROW.display}</td></tr>
			{/foreach}
		</table>
	{else}
		<div class="customWidgetEmpty">{vtranslate('NO_DATA')}</div>
	{/if}
	<div class="customWidgetLink"><a href="{$LIST_URL}">{vtranslate('LBL_CUSTOM_WIDGET_OPEN_LIST', 'Vtiger')} &rarr;</a></div>
</div>
{/strip}

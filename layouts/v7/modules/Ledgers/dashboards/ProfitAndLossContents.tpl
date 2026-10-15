{strip}
<div class="acctWidget">
	<div class="acctWidgetColumns">
		{foreach from=$PERIODS item=PERIOD}
			<div>
				<div class="acctWidgetMuted">{$PERIOD.label|escape:'html'}</div>
				<div class="acctWidgetBig{if $PERIOD.negative} negative{/if}">{$PERIOD.result}</div>
				<table>
					<tr><td>{vtranslate('LBL_ACCT_WIDGET_INCOME', 'Ledgers')}</td><td>{$PERIOD.income}</td></tr>
					<tr><td>{vtranslate('LBL_ACCT_WIDGET_EXPENSES', 'Ledgers')}</td><td>{$PERIOD.expenses}</td></tr>
				</table>
			</div>
		{/foreach}
	</div>
	<div class="customWidgetLink"><a href="{$LIST_URL}">{vtranslate('LBL_CUSTOM_WIDGET_OPEN_LIST', 'Vtiger')} &rarr;</a></div>
</div>
{/strip}

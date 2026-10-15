{strip}
<div class="customWidget" data-type="{if $SETTINGS}{$SETTINGS.type}{/if}" data-widgetid="{$WIDGET->get('id')}">
	{if !empty($RESULT.error)}
		<div class="customWidgetEmpty">{$RESULT.error|escape:'html'}</div>
	{elseif $RESULT.kind eq 'number'}
		<div class="customWidgetNumber">
			<div class="customWidgetValue">{$RESULT.display|escape:'html'}</div>
			<div class="customWidgetCaption">{$RESULT.caption|escape:'html'}</div>
		</div>
	{elseif $RESULT.kind eq 'groups'}
		{if !$RESULT.rows}
			<div class="customWidgetEmpty">{vtranslate('NO_DATA')}</div>
		{elseif $SETTINGS.type eq 'bar'}
			<div class="customWidgetBars">
				{foreach from=$RESULT.rows item=ROW}
					<div class="customWidgetRow">
						<div class="customWidgetBarLabel" title="{$ROW.label|escape:'html'}">{$ROW.label|escape:'html'}</div>
						<div class="customWidgetBarTrack"><div class="customWidgetBarFill" style="width:{$ROW.bar}%; background:{$ROW.color}"></div></div>
						<div class="customWidgetBarValue">{$ROW.display|escape:'html'}</div>
					</div>
				{/foreach}
			</div>
		{else}
			<div class="customWidgetDonutWrap">
				<svg class="customWidgetDonut" viewBox="0 0 42 42" role="img" aria-label="{$SETTINGS.title|escape:'html'}">
					<circle cx="21" cy="21" r="15.9155" fill="none" stroke="#EDEFEC" stroke-width="6"></circle>
					{foreach from=$RESULT.rows item=ROW}
						<circle cx="21" cy="21" r="15.9155" fill="none" stroke="{$ROW.color}" stroke-width="6" stroke-dasharray="{$ROW.arc} {$ROW.gap}" stroke-dashoffset="{$ROW.offset}"></circle>
					{/foreach}
				</svg>
				<div class="customWidgetLegend">
					{foreach from=$RESULT.rows item=ROW}
						<div class="customWidgetRow"><span class="customWidgetSwatch" style="background:{$ROW.color}"></span>
							<span class="customWidgetLegendLabel" title="{$ROW.label|escape:'html'}">{$ROW.label|escape:'html'}</span>
							<span class="customWidgetLegendValue">{$ROW.display|escape:'html'}</span></div>
					{/foreach}
				</div>
			</div>
		{/if}
	{/if}
	{if $LIST_URL}
		<div class="customWidgetLink"><a href="{$LIST_URL}">{vtranslate('LBL_CUSTOM_WIDGET_OPEN_LIST', 'Vtiger')} &rarr;</a></div>
	{/if}
</div>
{/strip}

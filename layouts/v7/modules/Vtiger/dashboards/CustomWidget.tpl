{*<!--
/* A dashboard widget the user built: see Vtiger_CustomWidget_Model */
-->*}
<div class="dashboardWidgetHeader">
	{include file="dashboards/WidgetHeader.tpl"|@vtemplate_path:$MODULE_NAME}
</div>

<div class="dashboardWidgetContent">
	{include file="dashboards/CustomWidgetContents.tpl"|@vtemplate_path:$MODULE_NAME}
</div>

<div class="widgeticons dashBoardWidgetFooter">
	<div class="footerIcons float-end">
		{if $SETTINGS_JSON}
			<a href="javascript:void(0);" name="customWidgetEdit" data-widgetid="{$WIDGET->get('id')}">
				<i class="fa fa-edit" hspace="2" border="0" align="absmiddle" title="{vtranslate('LBL_EDIT', 'Vtiger')}" alt="{vtranslate('LBL_EDIT', 'Vtiger')}"></i>
			</a>
		{/if}
		{include file="dashboards/DashboardFooterIcons.tpl"|@vtemplate_path:$MODULE_NAME}
	</div>
</div>

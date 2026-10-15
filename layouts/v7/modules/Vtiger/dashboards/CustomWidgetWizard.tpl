{strip}
<div id="customWidgetWizard" class="modelContainer modal-dialog" data-widgetid="{$WIDGET_ID}">
	<div class="modal-content">
		{assign var=HEADER_TITLE value=vtranslate('Custom Widget', 'Vtiger')}
		{include file="ModalHeader.tpl"|vtemplate_path:$MODULE_NAME TITLE=$HEADER_TITLE}
		<form class="form-horizontal" method="post" action="javascript:;">
			<div class="modal-body customWidgetForm">
				<div class="row mb-3">
					<label class="col-sm-4 col-form-label text-sm-end">{vtranslate('LBL_CUSTOM_WIDGET_TITLE', 'Vtiger')}</label>
					<div class="col-sm-7"><input type="text" name="title" class="form-control" maxlength="60" value="{$SETTINGS.title|escape:'html'}" placeholder="{vtranslate('LBL_CUSTOM_WIDGET_TITLE_HINT', 'Vtiger')}"></div>
				</div>
				<div class="row mb-3">
					<label class="col-sm-4 col-form-label text-sm-end">{vtranslate('LBL_SELECT_MODULE', 'Vtiger')}</label>
					<div class="col-sm-7">
						<select name="module" class="form-select" style="width:100%" data-selected="{$SETTINGS.module}">
							<option value=""></option>
							{foreach from=$MODULES item=LABEL key=NAME}
								<option value="{$NAME}"{if $SETTINGS.module eq $NAME} selected{/if}>{$LABEL|escape:'html'}</option>
							{/foreach}
						</select>
					</div>
				</div>
				<div class="row mb-3">
					<label class="col-sm-4 col-form-label text-sm-end">{vtranslate('LBL_FILTER', 'Vtiger')}</label>
					<div class="col-sm-7"><select name="filterid" class="form-select" style="width:100%" data-selected="{$SETTINGS.filterid}"><option value=""></option></select></div>
				</div>
				<div class="row mb-3">
					<label class="col-sm-4 col-form-label text-sm-end">{vtranslate('LBL_CUSTOM_WIDGET_SHOW_AS', 'Vtiger')}</label>
					<div class="col-sm-7">
						<select name="type" class="form-select" style="width:100%">
							{foreach from=['number', 'bar', 'donut'] item=TYPE}
								<option value="{$TYPE}"{if $SETTINGS.type eq $TYPE} selected{/if}>{vtranslate('LBL_CUSTOM_WIDGET_TYPE_'|cat:$TYPE, 'Vtiger')}</option>
							{/foreach}
						</select>
					</div>
				</div>
				<div class="row mb-3">
					<label class="col-sm-4 col-form-label text-sm-end">{vtranslate('LBL_CUSTOM_WIDGET_CALCULATE', 'Vtiger')}</label>
					<div class="col-sm-7">
						<select name="metric" class="form-select" style="width:100%">
							{foreach from=['count', 'sum', 'avg', 'min', 'max'] item=METRIC}
								<option value="{$METRIC}"{if $SETTINGS.metric eq $METRIC} selected{/if}>{vtranslate('LBL_CUSTOM_WIDGET_METRIC_'|cat:$METRIC, 'Vtiger')}</option>
							{/foreach}
						</select>
					</div>
				</div>
				<div class="row mb-3 customWidgetFieldRow">
					<label class="col-sm-4 col-form-label text-sm-end">{vtranslate('LBL_CUSTOM_WIDGET_OF_FIELD', 'Vtiger')}</label>
					<div class="col-sm-7"><select name="field" class="form-select" style="width:100%" data-selected="{$SETTINGS.field}"><option value=""></option></select></div>
				</div>
				<div class="row mb-3 customWidgetGroupRow">
					<label class="col-sm-4 col-form-label text-sm-end">{vtranslate('LBL_CUSTOM_WIDGET_GROUP_BY', 'Vtiger')}</label>
					<div class="col-sm-7"><select name="groupby" class="form-select" style="width:100%" data-selected="{$SETTINGS.groupby}"><option value=""></option></select></div>
				</div>
				<div class="row mb-3 customWidgetGroupRow">
					<label class="col-sm-4 col-form-label text-sm-end">{vtranslate('LBL_CUSTOM_WIDGET_LIMIT', 'Vtiger')}</label>
					<div class="col-sm-3"><input type="number" name="limit" class="form-control" min="2" max="20" value="{$SETTINGS.limit}"></div>
				</div>
				<div class="customWidgetError text-danger" style="display:none"></div>
			</div>
			{include file='ModalFooter.tpl'|@vtemplate_path:$MODULE_NAME}
		</form>
	</div>
</div>
{/strip}

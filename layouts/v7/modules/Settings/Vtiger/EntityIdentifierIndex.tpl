{*+**********************************************************************************
* modules/Settings/Vtiger/views/EntityIdentifierIndex.php
************************************************************************************}
{strip}
<div class="col-lg-12 col-md-12 col-sm-12" id="entityIdentifierContainer">
	<div class="editViewHeader">
		<h4>{vtranslate('LBL_ENTITY_IDENTIFIER', $QUALIFIED_MODULE)}</h4>
	</div>
	<hr>

	<div class="vt-default-callout vt-info-callout">
		<h4 class="vt-callout-header"><span class="fa fa-info-circle"></span>{vtranslate('LBL_INFO', $QUALIFIED_MODULE)}</h4>
		<p>{vtranslate('LBL_ENTITY_IDENTIFIER_INFO', $QUALIFIED_MODULE)}</p>
	</div>

	<div class="row" style="padding:15px;">
		<div class="col-lg-6">
			<label>{vtranslate('LBL_SELECT_MODULE', $QUALIFIED_MODULE)}</label>
			<select id="entityIdentifierModule" class="select2" style="width:100%;">
				<option value="">{vtranslate('LBL_SELECT_OPTION', $QUALIFIED_MODULE)}</option>
				{foreach item=MODULE_MODEL from=$ENTITY_MODULES}
					<option value="{$MODULE_MODEL->getName()}">{vtranslate($MODULE_MODEL->get('label'), $MODULE_MODEL->getName())}</option>
				{/foreach}
			</select>
		</div>
	</div>

	<div class="row" id="entityIdentifierFieldsRow" style="padding:0 15px 15px 15px; display:none;">
		<div class="col-lg-6">
			<label>{vtranslate('LBL_AVAILABLE_FIELDS', $QUALIFIED_MODULE)}</label>
			<div id="entityIdentifierFieldsList" class="entityIdentifierFieldsList"></div>
		</div>
		<div class="col-lg-6">
			<label>{vtranslate('LBL_IDENTIFIER_ORDER', $QUALIFIED_MODULE)}</label>
			<p class="muted">{vtranslate('LBL_IDENTIFIER_ORDER_INFO', $QUALIFIED_MODULE)}</p>
			<ul id="entityIdentifierSelectedList" class="entityIdentifierSelectedList"></ul>
			<button type="button" class="btn btn-success" id="entityIdentifierSaveButton" disabled>
				{vtranslate('LBL_SAVE', $QUALIFIED_MODULE)}
			</button>
		</div>
	</div>
</div>
{/strip}

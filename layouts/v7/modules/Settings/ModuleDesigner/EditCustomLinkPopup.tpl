<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">{vtranslate('LBL_CUSTOM_LINK', $QUALIFIED_MODULE)} - {vtranslate({$a_customLink.type}, $QUALIFIED_MODULE)}</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">

<table id="form" style="font-size:12px;">
<tr><td colspan="2"><h3>{vtranslate("LBL_CUSTOM_LINK_DESCRIPTION", $QUALIFIED_MODULE)}</h3></td></tr>
<tr>
	<td>{vtranslate("LBL_CUSTOM_LINK_LABEL", $QUALIFIED_MODULE)}</td>
	<td><input type="text" name="label" value="{if !empty($a_customLink.label)}{$a_customLink.label}{else}LBL_{/if}" size="50" onkeyup="md_setLabel(this, 'label', '')" /></td>
</tr>
{foreach item=language from=$a_languages}
{assign var="label" value='label_'|cat:$language}
<tr>
	<td>{vtranslate("LBL_CUSTOM_LINK_LABEL_TRANSLATION", $QUALIFIED_MODULE)} <em>{$language}</em></td>
	<td><input type="text" name="label-{$language}" size="50" value="{$a_customLink.$label}" /></td>
</tr>
{/foreach}
<tr>
	<td colspan="2">&nbsp;</td>
</tr>
<tr>
	<td>{vtranslate("LBL_CUSTOM_LINK_URL", $QUALIFIED_MODULE)}</td>
	<td><input type="text" name="url" size="50" value="{$a_customLink.url}" /></td>
</tr>
<tr>
	<td><img src="layouts/v7/modules/Settings/{$MODULE}/resources/images/icon.png" alt="{vtranslate('LBL_CUSTOM_LINK_ICON_ALT', $QUALIFIED_MODULE)}" /> {vtranslate("LBL_CUSTOM_LINK_ICON", $QUALIFIED_MODULE)}</td>
	<td><input type="text" name="icon" size="50" value="{vtranslate($a_customLink.icon)}" /></td>
</tr>
<tr>
	<td><img src="layouts/v7/modules/Settings/{$MODULE}/resources/images/handler-path.png" alt="{vtranslate('LBL_CUSTOM_LINK_HANDLER_PATH_ALT', $QUALIFIED_MODULE)}" /> {vtranslate("LBL_CUSTOM_LINK_HANDLER_PATH", $QUALIFIED_MODULE)}</td>
	<td colspan="2"><input type="text" name="handler_path" size="50" value="{$a_customLink.handlerPath}" /></td>
</tr>
<tr>
	<td><img src="layouts/v7/modules/Settings/{$MODULE}/resources/images/handler-class.png" alt="{vtranslate('LBL_CUSTOM_LINK_HANDLER_CLASS_ALT', $QUALIFIED_MODULE)}" /> {vtranslate("LBL_CUSTOM_LINK_HANDLER_CLASS", $QUALIFIED_MODULE)}</td>
	<td><input type="text" name="handler_class" size="50" value="{$a_customLink.handlerClass}" /></td>
</tr>
<tr>
	<td>{vtranslate("LBL_CUSTOM_LINK_HANDLER", $QUALIFIED_MODULE)}</td>
	<td><input type="text" name="handler" size="50" value="{$a_customLink.handler}" /></td>
</tr>
<tr>
	<td colspan="2">&nbsp;</td>
</tr>
<tr>
	<td>&nbsp;</td>
	<td><input type="button" onclick="md_popupSave();" value="{vtranslate('LBL_SAVE', $QUALIFIED_MODULE)}" /></td>
</tr>
</table>
</div><!-- /modal-body -->

<script type="text/javascript">
function md_popupSave()
{ldelim}
	var o_data = new Object();
	o_data.id				= {if !empty($a_customLink.id)}'{$a_customLink.id}'{else}undefined{/if};
	o_data.index			= {if !empty($a_customLink.index)}{$a_customLink.index}{else}undefined{/if};
	o_data.type				= '{$a_customLink.type}';
	o_data.label			= $("input[name='label']").val();
	o_data.url				= $("input[name='url']").val();
	o_data.icon				= $("input[name='icon']").val();
	o_data.handlerPath		= $("input[name='handler_path']").val();
	o_data.handlerClass		= $("input[name='handler_class']").val();
	o_data.handler			= $("input[name='handler']").val();

{foreach item=language from=$a_languages}
	o_data.label_{$language} = $("input[name='label-{$language}']").val();	
{/foreach}
	
	var valid = false;
	var field = '';
	
	if(o_data.label == '' || o_data.label == 'LBL_')
		field = '{(vtranslate("LBL_CUSTOM_LINK_LABEL", $QUALIFIED_MODULE))|escape:"javascript"}';
{foreach item=language from=$a_languages}
	else if(o_data.label_{$language} == '')
		field = '{(vtranslate("LBL_CUSTOM_LINK_LABEL_TRANSLATION", $QUALIFIED_MODULE)|cat:' '|cat:$language)|escape:"javascript"}';
{/foreach}
	else if(o_data.url == '')
		field = '{vtranslate("LBL_CUSTOM_LINK_URL", $QUALIFIED_MODULE)}';
	else
		valid = true;

	if(!valid)
		app.helper.showAlertBox({ldelim}message: "{vtranslate('LBL_FIELD_VALUE_HAS_TO_BE_DEFINED', $QUALIFIED_MODULE)} "+field{rdelim});
	else
	{ldelim}
		md_addCustomLink(o_data, false);
		md_closePopup();
	{rdelim}
{rdelim}
</script>
</div><!-- /modal-content -->
</div><!-- /modal-dialog -->

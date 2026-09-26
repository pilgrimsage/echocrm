<?php
/* Smarty version 4.5.7, created on 2026-09-23 22:25:51
  from '/Users/pilgrimsage/Desktop/Personal/vtigercrm/layouts/v7/modules/Settings/ModuleDesigner/EditCustomLinkPopup.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.5.7',
  'unifunc' => 'content_6ab451efbfd910_84068437',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '72180fac071db06d33f38445365957767c17c3df' => 
    array (
      0 => '/Users/pilgrimsage/Desktop/Personal/vtigercrm/layouts/v7/modules/Settings/ModuleDesigner/EditCustomLinkPopup.tpl',
      1 => 1790146781,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_6ab451efbfd910_84068437 (Smarty_Internal_Template $_smarty_tpl) {
?><div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title"><?php echo vtranslate('LBL_CUSTOM_LINK',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
 - <?php ob_start();
echo $_smarty_tpl->tpl_vars['a_customLink']->value['type'];
$_prefixVariable1 = ob_get_clean();
echo vtranslate($_prefixVariable1,$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">

<table id="form" style="font-size:12px;">
<tr><td colspan="2"><h3><?php echo vtranslate("LBL_CUSTOM_LINK_DESCRIPTION",$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</h3></td></tr>
<tr>
	<td><?php echo vtranslate("LBL_CUSTOM_LINK_LABEL",$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</td>
	<td><input type="text" name="label" value="<?php if (!empty($_smarty_tpl->tpl_vars['a_customLink']->value['label'])) {
echo $_smarty_tpl->tpl_vars['a_customLink']->value['label'];
} else { ?>LBL_<?php }?>" size="50" onkeyup="md_setLabel(this, 'label', '')" /></td>
</tr>
<?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['a_languages']->value, 'language');
$_smarty_tpl->tpl_vars['language']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['language']->value) {
$_smarty_tpl->tpl_vars['language']->do_else = false;
$_smarty_tpl->_assignInScope('label', ('label_').($_smarty_tpl->tpl_vars['language']->value));?>
<tr>
	<td><?php echo vtranslate("LBL_CUSTOM_LINK_LABEL_TRANSLATION",$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
 <em><?php echo $_smarty_tpl->tpl_vars['language']->value;?>
</em></td>
	<td><input type="text" name="label-<?php echo $_smarty_tpl->tpl_vars['language']->value;?>
" size="50" value="<?php echo $_smarty_tpl->tpl_vars['a_customLink']->value[$_smarty_tpl->tpl_vars['label']->value];?>
" /></td>
</tr>
<?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
<tr>
	<td colspan="2">&nbsp;</td>
</tr>
<tr>
	<td><?php echo vtranslate("LBL_CUSTOM_LINK_URL",$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</td>
	<td><input type="text" name="url" size="50" value="<?php echo $_smarty_tpl->tpl_vars['a_customLink']->value['url'];?>
" /></td>
</tr>
<tr>
	<td><img src="layouts/v7/modules/Settings/<?php echo $_smarty_tpl->tpl_vars['MODULE']->value;?>
/resources/images/icon.png" alt="<?php echo vtranslate('LBL_CUSTOM_LINK_ICON_ALT',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
" /> <?php echo vtranslate("LBL_CUSTOM_LINK_ICON",$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</td>
	<td><input type="text" name="icon" size="50" value="<?php echo vtranslate($_smarty_tpl->tpl_vars['a_customLink']->value['icon']);?>
" /></td>
</tr>
<tr>
	<td><img src="layouts/v7/modules/Settings/<?php echo $_smarty_tpl->tpl_vars['MODULE']->value;?>
/resources/images/handler-path.png" alt="<?php echo vtranslate('LBL_CUSTOM_LINK_HANDLER_PATH_ALT',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
" /> <?php echo vtranslate("LBL_CUSTOM_LINK_HANDLER_PATH",$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</td>
	<td colspan="2"><input type="text" name="handler_path" size="50" value="<?php echo $_smarty_tpl->tpl_vars['a_customLink']->value['handlerPath'];?>
" /></td>
</tr>
<tr>
	<td><img src="layouts/v7/modules/Settings/<?php echo $_smarty_tpl->tpl_vars['MODULE']->value;?>
/resources/images/handler-class.png" alt="<?php echo vtranslate('LBL_CUSTOM_LINK_HANDLER_CLASS_ALT',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
" /> <?php echo vtranslate("LBL_CUSTOM_LINK_HANDLER_CLASS",$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</td>
	<td><input type="text" name="handler_class" size="50" value="<?php echo $_smarty_tpl->tpl_vars['a_customLink']->value['handlerClass'];?>
" /></td>
</tr>
<tr>
	<td><?php echo vtranslate("LBL_CUSTOM_LINK_HANDLER",$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</td>
	<td><input type="text" name="handler" size="50" value="<?php echo $_smarty_tpl->tpl_vars['a_customLink']->value['handler'];?>
" /></td>
</tr>
<tr>
	<td colspan="2">&nbsp;</td>
</tr>
<tr>
	<td>&nbsp;</td>
	<td><input type="button" onclick="md_popupSave();" value="<?php echo vtranslate('LBL_SAVE',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
" /></td>
</tr>
</table>
</div><!-- /modal-body -->

<?php echo '<script'; ?>
 type="text/javascript">
function md_popupSave()
{
	var o_data = new Object();
	o_data.id				= <?php if (!empty($_smarty_tpl->tpl_vars['a_customLink']->value['id'])) {?>'<?php echo $_smarty_tpl->tpl_vars['a_customLink']->value['id'];?>
'<?php } else { ?>undefined<?php }?>;
	o_data.index			= <?php if (!empty($_smarty_tpl->tpl_vars['a_customLink']->value['index'])) {
echo $_smarty_tpl->tpl_vars['a_customLink']->value['index'];
} else { ?>undefined<?php }?>;
	o_data.type				= '<?php echo $_smarty_tpl->tpl_vars['a_customLink']->value['type'];?>
';
	o_data.label			= $("input[name='label']").val();
	o_data.url				= $("input[name='url']").val();
	o_data.icon				= $("input[name='icon']").val();
	o_data.handlerPath		= $("input[name='handler_path']").val();
	o_data.handlerClass		= $("input[name='handler_class']").val();
	o_data.handler			= $("input[name='handler']").val();

<?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['a_languages']->value, 'language');
$_smarty_tpl->tpl_vars['language']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['language']->value) {
$_smarty_tpl->tpl_vars['language']->do_else = false;
?>
	o_data.label_<?php echo $_smarty_tpl->tpl_vars['language']->value;?>
 = $("input[name='label-<?php echo $_smarty_tpl->tpl_vars['language']->value;?>
']").val();	
<?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
	
	var valid = false;
	var field = '';
	
	if(o_data.label == '' || o_data.label == 'LBL_')
		field = '<?php echo strtr((string)(vtranslate("LBL_CUSTOM_LINK_LABEL",$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value)), array("\\" => "\\\\", "'" => "\\'", "\"" => "\\\"", "\r" => "\\r", 
                       "\n" => "\\n", "</" => "<\/", "<!--" => "<\!--", "<s" => "<\s", "<S" => "<\S",
                       "`" => "\\`", "\${" => "\\\$\{"));?>
';
<?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['a_languages']->value, 'language');
$_smarty_tpl->tpl_vars['language']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['language']->value) {
$_smarty_tpl->tpl_vars['language']->do_else = false;
?>
	else if(o_data.label_<?php echo $_smarty_tpl->tpl_vars['language']->value;?>
 == '')
		field = '<?php echo strtr((string)(((vtranslate("LBL_CUSTOM_LINK_LABEL_TRANSLATION",$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value)).(' ')).($_smarty_tpl->tpl_vars['language']->value)), array("\\" => "\\\\", "'" => "\\'", "\"" => "\\\"", "\r" => "\\r", 
                       "\n" => "\\n", "</" => "<\/", "<!--" => "<\!--", "<s" => "<\s", "<S" => "<\S",
                       "`" => "\\`", "\${" => "\\\$\{"));?>
';
<?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
	else if(o_data.url == '')
		field = '<?php echo vtranslate("LBL_CUSTOM_LINK_URL",$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
';
	else
		valid = true;

	if(!valid)
		app.helper.showAlertBox({message: "<?php echo vtranslate('LBL_FIELD_VALUE_HAS_TO_BE_DEFINED',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
 "+field});
	else
	{
		md_addCustomLink(o_data, false);
		md_closePopup();
	}
}
<?php echo '</script'; ?>
>
</div><!-- /modal-content -->
</div><!-- /modal-dialog -->
<?php }
}

<?php
/* Smarty version 4.5.7, created on 2026-09-23 21:18:37
  from '/Users/pilgrimsage/Desktop/Personal/vtigercrm/layouts/v7/modules/Settings/ModuleDesigner/General.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.5.7',
  'unifunc' => 'content_6ab4422daf18f6_20907372',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'c21bbfcac11d794f5d25e84b3581146e8a1058eb' => 
    array (
      0 => '/Users/pilgrimsage/Desktop/Personal/vtigercrm/layouts/v7/modules/Settings/ModuleDesigner/General.tpl',
      1 => 1790145408,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_6ab4422daf18f6_20907372 (Smarty_Internal_Template $_smarty_tpl) {
?><h2><img src="layouts/v7/modules/Settings/<?php echo $_smarty_tpl->tpl_vars['MODULE']->value;?>
/resources/images/template.png" alt="<?php echo vtranslate('LBL_TEMPLATE_ALT',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
" /> <?php echo vtranslate('LBL_TEMPLATE',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</h2>

<table>
<tr>
	<td><?php echo vtranslate('LBL_DIRECTORY_TEMPLATE',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</td>
	<td>
		<select name="module_directory_template" onChange="md_selectDirectory(this)">
			<option value=""><?php echo vtranslate('LBL_SELECT_OPTION',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</option>
			<?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['LIST_DIR_TEMPLATES']->value, 'template');
$_smarty_tpl->tpl_vars['template']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['template']->value) {
$_smarty_tpl->tpl_vars['template']->do_else = false;
?>
			<option value="<?php echo $_smarty_tpl->tpl_vars['template']->value;?>
"><?php echo $_smarty_tpl->tpl_vars['template']->value;?>
</option>
			<?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
		</select>
	</td>
</tr>
<tr>
	<td><?php echo vtranslate('LBL_MANIFEST_TEMPLATE',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</td>
	<td>
		<select name="module_manifest_template">
			<option value=""><?php echo vtranslate('LBL_SELECT_OPTION',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</option>
			<?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['LIST_MANIFEST_TEMPLATES']->value, 'template');
$_smarty_tpl->tpl_vars['template']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['template']->value) {
$_smarty_tpl->tpl_vars['template']->do_else = false;
?>
			<option value="<?php echo $_smarty_tpl->tpl_vars['template']->value;?>
"><?php echo $_smarty_tpl->tpl_vars['template']->value;?>
</option>
			<?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
		</select>
	</td>
</tr>
</table>

<h2><img src="layouts/v7/modules/Settings/<?php echo $_smarty_tpl->tpl_vars['MODULE']->value;?>
/resources/images/module.png" alt="<?php echo vtranslate('LBL_MODULE_ALT',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
" /> <?php echo vtranslate('LBL_MODULE',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</h2>

<table id="md-module-name">
<tr>
	<td><?php echo vtranslate('LBL_SYSTEM_MODULE_NAME',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</td>
	<td>
		<input type="text" name="module_name" class="md-medium-text-input" maxlength="25" onkeyup="md_setModuleName(this)" onfocusout="md_updateFieldsTableName(this)" />
		<input type="hidden" name="old_module_table_name" />
	</td>
	<td>
		<a href="javascript:showLoadModulePopup()"><img src="layouts/v7/modules/Settings/<?php echo $_smarty_tpl->tpl_vars['MODULE']->value;?>
/resources/images/load.png" alt="<?php echo vtranslate('LBL_LOAD_MODULE_ALT',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
" title="<?php echo vtranslate('LBL_LOAD_MODULE',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
" /></a> &nbsp;
		<a href="javascript:showUploadModulePopup()"><img src="layouts/v7/modules/Settings/<?php echo $_smarty_tpl->tpl_vars['MODULE']->value;?>
/resources/images/upload.png" alt="<?php echo vtranslate('LBL_UPLOAD_MODULE_ALT',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
" title="<?php echo vtranslate('LBL_UPLOAD_MODULE',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
" /></a>
	</td>
</tr>
<tr>
	<td><?php echo vtranslate('LBL_VERSION',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</td>
	<td colspan="2">
		<input type="text" name="module_version" class="md-medium-text-input" maxlength="25" />
	</td>
</tr>
</table>

<h2><img src="layouts/v7/modules/Settings/<?php echo $_smarty_tpl->tpl_vars['MODULE']->value;?>
/resources/images/menu.png" alt="<?php echo vtranslate('LBL_PARENT_TAB_ALT',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
" /> <?php echo vtranslate('LBL_PARENT_TAB',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</h2>

<table>
<tr>
	<td><?php echo vtranslate('LBL_PARENT_TAB_CHOICE',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</td>
	<td>
		<select name="module_parent_tab">
			<option value=""><?php echo vtranslate('LBL_SELECT_OPTION',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</option>
			<?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['LIST_PARENT_TABS']->value, 'parent_tab');
$_smarty_tpl->tpl_vars['parent_tab']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['parent_tab']->value) {
$_smarty_tpl->tpl_vars['parent_tab']->do_else = false;
?>
				<option value="<?php echo $_smarty_tpl->tpl_vars['parent_tab']->value['parenttab_label'];?>
"><?php echo vtranslate($_smarty_tpl->tpl_vars['parent_tab']->value['parenttab_label']);?>
</option>
			<?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
		</select>
	</td>
</tr>
</table>

<input type="hidden" name="md-languages" />
<input type="hidden" name="md_modified_module" />
<input type="hidden" name="md_modified_module_path" /><?php }
}

<?php
/* Smarty version 4.5.7, created on 2026-09-23 21:18:37
  from '/Users/pilgrimsage/Desktop/Personal/vtigercrm/layouts/v7/modules/Settings/ModuleDesigner/Custom.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.5.7',
  'unifunc' => 'content_6ab4422db07dd6_29507782',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '96806fcfcda4abed7991e3a6c44310aa251e2b9b' => 
    array (
      0 => '/Users/pilgrimsage/Desktop/Personal/vtigercrm/layouts/v7/modules/Settings/ModuleDesigner/Custom.tpl',
      1 => 1790145420,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_6ab4422db07dd6_29507782 (Smarty_Internal_Template $_smarty_tpl) {
?><h2><?php echo vtranslate('LBL_CUSTOM_VALUES',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</h2>

<table>
<tr>
<td colspan="2">
<?php echo vtranslate('LBL_CUSTOM_VALUES_DESCRIPTION',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>

</td>
</tr>
<tr>
<td><?php echo vtranslate('LBL_MY_VARIABLE',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</td>
<td>
<select name="myVariable">
<option value="value1"><?php echo vtranslate('LBL_MY_VALUE',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
 1</option>
<option value="value2"><?php echo vtranslate('LBL_MY_VALUE',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
 2</option>
</select>
</td>
</tr>
</table><?php }
}

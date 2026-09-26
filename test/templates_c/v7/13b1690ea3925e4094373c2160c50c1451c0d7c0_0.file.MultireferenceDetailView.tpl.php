<?php
/* Smarty version 4.5.7, created on 2026-09-23 22:03:51
  from '/Users/pilgrimsage/Desktop/Personal/vtigercrm/layouts/v7/modules/Events/uitypes/MultireferenceDetailView.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.5.7',
  'unifunc' => 'content_6ab44cc7b6cb41_85657791',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '13b1690ea3925e4094373c2160c50c1451c0d7c0' => 
    array (
      0 => '/Users/pilgrimsage/Desktop/Personal/vtigercrm/layouts/v7/modules/Events/uitypes/MultireferenceDetailView.tpl',
      1 => 1790019580,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_6ab44cc7b6cb41_85657791 (Smarty_Internal_Template $_smarty_tpl) {
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['RELATED_CONTACTS']->value, 'CONTACT_INFO');
$_smarty_tpl->tpl_vars['CONTACT_INFO']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['CONTACT_INFO']->value) {
$_smarty_tpl->tpl_vars['CONTACT_INFO']->do_else = false;
?><a href='<?php echo $_smarty_tpl->tpl_vars['CONTACT_INFO']->value['_model']->getDetailViewUrl();?>
' title='<?php echo vtranslate("Contacts","Contacts");?>
'> <?php echo Vtiger_Util_Helper::getRecordName($_smarty_tpl->tpl_vars['CONTACT_INFO']->value['id']);?>
</a><br><?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);
}
}

<?php
/* Smarty version 4.5.7, created on 2026-09-23 22:04:51
  from '/Users/pilgrimsage/Desktop/Personal/vtigercrm/layouts/v7/modules/Settings/Vtiger/EntityIdentifierIndex.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.5.7',
  'unifunc' => 'content_6ab44d030668a1_61100308',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'b3a118198551eafc9cd99fb0b25430e27eb09ae5' => 
    array (
      0 => '/Users/pilgrimsage/Desktop/Personal/vtigercrm/layouts/v7/modules/Settings/Vtiger/EntityIdentifierIndex.tpl',
      1 => 1790153520,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_6ab44d030668a1_61100308 (Smarty_Internal_Template $_smarty_tpl) {
?><div class="col-lg-12 col-md-12 col-sm-12" id="entityIdentifierContainer"><div class="editViewHeader"><h4><?php echo vtranslate('LBL_ENTITY_IDENTIFIER',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</h4></div><hr><div class="vt-default-callout vt-info-callout"><h4 class="vt-callout-header"><span class="fa fa-info-circle"></span><?php echo vtranslate('LBL_INFO',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</h4><p><?php echo vtranslate('LBL_ENTITY_IDENTIFIER_INFO',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</p></div><div class="row" style="padding:15px;"><div class="col-lg-6"><label><?php echo vtranslate('LBL_SELECT_MODULE',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</label><select id="entityIdentifierModule" class="select2" style="width:100%;"><option value=""><?php echo vtranslate('LBL_SELECT_OPTION',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</option><?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['ENTITY_MODULES']->value, 'MODULE_MODEL');
$_smarty_tpl->tpl_vars['MODULE_MODEL']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['MODULE_MODEL']->value) {
$_smarty_tpl->tpl_vars['MODULE_MODEL']->do_else = false;
?><option value="<?php echo $_smarty_tpl->tpl_vars['MODULE_MODEL']->value->getName();?>
"><?php echo vtranslate($_smarty_tpl->tpl_vars['MODULE_MODEL']->value->get('label'),$_smarty_tpl->tpl_vars['MODULE_MODEL']->value->getName());?>
</option><?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?></select></div></div><div class="row" id="entityIdentifierFieldsRow" style="padding:0 15px 15px 15px; display:none;"><div class="col-lg-6"><label><?php echo vtranslate('LBL_AVAILABLE_FIELDS',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</label><div id="entityIdentifierFieldsList" class="entityIdentifierFieldsList"></div></div><div class="col-lg-6"><label><?php echo vtranslate('LBL_IDENTIFIER_ORDER',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</label><p class="muted"><?php echo vtranslate('LBL_IDENTIFIER_ORDER_INFO',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</p><ul id="entityIdentifierSelectedList" class="entityIdentifierSelectedList"></ul><button type="button" class="btn btn-success" id="entityIdentifierSaveButton" disabled><?php echo vtranslate('LBL_SAVE',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</button></div></div></div>
<?php }
}

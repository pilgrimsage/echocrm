<?php
/* Smarty version 4.5.7, created on 2026-09-23 21:54:01
  from '/Users/pilgrimsage/Desktop/Personal/vtigercrm/layouts/v7/modules/Vtiger/ModuleSearchResults.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.5.7',
  'unifunc' => 'content_6ab44a791f06c1_48526468',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '9d3017aa9e57cf8abbbaaa6f9beee13857130892' => 
    array (
      0 => '/Users/pilgrimsage/Desktop/Personal/vtigercrm/layouts/v7/modules/Vtiger/ModuleSearchResults.tpl',
      1 => 1790019576,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_6ab44a791f06c1_48526468 (Smarty_Internal_Template $_smarty_tpl) {
?>
<div class="listViewPageDiv"><div class="row"><div class="col-lg-12"><div class="col-lg-8"><h4 class="searchModuleHeader"><?php echo vtranslate($_smarty_tpl->tpl_vars['MODULE']->value,$_smarty_tpl->tpl_vars['MODULE']->value);?>
</h4><input type="hidden" name="search_module" value="<?php echo $_smarty_tpl->tpl_vars['MODULE']->value;?>
"/></div><div class="col-lg-4" style="margin-top: 10px;"><div class="float-end" ><input type="hidden" name="pageNumber" value="<?php echo $_smarty_tpl->tpl_vars['PAGE_NUMBER']->value;?>
"><input type="hidden" name="recordsCount" value="<?php echo $_smarty_tpl->tpl_vars['RECORDS_COUNT']->value;?>
"><span class="pageNumbersText" style="padding-right:5px"><?php echo $_smarty_tpl->tpl_vars['PAGING_MODEL']->value->getRecordStartRange();?>
 <?php echo vtranslate('LBL_to',$_smarty_tpl->tpl_vars['MODULE']->value);?>
 <?php echo $_smarty_tpl->tpl_vars['PAGING_MODEL']->value->getRecordEndRange();?>
 <?php echo vtranslate('LBL_OF',$_smarty_tpl->tpl_vars['MODULE']->value);?>
 <?php echo $_smarty_tpl->tpl_vars['RECORDS_COUNT']->value;?>
</span><a href="#" class="previousPageButton navigationButton verticalAlignMiddle" data-start='<?php echo $_smarty_tpl->tpl_vars['PAGING_MODEL']->value->getRecordStartRange()-$_smarty_tpl->tpl_vars['PAGING_MODEL']->value->getPageLimit();?>
' <?php if (!$_smarty_tpl->tpl_vars['PAGING_MODEL']->value->isPrevPageExists()) {?>disabled=""<?php }?>><i class="fa fa-caret-left"></i>&nbsp;&nbsp;</a><a href="#" class="nextPageButton navigationButton verticalAlignMiddle" data-start='<?php echo $_smarty_tpl->tpl_vars['PAGING_MODEL']->value->getRecordEndRange();?>
' <?php if (!$_smarty_tpl->tpl_vars['PAGING_MODEL']->value->isNextPageExists()) {?> disabled=""<?php }?>><i class="fa fa-caret-right"></i></a></div></div></div></div><div class="row"><?php $_smarty_tpl->_subTemplateRender(call_user_func_array($_smarty_tpl->registered_plugins[ 'modifier' ][ 'vtemplate_path' ][ 0 ], array( "ListViewContents.tpl",$_smarty_tpl->tpl_vars['MODULE']->value )), $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array('SEARCH_MODE_RESULTS'=>true), 0, true);
?></div></div><?php }
}

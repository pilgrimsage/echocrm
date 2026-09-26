<?php
/* Smarty version 4.5.7, created on 2026-09-23 21:24:11
  from '/Users/pilgrimsage/Desktop/Personal/vtigercrm/layouts/v7/modules/Vtiger/ListViewQuickPreview.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.5.7',
  'unifunc' => 'content_6ab4437b5050c1_72754476',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '9adbf19679052a363abc21bc36547f67d34c5186' => 
    array (
      0 => '/Users/pilgrimsage/Desktop/Personal/vtigercrm/layouts/v7/modules/Vtiger/ListViewQuickPreview.tpl',
      1 => 1790019577,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_6ab4437b5050c1_72754476 (Smarty_Internal_Template $_smarty_tpl) {
?><div class = "quickPreview">
    <input type="hidden" name="sourceModuleName" id="sourceModuleName" value="<?php echo $_smarty_tpl->tpl_vars['MODULE_NAME']->value;?>
" />
    <input type="hidden" id = "nextRecordId" value ="<?php echo $_smarty_tpl->tpl_vars['NEXT_RECORD_ID']->value;?>
">
    <input type="hidden" id = "previousRecordId" value ="<?php echo $_smarty_tpl->tpl_vars['PREVIOUS_RECORD_ID']->value;?>
">

    <div class='quick-preview-modal modal-content'>
        <div class='modal-body'>
            <div class = "quickPreviewModuleHeader row">
                <div class = "col-lg-10">
                    <div class="row qp-heading">
                        <?php $_smarty_tpl->_subTemplateRender(call_user_func_array($_smarty_tpl->registered_plugins[ 'modifier' ][ 'vtemplate_path' ][ 0 ], array( "ListViewQuickPreviewHeaderTitle.tpl",$_smarty_tpl->tpl_vars['MODULE_NAME']->value )), $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array('MODULE_MODEL'=>$_smarty_tpl->tpl_vars['MODULE_MODEL']->value,'RECORD'=>$_smarty_tpl->tpl_vars['RECORD']->value), 0, true);
?>
                    </div>
                </div>
                <div class = "col-lg-2 float-end">
                    <button class="close" aria-hidden="true" data-bs-dismiss="modal" type="button" title="<?php echo vtranslate('LBL_CLOSE');?>
">x</button>
                </div>
            </div>

            <div class="quickPreviewActions clearfix">
                <div class="btn-group float-start">
                    <button class="btn btn-success btn-xs" onclick="window.location.href = '<?php echo $_smarty_tpl->tpl_vars['RECORD']->value->getFullDetailViewUrl();?>
&app=<?php echo (isset($_smarty_tpl->tpl_vars['SELECTED_MENU_CATEGORY']->value)) ? $_smarty_tpl->tpl_vars['SELECTED_MENU_CATEGORY']->value : '';?>
'">
                       <?php echo vtranslate('LBL_VIEW_DETAILS',$_smarty_tpl->tpl_vars['MODULE_NAME']->value);?>
 
                    </button>
                </div>
                <?php if ($_smarty_tpl->tpl_vars['NAVIGATION']->value) {?>
                    <div class="btn-group float-end">
                        <button class="btn btn-default btn-xs" id="quickPreviewPreviousRecordButton" data-record="<?php echo $_smarty_tpl->tpl_vars['PREVIOUS_RECORD_ID']->value;?>
" data-app="<?php echo (isset($_smarty_tpl->tpl_vars['SELECTED_MENU_CATEGORY']->value)) ? $_smarty_tpl->tpl_vars['SELECTED_MENU_CATEGORY']->value : '';?>
" <?php if (empty($_smarty_tpl->tpl_vars['PREVIOUS_RECORD_ID']->value)) {?> disabled="disabled" <?php }?> >
                            <i class="fa fa-chevron-left"></i>
                        </button>
                        <button class="btn btn-default btn-xs" id="quickPreviewNextRecordButton" data-record="<?php echo $_smarty_tpl->tpl_vars['NEXT_RECORD_ID']->value;?>
" data-app="<?php echo (isset($_smarty_tpl->tpl_vars['SELECTED_MENU_CATEGORY']->value)) ? $_smarty_tpl->tpl_vars['SELECTED_MENU_CATEGORY']->value : '';?>
" <?php if (empty($_smarty_tpl->tpl_vars['NEXT_RECORD_ID']->value)) {?> disabled="disabled" <?php }?>>
                            <i class="fa fa-chevron-right"></i>
                        </button>
                    </div>
                <?php }?>

            </div>
            <div class = "quickPreviewSummary">
                <table class="summary-table no-border" style="width:100%;">
                    <tbody>
                        <?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['SUMMARY_RECORD_STRUCTURE']->value['SUMMARY_FIELDS'], 'FIELD_MODEL', false, 'FIELD_NAME');
$_smarty_tpl->tpl_vars['FIELD_MODEL']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['FIELD_NAME']->value => $_smarty_tpl->tpl_vars['FIELD_MODEL']->value) {
$_smarty_tpl->tpl_vars['FIELD_MODEL']->do_else = false;
?>
                            <?php if ($_smarty_tpl->tpl_vars['FIELD_MODEL']->value->get('name') != 'modifiedtime' && $_smarty_tpl->tpl_vars['FIELD_MODEL']->value->get('name') != 'createdtime') {?>
                                <tr class="summaryViewEntries">
                                    <td class="fieldLabel col-lg-5" ><label class="muted"><?php echo vtranslate($_smarty_tpl->tpl_vars['FIELD_MODEL']->value->get('label'),$_smarty_tpl->tpl_vars['MODULE_NAME']->value);?>
</label></td>
                                    <td class="fieldValue col-lg-7">
                                        <div class="row">
                                            <span class="value textOverflowEllipsis" <?php if ($_smarty_tpl->tpl_vars['FIELD_MODEL']->value->get('uitype') == '19' || $_smarty_tpl->tpl_vars['FIELD_MODEL']->value->get('uitype') == '20' || $_smarty_tpl->tpl_vars['FIELD_MODEL']->value->get('uitype') == '21') {?>style="word-wrap: break-word;"<?php }?>>
                                                <?php $_smarty_tpl->_subTemplateRender(call_user_func_array($_smarty_tpl->registered_plugins[ 'modifier' ][ 'vtemplate_path' ][ 0 ], array( $_smarty_tpl->tpl_vars['FIELD_MODEL']->value->getUITypeModel()->getDetailViewTemplateName(),$_smarty_tpl->tpl_vars['MODULE_NAME']->value )), $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array('FIELD_MODEL'=>$_smarty_tpl->tpl_vars['FIELD_MODEL']->value,'USER_MODEL'=>$_smarty_tpl->tpl_vars['USER_MODEL']->value,'MODULE'=>$_smarty_tpl->tpl_vars['MODULE_NAME']->value,'RECORD'=>$_smarty_tpl->tpl_vars['RECORD']->value), 0, true);
?>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php }?>
                        <?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
                    </tbody>
                </table>
            </div>

            <div class="engagementsContainer">
				<?php ob_start();
echo vtranslate('LBL_UPDATES',$_smarty_tpl->tpl_vars['MODULE_NAME']->value);
$_prefixVariable1=ob_get_clean();
$_smarty_tpl->_subTemplateRender(call_user_func_array($_smarty_tpl->registered_plugins[ 'modifier' ][ 'vtemplate_path' ][ 0 ], array( "ListViewQuickPreviewSectionHeader.tpl",$_smarty_tpl->tpl_vars['MODULE_NAME']->value )), $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array('TITLE'=>$_prefixVariable1), 0, true);
?>
				<?php $_smarty_tpl->_subTemplateRender(call_user_func_array($_smarty_tpl->registered_plugins[ 'modifier' ][ 'vtemplate_path' ][ 0 ], array( "RecentActivities.tpl",$_smarty_tpl->tpl_vars['MODULE_NAME']->value )), $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, true);
?>
            </div>

            <br>
            <?php if ($_smarty_tpl->tpl_vars['MODULE_MODEL']->value->isCommentEnabled()) {?>
                <div class="quickPreviewComments">
                    <?php ob_start();
echo vtranslate('LBL_RECENT_COMMENTS',$_smarty_tpl->tpl_vars['MODULE_NAME']->value);
$_prefixVariable2=ob_get_clean();
$_smarty_tpl->_subTemplateRender(call_user_func_array($_smarty_tpl->registered_plugins[ 'modifier' ][ 'vtemplate_path' ][ 0 ], array( "ListViewQuickPreviewSectionHeader.tpl",$_smarty_tpl->tpl_vars['MODULE_NAME']->value )), $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array('TITLE'=>$_prefixVariable2), 0, true);
?>
                    <?php $_smarty_tpl->_subTemplateRender(call_user_func_array($_smarty_tpl->registered_plugins[ 'modifier' ][ 'vtemplate_path' ][ 0 ], array( "QuickViewCommentsList.tpl",$_smarty_tpl->tpl_vars['MODULE_NAME']->value )), $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, true);
?>
                </div>
            <?php }?>
        </div>
    </div>
</div>

<?php }
}

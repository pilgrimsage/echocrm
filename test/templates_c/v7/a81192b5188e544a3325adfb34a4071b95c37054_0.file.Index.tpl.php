<?php
/* Smarty version 4.5.7, created on 2026-09-23 21:18:37
  from '/Users/pilgrimsage/Desktop/Personal/vtigercrm/layouts/v7/modules/Settings/ModuleDesigner/Index.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.5.7',
  'unifunc' => 'content_6ab4422dae66d2_05546174',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'a81192b5188e544a3325adfb34a4071b95c37054' => 
    array (
      0 => '/Users/pilgrimsage/Desktop/Personal/vtigercrm/layouts/v7/modules/Settings/ModuleDesigner/Index.tpl',
      1 => 1790145408,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_6ab4422dae66d2_05546174 (Smarty_Internal_Template $_smarty_tpl) {
?><div class="settingsHeader">
<a href="index.php?module=<?php echo $_smarty_tpl->tpl_vars['MODULE']->value;?>
&view=Index&parent=Settings"><?php echo vtranslate('LBL_MODULEDESIGNER',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</a>
<hr/>
</div>


<div id="md-container">
	<div id="md-header">
		<div id="md-tab-general" class="md-tab"><img src="layouts/v7/modules/Settings/<?php echo $_smarty_tpl->tpl_vars['MODULE']->value;?>
/resources/images/config.png" alt="<?php echo vtranslate('LBL_GENERAL_ALT',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
" /> <?php echo vtranslate('LBL_GENERAL',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</div>
		<div id="md-tab-blocks-fields" class="md-tab"><img src="layouts/v7/modules/Settings/<?php echo $_smarty_tpl->tpl_vars['MODULE']->value;?>
/resources/images/field.png" alt="<?php echo vtranslate('LBL_BLOCKS_FIELDS_ALT',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
" /> <?php echo vtranslate('LBL_BLOCKS_FIELDS',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</div>
		<div id="md-tab-custom-links" class="md-tab"><img src="layouts/v7/modules/Settings/<?php echo $_smarty_tpl->tpl_vars['MODULE']->value;?>
/resources/images/link.png" alt="<?php echo vtranslate('LBL_CUSTOM_LINKS_ALT',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
" /> <?php echo vtranslate('LBL_CUSTOM_LINKS',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</div>
		<div id="md-tab-related-lists" class="md-tab"><img src="layouts/v7/modules/Settings/<?php echo $_smarty_tpl->tpl_vars['MODULE']->value;?>
/resources/images/related.png" alt="<?php echo vtranslate('LBL_RELATED_LISTS_ALT',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
" /> <?php echo vtranslate('LBL_RELATED_LISTS',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</div>
		<div id="md-tab-events" class="md-tab"><img src="layouts/v7/modules/Settings/<?php echo $_smarty_tpl->tpl_vars['MODULE']->value;?>
/resources/images/event.png" alt="<?php echo vtranslate('LBL_EVENTS_ALT',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
" /> <?php echo vtranslate('LBL_EVENTS',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</div>
		<div id="md-tab-filters" class="md-tab"><img src="layouts/v7/modules/Settings/<?php echo $_smarty_tpl->tpl_vars['MODULE']->value;?>
/resources/images/filter.png" alt="<?php echo vtranslate('LBL_FILTERS_ALT',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
" /> <?php echo vtranslate('LBL_FILTERS',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</div>
		<div id="md-tab-custom" class="md-tab"><img src="layouts/v7/modules/Settings/<?php echo $_smarty_tpl->tpl_vars['MODULE']->value;?>
/resources/images/custom.png" alt="<?php echo vtranslate('LBL_CUSTOM_ALT',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
" /> <?php echo vtranslate('LBL_CUSTOM',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</div>
		<div id="md-tab-export" class="md-tab"><img src="layouts/v7/modules/Settings/<?php echo $_smarty_tpl->tpl_vars['MODULE']->value;?>
/resources/images/export.png" alt="<?php echo vtranslate('LBL_EXPORT_ALT',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
" /> <?php echo vtranslate('LBL_EXPORT',$_smarty_tpl->tpl_vars['QUALIFIED_MODULE']->value);?>
</div>
	</div><!-- md-header -->
	
	<div id="md-body">
			<div id="md-trash" class="md-trash"></div>
	
			<div id="md-page-general" class="md-page">
				<?php $_smarty_tpl->_subTemplateRender((('modules/Settings/').($_smarty_tpl->tpl_vars['MODULE']->value)).('/General.tpl'), $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, true);
?>
			</div><!-- md-page-general -->
			
			<div id="md-page-blocks-fields" class="md-page">
				<?php $_smarty_tpl->_subTemplateRender((('modules/Settings/').($_smarty_tpl->tpl_vars['MODULE']->value)).('/BlocksFields.tpl'), $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, true);
?>
			</div><!-- md-page-blocks-fields -->
			
			<div id="md-page-custom-links" class="md-page">
				<?php $_smarty_tpl->_subTemplateRender((('modules/Settings/').($_smarty_tpl->tpl_vars['MODULE']->value)).('/CustomLinks.tpl'), $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, true);
?>
			</div><!-- md-page-custom-links -->
			
			<div id="md-page-related-lists" class="md-page">
				<?php $_smarty_tpl->_subTemplateRender((('modules/Settings/').($_smarty_tpl->tpl_vars['MODULE']->value)).('/RelatedLists.tpl'), $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, true);
?>
			</div><!-- md-page-related-list -->
			
			<div id="md-page-events" class="md-page">
				<?php $_smarty_tpl->_subTemplateRender((('modules/Settings/').($_smarty_tpl->tpl_vars['MODULE']->value)).('/Events.tpl'), $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, true);
?>
			</div><!-- md-page-events -->
			
			<div id="md-page-filters" class="md-page">
				<?php $_smarty_tpl->_subTemplateRender((('modules/Settings/').($_smarty_tpl->tpl_vars['MODULE']->value)).('/Filters.tpl'), $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, true);
?>
			</div><!-- md-page-filters -->
			
			<div id="md-page-custom" class="md-page">
				<?php $_smarty_tpl->_subTemplateRender((('modules/Settings/').($_smarty_tpl->tpl_vars['MODULE']->value)).('/Custom.tpl'), $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, true);
?>
			</div><!-- md-page-custom -->
			
			<div id="md-page-export" class="md-page">
				<?php $_smarty_tpl->_subTemplateRender((('modules/Settings/').($_smarty_tpl->tpl_vars['MODULE']->value)).('/Export.tpl'), $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, true);
?>
			</div><!-- md-page-export -->	
			
			
	</div><!-- md-body -->

</div><!-- md-container --><?php }
}

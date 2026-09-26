<?php
/* Smarty version 4.5.7, created on 2026-09-23 21:18:30
  from '/Users/pilgrimsage/Desktop/Personal/vtigercrm/layouts/v7/modules/Vtiger/Footer.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.5.7',
  'unifunc' => 'content_6ab442263907c3_90621475',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '6ddae25d077a629e0de6d72cfa77587858381b9f' => 
    array (
      0 => '/Users/pilgrimsage/Desktop/Personal/vtigercrm/layouts/v7/modules/Vtiger/Footer.tpl',
      1 => 1790076443,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_6ab442263907c3_90621475 (Smarty_Internal_Template $_smarty_tpl) {
?>
<footer class="app-footer">
	<p>
		&copy; 2004 - <?php echo date('Y');?>
 EchoCrew
	</p>
</footer>
</div>
<div id='overlayPage' class='modal'>
	<!-- arrow is added to point arrow to the clicked element (Ex:- TaskManagement),
	any one can use this by adding "show" class to it -->
	<div class='arrow'></div>
	<div class='data'>
	</div>
</div>
<div id='helpPageOverlay' class='modal'></div>
<div id="js_strings" class="hide noprint"><?php echo Zend_Json::encode($_smarty_tpl->tpl_vars['LANGUAGE_STRINGS']->value);?>
</div>
<div id="maxListFieldsSelectionSize" class="hide noprint"><?php echo $_smarty_tpl->tpl_vars['MAX_LISTFIELDS_SELECTION_SIZE']->value;?>
</div>
<div class="modal myModal fade"></div>
<?php echo '<script'; ?>
>
(function () {
	var navEl = document.querySelector('.app-fixed-navbar');
	var resizeTimer;
	function syncNavbarHeight() {
		document.documentElement.style.setProperty('--app-navbar-height', (navEl ? navEl.offsetHeight : 0) + 'px');
	}
	syncNavbarHeight();
	if (navEl) {
		window.addEventListener('resize', function () {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(syncNavbarHeight, 100);
		});
	}
})();
<?php echo '</script'; ?>
>
<?php $_smarty_tpl->_subTemplateRender(call_user_func_array($_smarty_tpl->registered_plugins[ 'modifier' ][ 'vtemplate_path' ][ 0 ], array( 'JSResources.tpl' )), $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), 0, true);
?>
</body>

</html>
<?php }
}

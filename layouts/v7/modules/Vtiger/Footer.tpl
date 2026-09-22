{*+**********************************************************************************
* The contents of this file are subject to the vtiger CRM Public License Version 1.1
* ("License"); You may not use this file except in compliance with the License
* The Original Code is: vtiger CRM Open Source
* The Initial Developer of the Original Code is vtiger.
* Portions created by vtiger are Copyright (C) vtiger.
* All Rights Reserved.
************************************************************************************}

<footer class="app-footer">
	<p>
		&copy; 2004 - {date('Y')} EchoCrew
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
<div id="js_strings" class="hide noprint">{Zend_Json::encode($LANGUAGE_STRINGS)}</div>
<div id="maxListFieldsSelectionSize" class="hide noprint">{$MAX_LISTFIELDS_SELECTION_SIZE}</div>
<div class="modal myModal fade"></div>
<script>
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
</script>
{include file='JSResources.tpl'|@vtemplate_path}
</body>

</html>

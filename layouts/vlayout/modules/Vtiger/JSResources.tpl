{*<!--
/*********************************************************************************
  ** The contents of this file are subject to the vtiger CRM Public License Version 1.0
   * ("License"); You may not use this file except in compliance with the License
   * The Original Code is:  vtiger CRM Open Source
   * The Initial Developer of the Original Code is vtiger.
   * Portions created by vtiger are Copyright (C) vtiger.
   * All Rights Reserved.
  *
 ********************************************************************************/
-->*}


{* <script> resources below *}
	<script type="text/javascript" src="layouts/vlayout/lib/blockui/js/jquery.blockUI.js"></script>
	<script type="text/javascript" src="layouts/vlayout/lib/chosen/chosen.jquery.min.js"></script>
	<script type="text/javascript" src="layouts/v7/lib/jquery/select2/select2.min.js"></script>
	<script type="text/javascript" src="layouts/vlayout/lib/jquery-ui/js/jquery-ui-1.8.16.custom.min.js"></script>
	<script type="text/javascript" src="layouts/v7/lib/jquery/jquery.class.min.js"></script>
	<script type="text/javascript" src="libraries/jquery/defunkt-jquery-pjax/jquery.pjax.js"></script>
	<script type="text/javascript" src="layouts/vlayout/lib/autosize/jquery.autosize-min.js"></script>

	<script type="text/javascript" src="layouts/vlayout/lib/slimscroll/slimScroll.min.js"></script>
	<script type="text/javascript" src="libraries/jquery/pnotify/jquery.pnotify.min.js"></script>
	<script type="text/javascript" src="layouts/vlayout/lib/hoverintent/js/jquery.hoverIntent.minified.js"></script>

	<script type="text/javascript" src="libraries/bootstrap/js/bootstrap.min.js"></script>
	<script type="text/javascript" src="libraries/bootstrap/js/bootbox.min.js"></script>
	<script type="text/javascript" src="resources/jquery.additions.js"></script>
	<script type="text/javascript" src="resources/app.js"></script>
	<script type="text/javascript" src="resources/helper.js"></script>
	<script type="text/javascript" src="resources/Connector.js"></script>
	<script type="text/javascript" src="resources/ProgressIndicator.js" ></script>
	<script type="text/javascript" src="libraries/jquery/posabsolute-jQuery-Validation-Engine/js/jquery.validationEngine.js" ></script>
	<script type="text/javascript" src="layouts/vlayout/lib/guidersjs/guiders-1.2.6.js"></script>
	<script type="text/javascript" src="layouts/vlayout/lib/datepicker/js/datepicker.js"></script>
	<script type="text/javascript" src="layouts/vlayout/lib/daterangepicker/date.js"></script>
	<script type="text/javascript" src="libraries/jquery/jquery.ba-outside-events.min.js"></script>
	<script type="text/javascript" src="layouts/vlayout/lib/placeholder/js/jquery.placeholder.js"></script>

	{foreach key=index item=jsModel from=$SCRIPTS}
            <script type="{$jsModel->getType()}" src="{vresource_url($jsModel->getSrc())}"></script>
	{/foreach}

	<!-- Added in the end since it should be after less file loaded -->
	<script type="text/javascript" src="libraries/bootstrap/js/less.min.js"></script>
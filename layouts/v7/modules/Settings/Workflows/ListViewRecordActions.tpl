{*+**********************************************************************************
* The contents of this file are subject to the vtiger CRM Public License Version 1.1
* ("License"); You may not use this file except in compliance with the License
* The Original Code is: vtiger CRM Open Source
* The Initial Developer of the Original Code is vtiger.
* Portions created by vtiger are Copyright (C) vtiger.
* All Rights Reserved.
************************************************************************************}
{strip}
<!--LIST VIEW RECORD ACTIONS-->
<div style="width:80px ;">
    <a class="deleteRecordButton" style=" opacity: 0; padding: 0 5px;">
        <i title="{vtranslate('LBL_DELETE', $MODULE)}" class="fa fa-trash alignMiddle"></i>
    </a>
    <div class="form-check form-switch d-inline-block align-middle">
        <input {if $LISTVIEW_ENTRY->get('status')} checked value="on" {else} value="off"{/if} data-on-color="success" data-id="{$LISTVIEW_ENTRY->getId()}" type="checkbox" class="form-check-input" role="switch" name="workflowstatus" id="workflowstatus">
    </div>
</div>
{/strip}
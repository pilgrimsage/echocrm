<?php
/************************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * Contributor(s): Jonathan SARDO.
 * Portions created by Jonathan SARDO are Copyright (C).
 *************************************************************************************/

class Settings_ModuleDesigner_EditRelatedList_View extends Settings_Vtiger_Index_View
{

	function preProcess(Vtiger_Request $request, $display = true) {
		return;
	}

	function postProcess(Vtiger_Request $request) {
		return;
	}

	public function process(Vtiger_Request $request)
	{	
		$GLOBALS['csrf']['frame-breaker'] = false;
		
		$viewer = $this->getViewer ($request);
		$moduleName = $request->getModule();
		$qualifiedModuleName = $request->getModule(false);
		
		if((!$request->get("relatedlist") && !$request->get("relmodule")) || !$request->get("languages"))
		{
			//Make JSON response		
	        $response = new Vtiger_Response();	
	        $response->setError('error-param', getTranslatedString("LBL_ERROR_PARAM", $moduleName));
	        $response->emit();
		}
		else
		{	
			$languages = trim($request->get("languages"));
			$a_languages = explode(",", $languages);
					
			if($request->get("relatedlist"))
			{
				$a_relatedList =  $request->get("relatedlist");
			
				//Repair bug with utf8 characters
				if(!is_array($a_relatedList))
				{
					$a_relatedList = json_decode($a_relatedList, true);
					
				}
			}
			
			if(empty($a_relatedList))
			{
				$a_relatedList["relatedModule"]	= trim($request->get("relmodule"));
				$a_relatedList["label"]			= '';
				$a_relatedList["functionName"]	='';
				$a_relatedList["presence"]		= '';
				$a_relatedList["actionAdd"]		= '';
				$a_relatedList["actionSelect"]	= '';
			
				foreach($a_languages as $language)
				{
					$a_relatedList["label_".$language] = '';
				}
			}
			
			$viewer->assign('MODULE', $moduleName);
			$viewer->assign('QUALIFIED_MODULE', $qualifiedModuleName);
			$viewer->assign('a_relatedList', $a_relatedList);
			$viewer->assign('a_languages', $a_languages);
			
			echo $viewer->view('EditRelatedListPopup.tpl', $qualifiedModuleName,true);
		}
	}
}

?>
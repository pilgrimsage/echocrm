{*+**********************************************************************************
* The contents of this file are subject to the vtiger CRM Public License Version 1.1
* ("License"); You may not use this file except in compliance with the License
* The Original Code is: vtiger CRM Open Source
* The Initial Developer of the Original Code is vtiger.
* Portions created by vtiger are Copyright (C) vtiger.
* All Rights Reserved.
************************************************************************************}

{strip}
	{include file="modules/Vtiger/Header.tpl"}

	{include file="modules/Vtiger/partials/AppSidebar.tpl"}
	{assign var=APP_IMAGE_MAP value=Vtiger_MenuStructure_Model::getAppIcons()}
	{assign var=ALL_APP_MENUS value=Settings_MenuEditor_Module_Model::getAllVisibleModules()}
	{assign var=USER_PRIVILEGES_MODEL value=Users_Privileges_Model::getCurrentUserPrivilegesModel()}
	{assign var=CALENDAR_MODULE_MODEL value=Vtiger_Module_Model::getInstance('Calendar')}
	{assign var=HEADER_IMAGE_DETAILS value=$USER_MODEL->getImageDetails()}
	{assign var=HEADER_AVATAR_URL value=''}
	{foreach item=IMAGE_INFO from=$HEADER_IMAGE_DETAILS}
		{if !empty($IMAGE_INFO.url) && $HEADER_AVATAR_URL eq ''}{assign var=HEADER_AVATAR_URL value=$IMAGE_INFO.url}{/if}
	{/foreach}
	<nav class="navbar navbar-expand-lg navbar-inverse fixed-top app-fixed-navbar">
		<div class="container-fluid global-nav">
			<div class="header-inner">
				<div class="header-left">
					<button type="button" id="sidebarToggle" class="sidebar-toggle" title="{vtranslate('LBL_NAV_TOGGLE', 'Vtiger')}"><i class="fa fa-bars"></i></button>
					{if $COMPANY_LOGO->get('alt') eq 'vtiger-crm-logo.png'}
						{* nobody has uploaded a logo yet: show the product mark instead of vtiger's *}
						<a href="index.php" class="header-logo company-logo brand-lockup">
							<span class="header-logo-mark"><i class="fa fa-bolt"></i></span>
							<span class="header-logo-text">EchoCrew</span>
						</a>
					{else}
						<a href="index.php" class="header-logo company-logo">
							<img src="{$COMPANY_LOGO->get('imagepath')}" alt="{$COMPANY_LOGO->get('alt')}"/>
						</a>
					{/if}
					<div class="header-action dropdown apps-dropdown">
						<button type="button" class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="{vtranslate('LBL_QUICK_ACCESS', 'Vtiger')}"><i class="fa fa-th"></i></button>
						<div class="dropdown-menu apps-menu">
							<div class="apps-menu-header">
								<h6>{vtranslate('LBL_QUICK_ACCESS', 'Vtiger')}</h6>
								<span>{vtranslate('LBL_QUICK_ACCESS_SUB', 'Vtiger')}</span>
							</div>
							<div class="apps-grid">
								<a href="{$HOME_MODULE_MODEL->getDefaultUrl()}" class="apps-grid-item"><span class="apps-grid-icon"><i class="fa fa-home"></i></span><span>{vtranslate('LBL_DASHBOARD')}</span></a>
								{if $CALENDAR_MODULE_MODEL && $USER_PRIVILEGES_MODEL->hasModulePermission($CALENDAR_MODULE_MODEL->getId())}
									<a href="index.php?module=Calendar&view={$CALENDAR_MODULE_MODEL->getDefaultViewName()}" class="apps-grid-item"><span class="apps-grid-icon"><i class="fa fa-calendar"></i></span><span>{vtranslate('Calendar','Calendar')}</span></a>
								{/if}
								{assign var=QUICK_COUNT value=0}
								{assign var=APP_ORDER value=['MARKETING', 'SALES', 'INVENTORY', 'SUPPORT', 'PROJECT', 'TOOLS']}
								{foreach item=APP_NAME from=$APP_ORDER}
									{assign var=APP_MODULES value=[]}
									{if isset($ALL_APP_MENUS[$APP_NAME])}{assign var=APP_MODULES value=$ALL_APP_MENUS[$APP_NAME]}{/if}
									{foreach key=MODULE_NAME item=MODULE_MODEL from=$APP_MODULES}
										{if $QUICK_COUNT lt 10}
											<a href="{$MODULE_MODEL->getDefaultUrl()}&app={$APP_NAME}" class="apps-grid-item"><span class="apps-grid-icon">{$MODULE_MODEL->getModuleIcon()}</span><span>{vtranslate($MODULE_MODEL->get('label'), $MODULE_NAME)}</span></a>
											{assign var=QUICK_COUNT value=$QUICK_COUNT+1}
										{/if}
									{/foreach}
								{/foreach}
							</div>
						</div>
					</div>
				</div>

				<div class="header-search topbar-search-wrapper">
					<div id="search-links-container" class="search-links-container">
						<div class="search-link">
							<span class="fa fa-search" aria-hidden="true"></span>
							<input class="keyword-input" type="text" placeholder="{vtranslate('LBL_TYPE_SEARCH')}" value="{$GLOBAL_SEARCH_VALUE}" autocomplete="off">
							<span class="search-hint">&#8984;K</span>
							<span id="adv-search" class="adv-search fa fa-sliders cursorPointer" aria-hidden="true"></span>
						</div>
					</div>
				</div>

				<div id="navbar" class="header-right global-actions">
					<div class="dropdown create-dropdown">
						<button type="button" id="menubar_quickCreate" class="create-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="{vtranslate('LBL_QUICK_CREATE',$MODULE)}">
							<i class="fa fa-plus"></i><span>{vtranslate('LBL_CREATE')}</span>
						</button>
								<ul class="dropdown-menu dropdown-menu-end create-menu" role="menu" aria-labelledby="dropdownMenu1" >
									<li class="title" style="padding: 5px 0 0 15px;">
										<strong>{vtranslate('LBL_QUICK_CREATE',$MODULE)}</strong>
									</li>
									<hr/>
									<li id="quickCreateModules" style="padding: 0 5px;">
										<div class="col-lg-12" style="padding-bottom:15px;">
											<div class="row quickCreateItems">
											{foreach key=moduleName item=moduleModel from=$QUICK_CREATE_MODULES}
												{if $moduleModel->isPermitted('CreateView') || $moduleModel->isPermitted('EditView')}
													{assign var='quickCreateModule' value=$moduleModel->isQuickCreateSupported()}
													{assign var='singularLabel' value=$moduleModel->getSingularLabelKey()}
													{assign var=hideDiv value={!$moduleModel->isPermitted('CreateView') && $moduleModel->isPermitted('EditView')}}
													
													{if $quickCreateModule == '1' && !$hideDiv}
															{* Adding two links,Event and Task if module is Calendar *}
															{if $singularLabel == 'SINGLE_Calendar'}
																{assign var='singularLabel' value='LBL_TASK'}
																<div class="quickCreateItem {if $hideDiv}create_restricted_{$moduleModel->getName()} hide{else}col-lg-4 col-4{/if}">
																	<a id="menubar_quickCreate_Events" class="quickCreateModule" data-name="Events"
																	   data-url="index.php?module=Events&view=QuickCreateAjax" href="javascript:void(0)">{$moduleModel->getModuleIcon('Event')}<span class="quick-create-module">{vtranslate('LBL_EVENT',$moduleName)}</span></a>
																</div>
																<div class="quickCreateItem {if $hideDiv}create_restricted_{$moduleModel->getName()} hide{else}col-lg-4 col-4{/if}">
																	<a id="menubar_quickCreate_{$moduleModel->getName()}" class="quickCreateModule" data-name="{$moduleModel->getName()}"
																	   data-url="{$moduleModel->getQuickCreateUrl()}" href="javascript:void(0)">{$moduleModel->getModuleIcon('Task')}<span class="quick-create-module">{vtranslate($singularLabel,$moduleName)}</span></a>
																</div>
															{else if $singularLabel == 'SINGLE_Documents'}
																<div class="quickCreateItem {if $hideDiv}create_restricted_{$moduleModel->getName()} hide{else}col-lg-4 col-4{/if} dropdown">
																	<a id="menubar_quickCreate_{$moduleModel->getName()}" class="quickCreateModuleSubmenu dropdown-toggle" data-name="{$moduleModel->getName()}" data-bs-toggle="dropdown" 
																	   data-url="{$moduleModel->getQuickCreateUrl()}" href="javascript:void(0)">
																		{$moduleModel->getModuleIcon()}
																		<span class="quick-create-module">
																			{vtranslate($singularLabel,$moduleName)}
																			<i class="fa fa-caret-down quickcreateMoreDropdownAction"></i>
																		</span>
																	</a>
																	<ul class="dropdown-menu quickcreateMoreDropdown" aria-labelledby="menubar_quickCreate_{$moduleModel->getName()}">
																		<li class="dropdown-header"><i class="fa fa-upload"></i> {vtranslate('LBL_FILE_UPLOAD', $moduleName)}</li>
																		<li id="VtigerAction">
																			<a href="javascript:Documents_Index_Js.uploadTo('Vtiger')">
																				<img style="  margin-top: -3px;margin-right: 4%;" title="Vtiger" alt="Vtiger" src="layouts/v7/skins//images/Vtiger.png">
																				{vtranslate('LBL_TO_SERVICE', $moduleName, {vtranslate('LBL_VTIGER', $moduleName)})}
																			</a>
																		</li>
																		<li class="dropdown-header"><i class="fa fa-link"></i> {vtranslate('LBL_LINK_EXTERNAL_DOCUMENT', $moduleName)}</li>
																		<li id="shareDocument"><a href="javascript:Documents_Index_Js.createDocument('E')">&nbsp;<i class="fa fa-external-link"></i>&nbsp;&nbsp; {vtranslate('LBL_FROM_SERVICE', $moduleName, {vtranslate('LBL_FILE_URL', $moduleName)})}</a></li>
																		<li role="separator" class="divider"></li>
																		<li id="createDocument"><a href="javascript:Documents_Index_Js.createDocument('W')"><i class="fa fa-file-text"></i> {vtranslate('LBL_CREATE_NEW', $moduleName, {vtranslate('SINGLE_Documents', $moduleName)})}</a></li>
																	</ul>
																</div>
															{else}
																<div class="quickCreateItem {if $hideDiv}create_restricted_{$moduleModel->getName()} hide{else}col-lg-4 col-4{/if}">
																	<a id="menubar_quickCreate_{$moduleModel->getName()}" class="quickCreateModule" data-name="{$moduleModel->getName()}"
																	   data-url="{$moduleModel->getQuickCreateUrl()}" href="javascript:void(0)">
																		{$moduleModel->getModuleIcon()}
																		<span class="quick-create-module">{vtranslate($singularLabel,$moduleName)}</span>
																	</a>
																</div>
															{/if}
													{/if}
												{/if}
											{/foreach}
											</div>
										</div>
									</li>
								</ul>
					</div>
					<span class="header-divider"></span>
					<a href="javascript:void(0)" id="themeToggle" class="header-action theme-toggle" title="{vtranslate('LBL_THEME_TOGGLE', 'Vtiger')}" aria-pressed="false"><i class="fa fa-moon-o"></i></a>
					<button type="button" id="fullscreenToggle" class="header-action fullscreen-toggle" title="{vtranslate('LBL_FULLSCREEN', 'Vtiger')}"><i class="fa fa-arrows-alt"></i></button>
					{if $CALENDAR_MODULE_MODEL && $USER_PRIVILEGES_MODEL->hasModulePermission($CALENDAR_MODULE_MODEL->getId())}
						<a href="#" class="header-action taskManagement" title="{vtranslate('Tasks','Vtiger')}"><i class="vicon vicon-task"></i></a>
					{/if}
					<div class="dropdown user-dropdown">
						<a href="#" class="userName user-chip dropdown-toggle" data-bs-toggle="dropdown" role="button" title="{$USER_MODEL->get('userlabel')} ({$USER_MODEL->get('user_name')})">
							<span class="user-chip-avatar">{if $HEADER_AVATAR_URL neq ''}<img src="{$HEADER_AVATAR_URL}" alt="">{else}{$USER_MODEL->get('first_name')|truncate:1:''}{$USER_MODEL->get('last_name')|truncate:1:''}{/if}</span>
							<span class="user-chip-name">{$USER_MODEL->get('first_name')}</span>
							<i class="fa fa-angle-down user-chip-caret"></i>
						</a>
								<div class="dropdown-menu dropdown-menu-end logout-content" role="menu">
									<div class="row">
										<div class="col-lg-4 col-sm-4">
											<div class="profile-img-container">
												{assign var=IMAGE_DETAILS value=$USER_MODEL->getImageDetails()}
												{if $IMAGE_DETAILS neq '' && $IMAGE_DETAILS[0] neq '' && $IMAGE_DETAILS[0].path eq ''}
													<i class='vicon-vtigeruser' style="font-size:90px"></i>
												{else}
													{foreach item=IMAGE_INFO from=$IMAGE_DETAILS}
														{if !empty($IMAGE_INFO.url)}
															<img src="{$IMAGE_INFO.url}" width="100px" height="100px">
														{/if}
													{/foreach}
												{/if}
											</div>
										</div>
										<div class="col-lg-8 col-sm-8">
											<div class="profile-container">
												<h4>{$USER_MODEL->get('first_name')} {$USER_MODEL->get('last_name')}</h4>
												<h5 class="textOverflowEllipsis" title='{$USER_MODEL->get('user_name')}'>{$USER_MODEL->get('user_name')}</h5>
												<p>{$USER_MODEL->getUserRoleName()}</p>
											</div>
										</div>
									</div>
									<div id="logout-footer" class="logout-footer clearfix">
										<hr style="margin: 10px 0 !important">
										<div class="">
											<span class="float-start">
												<span class="fa fa-cogs"></span>
												<a id="menubar_item_right_LBL_MY_PREFERENCES" href="{$USER_MODEL->getPreferenceDetailViewUrl()}">{vtranslate('LBL_MY_PREFERENCES')}</a>
											</span>
											<span class="float-end">
												<span class="fa fa-power-off"></span>
												<a id="menubar_item_right_LBL_SIGN_OUT" href="index.php?module=Users&action=Logout">{vtranslate('LBL_SIGN_OUT')}</a>
											</span>
										</div>
									</div>
								</div>
					</div>
				</div>
			</div>
		</div>
{/strip}

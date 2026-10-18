{*+**********************************************************************************
* Application menu: the dark sidebar of the TrendyAdmin layout.
* Overview links (Home, Calendar, Reports), one collapsible group per app (Marketing,
* Sales, Inventory, Support, Project, Tools) listing its modules, the Settings link for
* administrators and the signed-in user at the bottom.
* Styles: layouts/v7/lib/modern/css/appshell.css, behaviour: Vtiger/resources/AppSidebar.js
************************************************************************************}
{strip}
	{assign var=APP_IMAGE_MAP value=Vtiger_MenuStructure_Model::getAppIcons()}
	{assign var=ALL_APP_MENUS value=Settings_MenuEditor_Module_Model::getAllVisibleModules()}
	{assign var=USER_PRIVILEGES_MODEL value=Users_Privileges_Model::getCurrentUserPrivilegesModel()}
	{assign var=HOME_MODULE_MODEL value=Vtiger_Module_Model::getInstance('Home')}
	{assign var=CALENDAR_MODULE_MODEL value=Vtiger_Module_Model::getInstance('Calendar')}
	{assign var=REPORTS_MODULE_MODEL value=Vtiger_Module_Model::getInstance('Reports')}
	<aside class="app-sidebar" id="appSidebar">
		<div class="sidebar-header">
			<a href="index.php" class="sidebar-logo">
				<span class="sidebar-logo-mark"><i class="fa fa-bolt"></i></span>
				<span class="sidebar-logo-text">EchoCrew</span>
			</a>
			<button type="button" class="sidebar-close" id="sidebarClose" title="{vtranslate('LBL_NAV_CLOSE', 'Vtiger')}"><i class="fa fa-times"></i></button>
		</div>
		<nav class="sidebar-nav">
			<ul class="nav-menu">
				<li class="nav-heading"><span>{vtranslate('LBL_NAV_OVERVIEW', 'Vtiger')}</span></li>
				<li class="nav-item">
					<a class="nav-link {if $MODULE eq 'Home' || !$MODULE}active{/if}" href="{$HOME_MODULE_MODEL->getDefaultUrl()}" title="{vtranslate('LBL_DASHBOARD')}">
						<span class="nav-icon"><i class="fa fa-home"></i></span>
						<span class="nav-text">{vtranslate('LBL_DASHBOARD')}</span>
					</a>
				</li>
				{if $CALENDAR_MODULE_MODEL && $USER_PRIVILEGES_MODEL->hasModulePermission($CALENDAR_MODULE_MODEL->getId())}
					<li class="nav-item">
						<a class="nav-link {if $MODULE eq 'Calendar'}active{/if}" href="index.php?module=Calendar&view={$CALENDAR_MODULE_MODEL->getDefaultViewName()}" title="{vtranslate('Calendar','Calendar')}">
							<span class="nav-icon"><i class="fa fa-calendar"></i></span>
							<span class="nav-text">{vtranslate('Calendar','Calendar')}</span>
						</a>
					</li>
				{/if}
				{if $REPORTS_MODULE_MODEL && $USER_PRIVILEGES_MODEL->hasModulePermission($REPORTS_MODULE_MODEL->getId())}
					<li class="nav-item">
						<a class="nav-link {if $MODULE eq 'Reports'}active{/if}" href="index.php?module=Reports&view=List" title="{vtranslate('Reports','Reports')}">
							<span class="nav-icon"><i class="fa fa-bar-chart"></i></span>
							<span class="nav-text">{vtranslate('Reports','Reports')}</span>
						</a>
					</li>
				{/if}

				<li class="nav-heading"><span>{vtranslate('LBL_NAV_APPS', 'Vtiger')}</span></li>
				{assign var=APP_ORDER value=['MARKETING', 'SALES', 'INVENTORY', 'SUPPORT', 'PROJECT', 'TOOLS']}
				{foreach item=APP_NAME from=$APP_ORDER}
					{assign var=APP_MODULES value=[]}
					{if isset($ALL_APP_MENUS[$APP_NAME])}{assign var=APP_MODULES value=$ALL_APP_MENUS[$APP_NAME]}{/if}
					{if empty($APP_MODULES)}{continue}{/if}
					{assign var=APP_IS_CURRENT value=($APP_NAME eq $SELECTED_MENU_CATEGORY && isset($APP_MODULES[$MODULE]))}
					<li class="nav-item has-submenu {if $APP_IS_CURRENT}open{/if}">
						<a class="nav-link {if $APP_IS_CURRENT}current-app{/if}" href="#" aria-expanded="{if $APP_IS_CURRENT}true{else}false{/if}" title="{vtranslate("LBL_$APP_NAME")}">
							<span class="nav-icon"><i class="fa {if $APP_NAME eq 'INVENTORY'}fa-cubes{elseif isset($APP_IMAGE_MAP[$APP_NAME])}{$APP_IMAGE_MAP[$APP_NAME]}{else}fa-th-large{/if}"></i></span>
							<span class="nav-text">{vtranslate("LBL_$APP_NAME")}</span>
							<i class="fa fa-chevron-right nav-arrow"></i>
						</a>
						<ul class="nav-submenu">
							{foreach key=MODULE_NAME item=MODULE_MODEL from=$APP_MODULES}
								<li>
									<a class="nav-link {if $APP_IS_CURRENT && $MODULE eq $MODULE_NAME}active{/if}" href="{$MODULE_MODEL->getDefaultUrl()}&app={$APP_NAME}">{vtranslate($MODULE_MODEL->get('label'), $MODULE_NAME)}</a>
								</li>
							{/foreach}
						</ul>
					</li>
				{/foreach}

				{if $USER_MODEL->isAdminUser()}
					<li class="nav-heading"><span>{vtranslate('LBL_NAV_ADMIN', 'Vtiger')}</span></li>
					<li class="nav-item">
						<a class="nav-link {if $PARENT_MODULE eq 'Settings'}active{/if}" href="index.php?module=Vtiger&parent=Settings&view=Index" title="{vtranslate('LBL_CRM_SETTINGS','Vtiger')}">
							<span class="nav-icon"><i class="fa fa-cog"></i></span>
							<span class="nav-text">{vtranslate('LBL_CRM_SETTINGS','Vtiger')}</span>
						</a>
					</li>
				{/if}
			</ul>
		</nav>
		<div class="sidebar-footer">
			<div class="sidebar-user">
				<a class="sidebar-user-link" href="{$USER_MODEL->getPreferenceDetailViewUrl()}" title="{vtranslate('LBL_MY_PREFERENCES')}">
					<span class="sidebar-user-avatar">
						{assign var=SIDEBAR_IMAGE_DETAILS value=$USER_MODEL->getImageDetails()}
						{assign var=SIDEBAR_AVATAR_URL value=''}
						{foreach item=IMAGE_INFO from=$SIDEBAR_IMAGE_DETAILS}
							{if !empty($IMAGE_INFO.url) && $SIDEBAR_AVATAR_URL eq ''}{assign var=SIDEBAR_AVATAR_URL value=$IMAGE_INFO.url}{/if}
						{/foreach}
						{if $SIDEBAR_AVATAR_URL neq ''}
							<img src="{$SIDEBAR_AVATAR_URL}" alt="">
						{else}
							<span class="sidebar-user-initials">{$USER_MODEL->get('first_name')|truncate:1:''}{$USER_MODEL->get('last_name')|truncate:1:''}</span>
						{/if}
						<span class="sidebar-user-status"></span>
					</span>
					<span class="sidebar-user-info">
						<span class="sidebar-user-name">{$USER_MODEL->getName()}</span>
						<span class="sidebar-user-role">{$USER_MODEL->getUserRoleName()}</span>
					</span>
				</a>
				<span class="sidebar-user-actions">
					<a class="sidebar-user-action" href="{$USER_MODEL->getPreferenceDetailViewUrl()}" title="{vtranslate('LBL_MY_PREFERENCES')}"><i class="fa fa-cog"></i></a>
					<a class="sidebar-user-action sidebar-user-logout" href="index.php?module=Users&action=Logout" title="{vtranslate('LBL_SIGN_OUT')}"><i class="fa fa-power-off"></i></a>
				</span>
			</div>
		</div>
	</aside>
	<div class="sidebar-overlay" id="sidebarOverlay"></div>
{/strip}

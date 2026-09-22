{*+**********************************************************************************
* The contents of this file are subject to the vtiger CRM Public License Version 1.1
* ("License"); You may not use this file except in compliance with the License
* The Original Code is: vtiger CRM Open Source
* The Initial Developer of the Original Code is vtiger.
* Portions created by vtiger are Copyright (C) vtiger.
* All Rights Reserved.
************************************************************************************}
{* modules/Users/views/Login.php *}

{strip}
	<link type='text/css' rel='stylesheet' href='{vresource_url("layouts/v7/lib/modern/css/login.css")}'>

	<span class="app-nav"></span>

	<div class="split">

		<div class="brand">
			<div class="brand-mark"><span class="dot"></span> ECHOCREW</div>
			<div class="brand-copy">
				<h1>Every deal, moving forward.</h1>
				<p>Track pipeline, close faster, and keep your whole team looking at the same truth.</p>
			</div>
			<div class="brand-foot">
				{if $JSON_DATA}
					{assign var=ALL_BLOCKS_COUNT value=0}
					{foreach key=BLOCK_NAME item=BLOCKS_DATA from=$JSON_DATA}
						{if $BLOCKS_DATA}
							<div class="mkt-block">
								<h4>{$BLOCKS_DATA[0].heading}</h4>
								<div class="mkt-slides">
									{foreach item=BLOCK_DATA from=$BLOCKS_DATA name=slideLoop}
										{assign var=ALL_BLOCKS_COUNT value=$ALL_BLOCKS_COUNT+1}
										<div class="mkt-slide{if $smarty.foreach.slideLoop.first} active{/if}" title="{$BLOCK_DATA.summary}">
											{if $BLOCK_DATA.image}<img src="{$BLOCK_DATA.image}">{/if}
											<h3>{$BLOCK_DATA.displayTitle}</h3>
											<a href="{$BLOCK_DATA.url}" target="_blank">{$BLOCK_DATA.urlalt}</a>
										</div>
									{/foreach}
								</div>
							</div>
						{/if}
					{/foreach}
				{/if}
				<div style="margin-top:16px;">&copy; {date('Y')} EchoCrew</div>
			</div>
		</div>

		<div class="formside">
			<div class="formcard">

				<div id="loginFormDiv">
					<h2>Sign in</h2>
					<div class="sub">Welcome back &mdash; enter your details to continue.</div>

					<span class="{if !$ERROR}hide{/if} err" id="validationMessage">{$MESSAGE}</span>
					<span class="{if !$MAIL_STATUS}hide{/if} ok">{$MESSAGE}</span>

					<form class="form-horizontal" method="POST" action="index.php">
						<input type="hidden" name="module" value="Users"/>
						<input type="hidden" name="action" value="Login"/>

						<div class="field">
							<label for="username">Username</label>
							<input id="username" type="text" name="username" placeholder="your.name">
						</div>
						<div class="field">
							<label for="password">Password</label>
							<input id="password" type="password" name="password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
						</div>

						{assign var="CUSTOM_SKINS" value=Vtiger_Theme::getAllSkins()}
						{if !empty($CUSTOM_SKINS)}
						<div class="field">
							<label for="skin">Skin</label>
							<select id="skin" name="skin" class="skin-select">
								<option value="">Default Skin</option>
								{foreach item=CUSTOM_SKIN from=$CUSTOM_SKINS}
								<option value="{$CUSTOM_SKIN}">{$CUSTOM_SKIN}</option>
								{/foreach}
							</select>
						</div>
						{/if}

						<div class="row-between">
							<span></span>
							<a href="#" class="forgotPasswordLink forgot-link">Forgot password?</a>
						</div>

						<button type="submit" class="btn-signin">Sign in</button>
					</form>
					<div class="foot-note">Trouble signing in? Contact your administrator.</div>
				</div>

				<div id="forgotPasswordDiv" class="hide">
					<h2>Reset password</h2>
					<div class="sub">We&rsquo;ll email you a reset link.</div>

					<form class="form-horizontal" action="forgotPassword.php" method="POST">
						<div class="field">
							<label for="fusername">Username</label>
							<input id="fusername" type="text" name="username" placeholder="your.name">
						</div>
						<div class="field">
							<label for="email">Email</label>
							<input id="email" type="email" name="emailId" placeholder="you@company.com">
						</div>
						<button type="submit" class="btn-signin forgot-submit-btn">Send reset link</button>
					</form>
					<div class="foot-note"><a href="#" class="forgotPasswordLink forgot-link">Back to sign in</a></div>
				</div>

			</div>
		</div>

	</div>

	<script>
		jQuery(document).ready(function () {
			var validationMessage = jQuery('#validationMessage');
			var forgotPasswordDiv = jQuery('#forgotPasswordDiv');
			var loginFormDiv = jQuery('#loginFormDiv');

			loginFormDiv.find('#username').focus();

			jQuery('.forgotPasswordLink').on('click', function (e) {
				e.preventDefault();
				loginFormDiv.toggleClass('hide');
				forgotPasswordDiv.toggleClass('hide');
				validationMessage.addClass('hide');
			});

			loginFormDiv.find('button').on('click', function () {
				var username = loginFormDiv.find('#username').val();
				var password = jQuery('#password').val();
				var result = true;
				var errorMessage = '';
				if (username === '') {
					errorMessage = 'Please enter valid username';
					result = false;
				} else if (password === '') {
					errorMessage = 'Please enter valid password';
					result = false;
				}
				if (errorMessage) {
					validationMessage.removeClass('hide').text(errorMessage);
				}
				return result;
			});

			forgotPasswordDiv.find('button').on('click', function () {
				var username = jQuery('#forgotPasswordDiv #fusername').val();
				var email = jQuery('#email').val();
				var email1 = email.replace(/^\s+/, '').replace(/\s+$/, '');
				var emailFilter = /^[^@]+@[^@.]+\.[^@]*\w\w$/;
				var illegalChars = /[\(\)\<\>\,\;\:\\\"\[\]]/;

				var result = true;
				var errorMessage = '';
				if (username === '') {
					errorMessage = 'Please enter valid username';
					result = false;
				} else if (!emailFilter.test(email1) || email == '') {
					errorMessage = 'Please enter valid email address';
					result = false;
				} else if (email.match(illegalChars)) {
					errorMessage = 'The email address contains illegal characters.';
					result = false;
				}
				if (errorMessage) {
					validationMessage.removeClass('hide').text(errorMessage);
				}
				return result;
			});

			/* lightweight marketing slide rotation, replaces bxSlider */
			jQuery('.mkt-slides').each(function () {
				var slides = jQuery(this).find('.mkt-slide');
				if (slides.length < 2) return;
				var idx = 0;
				setInterval(function () {
					slides.eq(idx).removeClass('active');
					idx = (idx + 1) % slides.length;
					slides.eq(idx).addClass('active');
				}, 4000);
			});
		});
	</script>
{/strip}

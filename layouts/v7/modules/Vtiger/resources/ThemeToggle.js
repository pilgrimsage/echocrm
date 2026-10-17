/*
 * Light / dark switch in the top bar. The choice is kept in this browser (localStorage, key
 * "echocrm-theme"); Header.tpl applies it before the page paints so there is no flash.
 */
(function () {
	var KEY = 'echocrm-theme';

	function current() {
		return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
	}

	function paint(theme) {
		if (theme === 'dark') {
			document.documentElement.setAttribute('data-theme', 'dark');
		} else {
			document.documentElement.removeAttribute('data-theme');
		}
		var icon = document.querySelector('#themeToggle i');
		if (icon) {
			icon.className = theme === 'dark' ? 'fa fa-sun-o' : 'fa fa-moon-o';
		}
		var button = document.getElementById('themeToggle');
		if (button) {
			button.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		paint(current());
		var button = document.getElementById('themeToggle');
		if (!button) {
			return;
		}
		button.addEventListener('click', function (event) {
			event.preventDefault();
			var next = current() === 'dark' ? 'light' : 'dark';
			paint(next);
			try { localStorage.setItem(KEY, next); } catch (e) { /* private window: the choice just lasts for this page */ }
		});
	});
})();

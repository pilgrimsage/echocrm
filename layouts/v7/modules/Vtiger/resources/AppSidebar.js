/*
 * Application sidebar (partials/AppSidebar.tpl). State is kept on <html>:
 *   sidebar-collapsed  desktop, icons only; remembered in localStorage ("echocrm-sidebar"),
 *                      Header.tpl applies it before the first paint
 *   sidebar-open       below 1200px, the sidebar slides over the page
 * The header button (#sidebarToggle) collapses the sidebar on desktop and opens it on small screens.
 */
(function () {
	var KEY = 'echocrm-sidebar';
	var root = document.documentElement;

	function isDesktop() {
		return window.matchMedia('(min-width: 1200px)').matches;
	}

	function remember(collapsed) {
		try {
			localStorage.setItem(KEY, collapsed ? 'collapsed' : 'expanded');
		} catch (e) {}
	}

	function closeOverlay() {
		root.classList.remove('sidebar-open');
	}

	document.addEventListener('click', function (event) {
		var target = event.target;

		if (target.closest('#sidebarToggle')) {
			if (isDesktop()) {
				var collapsed = root.classList.toggle('sidebar-collapsed');
				remember(collapsed);
				// list and calendar layouts measure their container
				window.dispatchEvent(new Event('resize'));
			} else {
				root.classList.toggle('sidebar-open');
			}
			return;
		}
		if (target.closest('#sidebarClose') || target.closest('#sidebarOverlay')) {
			closeOverlay();
			return;
		}

		if (target.closest('#fullscreenToggle')) {
			if (document.fullscreenElement) {
				document.exitFullscreen();
			} else if (root.requestFullscreen) {
				root.requestFullscreen();
			}
			return;
		}

		var parent = target.closest('#appSidebar .has-submenu > .nav-link');
		if (parent) {
			event.preventDefault();
			var item = parent.parentElement;
			var willOpen = !item.classList.contains('open');
			// one app open at a time, like an accordion
			if (item.parentElement && !item.classList.contains('nav-submenu')) {
				item.parentElement.querySelectorAll(':scope > .has-submenu.open').forEach(function (other) {
					other.classList.remove('open');
					other.querySelector(':scope > .nav-link').setAttribute('aria-expanded', 'false');
				});
			}
			item.classList.toggle('open', willOpen);
			parent.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
			return;
		}

		if (target.closest('#appSidebar a.nav-link[href]') && !isDesktop()) {
			closeOverlay();
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			closeOverlay();
		}
		// Ctrl/Cmd + K jumps to the global search
		if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
			var input = document.querySelector('.global-nav .keyword-input');
			if (input) {
				event.preventDefault();
				input.focus();
				input.select();
			}
		}
	});

	document.addEventListener('fullscreenchange', function () {
		var icon = document.querySelector('#fullscreenToggle i');
		if (icon) {
			icon.className = document.fullscreenElement ? 'fa fa-compress' : 'fa fa-arrows-alt';
		}
	});

	window.addEventListener('resize', function () {
		if (isDesktop()) {
			closeOverlay();
		}
	});

	// bring the current module into view in a long menu
	document.addEventListener('DOMContentLoaded', function () {
		var active = document.querySelector('#appSidebar .nav-link.active');
		if (active && active.scrollIntoView) {
			active.scrollIntoView({block: 'nearest'});
		}
	});
})();

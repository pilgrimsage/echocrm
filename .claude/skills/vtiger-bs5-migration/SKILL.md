---
name: vtiger-bs5-migration
description: Reference for the vtiger CRM 8.4 Bootstrap 3→5 / jQuery 2→3 modernization project. Use whenever working on this vtiger codebase's UI, CSS, JS plugin, or Bootstrap-related code — class/attribute renames, jQuery-plugin-to-vanilla-JS conversions, plugin tier decisions, or debugging "looks broken after a change" issues. Also use when the user asks what's been done, what's deferred, or why a past decision was made on this project.
---

# Vtiger BS3→BS5 Migration Reference

Full log of the modernization work on this vtiger 8.4 install: what changed, why, and what's still pending. Read this before touching Bootstrap/jQuery-related code in this repo so you don't redo work or reintroduce a fixed bug.

**⚠️ Trust git history over this log, always.** On 2026-09-22 this log claimed the class-rename sweep and JS API conversion were both complete app-wide. They weren't — git history had only 4 commits total, touching a narrow slice of files (mostly `Vtiger/` core + `helper.js`/`application.js`/`app.js`), while live grep found ~230 template/JS files across nearly every module still on raw Bootstrap 3 markup, including `helper.js`/`Tag.js`/`Utils.js` themselves — the exact files this log said were "most central, already converted." The work described below as done was re-verified against the actual repo state on 2026-09-22 and is now accurate as of that date. If you're reading this later and something looks unswept, believe the grep, not the log — run `git log --oneline -- <file>` and a fresh grep before assuming a past session's claims here are still true.

## Class/attribute renames — swept app-wide across `layouts/v7/modules` + `layouts/v7/resources` (verified 2026-09-22), don't redo

| Old (BS3) | New (BS5) | Notes |
|---|---|---|
| `data-toggle="..."` | `data-bs-toggle="..."` | |
| `data-target="..."` | `data-bs-target="..."` | **Not a blind rename** — `data-target` is also used as a plain custom data attribute in 4 files unrelated to Bootstrap (`PurchaseOrder/uitypes/Text.tpl`, `Contacts/uitypes/Text.tpl`, `Accounts/uitypes/Text.tpl`, `Inventory/partials/EditViewContents.tpl` — all `copyAddress`/address-type selectors read via `.data('target')` in the matching `Edit.js` files). Those were deliberately left as bare `data-target=`. Before renaming any `data-target`, check it's actually paired with `data-toggle="collapse"`/`"modal"` on the same element, not a custom attribute. |
| `data-dismiss="..."` | `data-bs-dismiss="..."` | Missed in the original pass — 68 occurrences found later, all real (modal/alert close buttons) |
| `data-parent="..."` | `data-bs-parent="..."` | Accordion/collapse parent — 5 occurrences, all real |
| `data-ride="..."` | `data-bs-ride="..."` | Carousel autoplay |
| `data-slide="..."` / `data-slide-to="..."` | `data-bs-slide="..."` / `data-bs-slide-to="..."` | Carousel controls — only in `Settings/ExtensionStore/Detail.tpl` |
| `navbar-fixed-top` | `fixed-top` | |
| `hidden-xs`, `hidden-sm`, etc. | `d-none d-md-block` (adjust breakpoint) | BS4+ dropped `hidden-*`/`visible-*`. `hidden-xs hidden-sm` (either word order) → `d-none d-md-block`; a lone `hidden-xs` (3 spots) → `d-none d-sm-block` |
| `pull-left` / `pull-right` | `float-start` / `float-end` | |
| `navbar-toggle` | `navbar-toggler` | Needed for BS5's auto-hide-at-desktop behavior. **Gotcha:** a naive `sed 's/navbar-toggle"/navbar-toggler"/'` only catches it as the *last* class before the closing quote — 2 files had `class="navbar-toggle collapsed ..."` with trailing classes and were missed until a follow-up grep for `navbar-toggle[^r]` caught them |
| `.navbar-right` | `margin-left: auto` (custom CSS) | BS5 has no equivalent class — **no markup changes needed**, just add `.navbar-right { margin-left: auto; }` to `nav.css`; the class stays in the markup as a pure CSS hook |

**JS-side attribute reads must be updated in lockstep with the markup rename**, or they silently break: `.data('toggle')` (jQuery camelCases `data-bs-toggle` to `bsToggle`, not `toggle`) and `[data-toggle]` selectors stop matching once markup says `data-bs-toggle`. Found and fixed in `Vtiger/ListSidebar.js`, `Vtiger/AdvanceSearchList.js`, `Vtiger/SearchList.js`, `Vtiger/List.js`, `Reports/List.js`, `Reports/ChartDetail.js`.

If you find an un-swept instance, it's a real bug. Do a full-tree grep before assuming it's already handled — don't trust a prior sweep's claimed file count.

## Critical structural fixes (not obvious, easy to miss)

**`navbar-expand-lg` is required on `<nav class="app-fixed-navbar">`.** Without any `.navbar-expand-*` class, BS5 keeps `.navbar-collapse` content (search box, quick-create, user menu — basically the whole right side of the top bar) hidden at ALL widths. BS3 auto-showed this above a breakpoint via CSS baked into `.navbar-collapse` itself; BS5 requires explicit opt-in via `.navbar-expand-{breakpoint}`.

**Side effect:** `navbar-expand-lg` also sets `flex-wrap: nowrap` on the nav. This vtiger markup stacks TWO separate `.container-fluid` blocks inside one `<nav>` (top user bar + breadcrumb bar below it) — `flex-wrap:nowrap` forces them onto one line, splitting the nav's width ~50/50 between them instead of stacking. Fix (applied in `layouts/v7/lib/modern/css/nav.css`):
```css
.app-fixed-navbar.navbar-expand-lg { flex-wrap: wrap !important; }
```

**`.navbar-right` and `float-end` on flex children:** `.nav` is `display:flex` in BS5, and floats have no effect on flex items. `.navbar-right` (BS3 float helper) also doesn't exist in BS5. Fix (applied in `nav.css`, no markup changes): `.navbar-right { margin-left: auto; }` — covers all 12 occurrences (`ModuleHeader.tpl`/`CalendarHeader.tpl`/`ListViewHeader.tpl`/`Topbar.tpl` across modules).

**`.caret` markup duplicates BS5's own arrow.** BS5's `.dropdown-toggle` auto-draws an arrow via CSS `::after`. Old markup still has literal `<span class="caret">`/`<i class="caret">` in 31 files — don't try to restyle it, just hide it (applied in `nav.css`): `.caret { display: none; }`.

**Popper is required for dropdowns/tooltips/popovers to position correctly.** Load `bootstrap.bundle.js` (includes Popper) or load Popper separately before plain `bootstrap.js`.

## jQuery 3 regressions found and fixed

- `.size()` → `.length` (`.size()` removed in jQuery 3)
- `.live()` → `.on()` (delegated event binding; `.live()` fully removed)
- `$.browser` fully removed. Real code (`PrintReport.tpl`) and vendor code (jqplot) both depended on it. Fix: a global shim right after jQuery loads — `jQuery.browser = jQuery.browser || {};` — stops crashes without restoring real detection (fine, since no modern browser is IE anyway).
- `jQuery(window).load(fn)` is ambiguous with jQuery's AJAX `.load()` method in jQuery 3 and doesn't reliably bind as a window-load event. Fix: `jQuery(window).on('load', fn)`.
- jstorage plugin deletion broke internal wrapper functions in `application.js` (`storage.get/set/delete/flush`) and root `resources/app.js` (`cacheGet/cacheSet/cacheClear`) — both rewritten to call native `localStorage` directly. **Lesson: check for internal wrapper dependents before deleting any plugin, not just external template/module usage.**

## BS5 JS API — jQuery-plugin calls converted to vanilla JS classes

BS5 removed the jQuery-plugin style entirely (`$(el).modal('show')` etc no longer works out of the box). **Explicit project decision: no compatibility shim — direct call-site rewrites in every file.** A shim (monkey-patching `jQuery.fn.modal` etc to delegate to BS5 classes) was proposed and rejected.

Conversion patterns used throughout:

| Old call | New call |
|---|---|
| `X.modal(options)` | `bootstrap.Modal.getOrCreateInstance(X[0], options).show();` |
| `X.modal('hide')` | `var _m = bootstrap.Modal.getInstance(X[0]); if(_m) _m.hide();` |
| `X.tooltip(options)` | `bootstrap.Tooltip.getOrCreateInstance(X[0], options);` |
| `X.popover(options)` | `bootstrap.Popover.getOrCreateInstance(X[0], options);` |
| `X.popover('show'/'hide'/'toggle')` | `var _p = bootstrap.Popover.getInstance(X[0]); if(_p) _p.show()/.hide()/.toggle();` |
| `X.popover('destroy')` | `var _p = bootstrap.Popover.getInstance(X[0]); if(_p) _p.dispose();` |
| `X.dropdown('toggle')` | `bootstrap.Dropdown.getOrCreateInstance(X[0]).toggle();` |
| `X.collapse('show'/'hide')` | `bootstrap.Collapse.getOrCreateInstance(X[0]).show();` or via `getInstance().hide()` |

All 20 affected app-code files (vendor/lib excluded from scope) have been converted, verified 2026-09-22: `helper.js` (most central — 10 modal calls), `Emails/MassEdit.js`, `Emails/EmailPreview.js`, `Settings/Workflows/Edit.js`, `Vtiger/Utils.js`, `Settings/CustomerPortal/CustomerPortal.js`, `Vtiger/ListSidebar.js`, `Vtiger/Detail.js`, `Vtiger/List.js`, `Vtiger/Tag.js`, `Calendar/TaskManagement.js`, `MailManager/List.js`, `Settings/SMSNotifier/List.js`, `Settings/ExtensionStore/ExtensionStore.js`, `Products/Detail.js`, `Inventory/Detail.js`, `Inventory/Edit.js` (25 calls, same handful of patterns repeated — 4 more are inside a comment, `// chargesTrigger.popover('hide');` etc, deliberately left alone), `Leads/Detail.js`, `Potentials/Detail.js`. `Vtiger/validation.js` had a `.popover()` block that turned out to be dead/commented code — skipped, don't "fix" commented-out code.

Extra nuances hit during conversion, worth knowing before doing more of these:
- **Multi-element selectors need `.each()`**, not `X[0]`: class/attribute selectors like `jQuery('[rel="tooltip"]')` or `jQuery('.totalCostCalculationInfo')` can match more than one node — `getOrCreateInstance` takes exactly one element. Single-element cases (an ID selector, or a variable already scoped to one node via a click handler's `e.currentTarget`) are safe to use `X[0]` directly.
- **`.data('bs.popover').tip()` has no BS5 equivalent.** Old code used this to grab the generated tip element synchronously right after init. BS5 doesn't expose `.tip()`; instead, hook `shown.bs.popover` and read `element.attr('aria-describedby')` to find the actual tip element in the DOM (`Products/Detail.js`).
- **`.removeData("modal").modal(newParams)` (force re-init with fresh options)** becomes: `getInstance(...)?.dispose()` first, then `getOrCreateInstance(el, newParams).show()` — `getOrCreateInstance` reuses an existing instance's original config if one exists, so you must dispose before re-creating with different params (`helper.js` `showModal`/`hideModal`, `Emails/MassEdit.js`).
- **The old sanitizer whitelist API renamed**: `X.popover.Constructor.DEFAULTS.whiteList` (BS3/4, per-instance) → `bootstrap.Popover.Default.allowList` (BS5, static on the class, and renamed `whiteList`→`allowList`) (`MailManager/List.js`).
- Guard `getOrCreateInstance`/`getInstance` calls with a null/existence check wherever the original jQuery selector could legitimately match nothing (e.g. `#phoneFormatWarningPop` only exists on modules with phone fields) — jQuery silently no-ops on an empty selection, but `bootstrap.Popover.getOrCreateInstance(undefined)` throws.

If you find more `.modal(`/`.tooltip(`/`.popover(`/`.dropdown(`/`.collapse(` calls in app code (not vendor `/lib/` folders), convert them the same way.

## Plugin tier audit (don't re-litigate without new evidence)

**Tier 1 — verified dead and deleted 2026-09-22** (previously claimed done, but the files were still present with zero references — `git grep` for each name found nothing): `html5shim` (`libraries/html5shim/`), `jstorage.min.js` (both `libraries/jquery/` and `layouts/v7/lib/jquery/` copies). `handsontable` was already genuinely gone.

**⚠️ NOT actually dead — still load-bearing, do not delete:**
- `colorpicker` (`libraries/jquery/colorpicker/`) — actively registered by `modules/Settings/Picklist/views/Index.php`, `modules/Calendar/views/Calendar.php`, and `modules/Project/views/Detail.php` (picklist color swatches, calendar/project color fields). Replacing it means swapping these 3 controllers to a native `<input type="color">` or a maintained picker, then testing each of those 3 screens — not a deletion.
- `garand-sticky` (`libraries/garand-sticky/jquery.sticky.js`) — actively registered by `modules/Settings/LayoutEditor/views/Index.php`. Modern CSS `position: sticky` can very likely replace this outright, but needs verifying on that one screen before removal.
- `libraries/bootstrap-legacy` — its CSS is actively loaded by `modules/Migration/views/Index.php` (the upgrade-wizard UI, a different, older skin layer than the main v7 login/app skin). Confirm whether the migration wizard is still reachable/used before touching this; it may be intentionally isolated from the BS5 migration.

**Tier 2 — deferred to advanced-customization phase** (deep functional coupling, not a safe "UI pass" item):
- `select2` — v3.4.8 in use, ~22 modules call `.select2("val"/"data", ...)` setter syntax which breaks in v4's changed API. CSS-only visual refresh applied instead of version bump.
- `chosen` — has its own wrapper abstraction layer in `resources/app.js` (ID convention `_chzn` suffix), not a trivial swap to select2.
- `bootstrap-datepicker` CSS is actually dead weight — the real widget is jQuery UI Datepicker, used broadly alongside sortable/draggable/resizable/dialog/autocomplete (28+ files) — jQuery UI itself is staying, not being ripped out.
- `bootstrapSwitch` — real state-management logic in 4 files.
- `ckeditor` (old v3/4, EOL), `jqplot` (charts, broken by `$.browser` removal — patched with the shim above but full replacement deferred), `gantt`, `gridster`.

**Tier 3 — kept as-is** (still fine): `jquery-validation`, `daterangepicker` (dangrossman), `perfect-scrollbar`, `pdfjs`, `Viewer.js`, `video-js`.

**pjax — explicitly not touched.** 21 real `.pjax()` calls in core navigation (`List.js`, `Detail.js`, `application.js`). This is AJAX-navigation infrastructure, not a UI/plugin concern — replacing it is an architecture change for the advanced-customization phase.

## Screens already rebuilt/skinned

- **Login page** (`Login.tpl`) — full rewrite, self-contained (no shared JS deps), dropped bxSlider/mCustomScrollbar for vanilla JS/CSS. Smarty wiring preserved exactly: `$ERROR`, `$MESSAGE`, `$CUSTOM_SKINS`, `$JSON_DATA`, forgotPassword flow.
- **List/Detail/Nav/Edit views** — CSS-only skin layers (`listview.css`, `detailview.css`, `nav.css`, `editview.css`), zero markup changes. These views are deeply wired to JS via exact classnames (`.listview-table`, `.fieldBlockContainer`, sort/inline-edit/quick-preview handlers) — a full rewrite here would break core CRM functions. Only the login page was safe to fully rewrite because it's isolated.

## Debugging approach that works here

1. Pull the latest repo state before diagnosing (`git pull`; watch for local uncommitted test edits blocking the pull).
2. When something "still looks broken after the fix," check in this order: (a) was the fix actually applied/pushed, (b) is Smarty cache stale, (c) is browser cache stale (`?v=8.4.0` doesn't bust Smarty server-side cache).
3. For layout bugs, get real computed-style data via browser DevTools console (`getComputedStyle`, `getBoundingClientRect`) rather than guessing from screenshots repeatedly — this found the exact `flex-wrap:nowrap` 50/50-split bug fast once real numbers were in hand.
4. Read the actual rendered HTML output when available (view-source or a pasted DOM dump) — this is far more reliable than reasoning about what the templates "should" produce, especially once several rounds of edits have happened.

# Vtiger CRM 8.4 Modernization

Self-hosted vtiger CRM 8.4 open-source install being modernized. Phase 1 (current): UI + plugin modernization. Phase 2 (later): advanced customization.

## Commands
- No JS build step and no automated test/lint suite in this repo — changes are verified by hand in the browser (see the `.tpl` cache-clear rule below).
- `composer update` — refresh PHP dependencies (Smarty, PHPMailer, TCPDF, etc., per `composer.json`). Only needed when dependency versions change.

## Environment
- macOS, XAMPP/LAMP, install path `/var/www/html/vtiger`
- Repo: `https://github.com/pilgrimsage/sage-crm`
- Templating: Smarty 5, `v7` layout (710 `.tpl` files), 52 modules
- **After ANY `.tpl` change, clear the Smarty compile cache** (`test/templates_c/v7/*`, or whatever `compile_dir` says in `config.inc.php`) and hard-refresh the browser. Skipping this is the #1 cause of "fix applied but still broken."
- **macOS `sed -i` needs an explicit empty suffix**: `sed -i ''`, not bare `sed -i`.

## Stack (target)
- Bootstrap 5.3.8 (fresh skin — old `todc-bootstrap` dropped entirely, not ported)
- jQuery 3.7.1 + jquery-migrate 3.5.2
- SweetAlert2 (replaced bootbox + bootstrap-notify)

## Design tokens (established)
Taken from the TrendyAdmin template (`bootstrapmade.com/demo/TrendyAdmin`, light and dark): purple accent, dark-purple sidebar, soft-bordered cards, Plus Jakarta Sans for text and Outfit for headings. Source of truth is `layouts/v7/lib/modern/css/theme.css` (loaded first by `Header.tpl`; `Login.tpl` loads it too). Every other modern stylesheet only uses its variables, so a change of look is a change there:
```
--background-color  --surface-color  --surface-muted  --default-color  --heading-color  --muted-color  --light-color
--accent-color  --accent-hover  --accent-soft  --accent-gradient
--border-color  --border-color-light  --success/warning/danger/info-color (+ -light)
--header-bg  --sidebar-bg/-color/-muted-color/-hover-bg  --card-bg/-border/-shadow  --table-*  --input-*
--radius-sm..2xl  --shadow-sm/md/lg  --font-body  --font-heading
```
Dark mode is `<html data-theme="dark">` (toggle in the top bar, remembered in `localStorage` as `echocrm-theme`); the dark values are the second block in `theme.css`. After changing a token's value, grep the modern CSS for the old hex/`rgba()` literal — `var()` substitution doesn't reach hardcoded copies. Own CSS lives in `layouts/v7/lib/modern/css/`: `theme.css` (tokens), `shell.css` (top bar, rail, sidebar, Settings menu, brand), `components.css` (buttons, inputs, tables, cards, modals, dark-mode surfaces), plus the per-screen files (`listview`, `detailview`, `editview`, `nav`, `select2`, `dashboard`, `widgets`, `login`).

## Working rules
- **Ask before editing.** Direct edits to the user's local files are allowed once the user has explicitly approved the specific change in chat — state the file/line change and get a go-ahead first, then apply it. Don't generalize a past approval to later, unrelated changes. A cloned copy can still be used for analysis/testing before touching the real files.
- **No shims or compatibility layers.** Direct call-site rewrites only, even when it's more files. This was explicitly decided during the Bootstrap JS API migration.
  - **Exception (2026-09-24, ADOdb→PDO migration):** `PearDatabase.php`'s query methods return a recordset object, and both its own internal code and an unknown subset of the ~255 external call sites call ADOdb-specific recordset methods directly on that return value (`FetchRow`, `EOF`, `Move`, `FieldCount`, `RecordCount`, `GetRowAssoc`, `MoveNext`, `FetchField`) rather than only going through `$adb`'s wrapper methods. Auditing and rewriting all 255 call sites individually was judged too large/risky given no automated test suite exists for this core layer. Decision: allow a thin PDO-backed recordset wrapper (`include/database/PDORecordSet.php`) matching ADOdb's recordset method names, so every call site — including ones that poke the recordset directly — keeps working unchanged. This is a narrow, explicitly-approved exception, not a precedent for other shims.
- **Keep the file system clean (user's standing rule).** PHP libraries and front-end libraries live in separate trees; within a vendored front-end library, JS and CSS are separate (`layouts/v7/lib/<name>/js/`, `.../css/`); our own CSS goes in its own file under `layouts/v7/lib/modern/css/`, our JS in the module's `resources/`. Remove what becomes unused — after a reference check (PHP registrations, templates, and internal wrapper callers), and in small commits. Do not add a second copy of a library that already exists.
- Full-tree sweeps for class/attribute renames — not per-screen. Per-screen review already missed instances once (Dashboard `data-toggle`).
- Before deleting any plugin as "unused," check for *internal* wrapper dependents (e.g. `application.js`/`app.js` helper functions), not just external `.tpl`/module references — jstorage looked dead externally but had live internal callers.
- Verify root cause with real data (DevTools computed styles, actual rendered HTML/CSS) before proposing fixes — don't guess repeatedly from screenshots alone.

## Full status, decisions, and completed work
See `.claude/skills/vtiger-bs5-migration/SKILL.md` for the complete migration log: every sweep done, every JS API rewrite, the full plugin tier audit, and known gotchas. Load it whenever working on this migration.

## Currently pending
As of 2026-10-03, the BS3→BS5 class/attribute sweep, the jQuery-plugin→vanilla-JS conversion, the `col-xs-*`, glyphicon and `.bind()/.unbind()` leftovers, and PHP 8.4 deprecations on every page checked are done and verified in the browser — see the skill log for exact scope and gotchas.

Still open:
- **Browser spot-checks not yet done:** MailConverter rule-edit form (needs a scanner configured). Import with large files was tested 2026-10-14 (18k rows; a paging bug that dropped rows past 10,000 is fixed). Done in the sandbox 2026-10-11/13: Merge Records dialog, Login at 375/768px, Inventory popovers, Leads block collapse, Import wizard end-to-end with Overwrite and Merge modes plus saved mappings, modal/form labels at 1280px (see the skill log; the label-width and block-arrow bugs found there are fixed). Tablet/phone and 1024/1440px modal checks were done on 2026-10-03.
- **Breakpoint tiers:** BS3 breakpoints are one tier above BS5's (BS3 `sm`≈BS5 `md`, `md`≈`lg`, `lg`≈`xl`). When porting `hidden-*`/`visible-*`/`col-*` classes, shift the tier — `hidden-xs hidden-sm` is `d-none d-lg-block`, not `d-md-block` (that mistake made the module menu cover the whole page at 768px). `col-sm/md/lg-*` names were left as-is, so they switch a tier earlier than in BS3.
- **Layout rule of thumb:** a container holding a BS5 `.row` directly needs ≥12px side padding (the row's −12px margins); a page that scrolls sideways by ~12px, or labels clipped at the left edge, usually means a parent with `padding:0`. Fixed for Edit views (`editview.css`) and Settings pages (`nav.css`); at <992px Config Editor labels sit flush with the left edge (tight, not clipped).
- **Known, left alone:** whole-tree lint (PHP 8.4) reports only the 3 *intentional* parse errors in `modules/ModuleDesigner/templates` (the implicit-nullable deprecations in `include/Zend/Oauth` and `kcfinder` were fixed 2026-10-13). PHPExcel is gone: Reports → XLS now uses PhpSpreadsheet (see the skill log).
- **Low priority:** phpmailer is 7.1 and the unused oauth2-google was dropped (2026-10-17); tcpdf stays on 6.11.4 on purpose (7.x is a facade over tc-lib-pdf that breaks our invoice/quote PDFs, see the skill log). The deprecated jQuery calls (`$.isArray` / `$.trim` / `$.parseJSON` / `$.isFunction`) were converted to native JS in our own code on 2026-10-13 (vendored libraries untouched).
- **Before deploying:** `config.inc.php` forces `display_errors` on / `E_ALL` ("STRICT DEVELOPMENT") — switch to the PRODUCTION line.
- **Structure pass done (2026-10-04):** `libraries/` is PHP-only; every front-end library lives in `layouts/v7/lib/<name>/{js,css}` (or `layouts/vlayout/lib/` for legacy-only ones). Register new ones as `"~layouts/".Vtiger_Viewer::getDefaultLayoutName()."/lib/<name>/..."`. Details and open items in the skill log.
- **select2 4 (2026-10-13):** `layouts/v7/lib/select2/{js,css}` is 4.0.13; its theme is `layouts/v7/lib/modern/css/select2.css`; every v3 call, option, event and class was ported (see the skill log). The `todc` folder is gone; `docs.min.css`'s only live rule (`body{position:relative}`) is in `nav.css`. Legacy `layouts/vlayout` and root `resources/app.js` still use the v3 API (that skin is not used).
- **Cleanup pass done (2026-10-04):** ~23 MB of unused files removed and the broken web installer retired (setup is `bin/setup.sh`; an unconfigured checkout answers 503 with that hint). Remaining cleanup follow-ups (Popper sitting in `todc`, dangling `libraries/bootstrap/` references, moving front-end libs out of `libraries/`) are listed at the end of the skill log.
- **Phase 2:** `gridster` → GridStack is DONE (2026-10-03). `select2` 3→4 is DONE (2026-10-13, 4.0.13). `pjax` is DONE (2026-10-13, native). `gantt` (Project) is DONE as an in-place modernisation (2026-10-13): the Teamwork jQuery Gantt is kept (it is a full editor: grid, SVG bars, dependency arrows, drag/resize, zoom, print), cleaned and fixed, not replaced. Smarty 4→5 is DONE (2026-10-13, 5.8.5). Nothing in Phase 2 is deferred any more. PHPExcel → PhpSpreadsheet is DONE (2026-10-04). `composer.json` requires `ext-imap`, which PHP 8.4 lacks; use `--ignore-platform-req=ext-imap` locally. The two known dashboard bugs (`RemoveWidget` hitting other tabs, the flatpickr dashboard date-range error) were fixed 2026-10-13.

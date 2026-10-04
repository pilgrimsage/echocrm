# Vtiger CRM 8.4 Modernization

Self-hosted vtiger CRM 8.4 open-source install being modernized. Phase 1 (current): UI + plugin modernization. Phase 2 (later): advanced customization.

## Commands
- No JS build step and no automated test/lint suite in this repo — changes are verified by hand in the browser (see the `.tpl` cache-clear rule below).
- `composer update` — refresh PHP dependencies (Smarty, PHPMailer, TCPDF, etc., per `composer.json`). Only needed when dependency versions change.

## Environment
- macOS, XAMPP/LAMP, install path `/var/www/html/vtiger`
- Repo: `https://github.com/pilgrimsage/sage-crm`
- Templating: Smarty, `v7` layout (652 `.tpl` files), 52 modules
- **After ANY `.tpl` change, clear the Smarty compile cache** (`test/templates_c/v7/*`, or whatever `compile_dir` says in `config.inc.php`) and hard-refresh the browser. Skipping this is the #1 cause of "fix applied but still broken."
- **macOS `sed -i` needs an explicit empty suffix**: `sed -i ''`, not bare `sed -i`.

## Stack (target)
- Bootstrap 5.3.8 (fresh skin — old `todc-bootstrap` dropped entirely, not ported)
- jQuery 3.7.1 + jquery-migrate 3.5.2
- SweetAlert2 (replaced bootbox + bootstrap-notify)

## Design tokens (established)
Matched from the real EchoCrew site (`dev.echocrew.in`) — the project's original invented palette was replaced. Source of truth is the `:root` block in `layouts/v7/lib/modern/css/listview.css` (globally loaded); `login.css` carries its own copy because that page loads no other stylesheet — keep the two in sync.
```
--ink:#0B0F14  --teal:#00D3B8  --teal-deep:#06A793  --teal-soft:#E4FBF6
--paper:#FFFFFF  --mist:#F6F7F5  --mist-dark:#EDEFEC  --muted:#4A555F
--line:#DFE2DD  --danger:#E4572E
--radius:6px  --radius-md:14px  --radius-lg:22px
--shadow, --shadow-sm  (see listview.css)
```
Font: Roboto, app-wide (headings and body/UI) — a deliberate user choice that replaced Sora/Inter. Loaded via the Google Fonts `<link>` in `layouts/v7/modules/Vtiger/Header.tpl`.
After changing a token's value, grep the modern CSS for the old hex/`rgba()` literal — `var()` substitution doesn't reach hardcoded copies.

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
- **Browser spot-checks not yet done:** Merge Records dialog (needs ≥2 records), MailConverter rule-edit form (needs a scanner configured), the Login page at tablet/phone width (needs a logout) (tablet 768px and phone 375px were checked on 2026-10-03 across the core list/detail/edit/Settings/overlay screens; Currency/Tax/Email Template/Add Dashboard modals at 1024 and 1440px; judge `col-lg-*` modal labels at ≥1200px), and the Inventory line-item popovers and Leads/Potentials collapsible field blocks. (Import wizard was run end-to-end on 2026-10-03 and works; only merge/overwrite modes, saved mappings and large files are untested.)
- **Breakpoint tiers:** BS3 breakpoints are one tier above BS5's (BS3 `sm`≈BS5 `md`, `md`≈`lg`, `lg`≈`xl`). When porting `hidden-*`/`visible-*`/`col-*` classes, shift the tier — `hidden-xs hidden-sm` is `d-none d-lg-block`, not `d-md-block` (that mistake made the module menu cover the whole page at 768px). `col-sm/md/lg-*` names were left as-is, so they switch a tier earlier than in BS3.
- **Layout rule of thumb:** a container holding a BS5 `.row` directly needs ≥12px side padding (the row's −12px margins); a page that scrolls sideways by ~12px, or labels clipped at the left edge, usually means a parent with `padding:0`. Fixed for Edit views (`editview.css`) and Settings pages (`nav.css`); at <992px Config Editor labels sit flush with the left edge (tight, not clipped).
- **Known, left alone:** `libraries/PHPExcel` (Reports → XLS) has ~203 PHP 8.4 implicit-nullable deprecations (as do small amounts in `include/Zend/Oauth`, `kcfinder`, qCal and google-api; whole-tree lint also reports 3 *intentional* parse errors in `modules/ModuleDesigner/templates`) that can corrupt a cold-start `.xls` download when `display_errors` is on; harmless in production. Fix by replacing it with PhpSpreadsheet in Phase 2, not by patching vendor files.
- **Low priority:** `$.isArray` / `$.trim` / `$.parseJSON` (~45 files, deprecated but working in jQuery 3.7); `composer outdated` shows only a safe patch bump for monolog (majors for smarty, phpmailer, tcpdf, oauth2-google deferred).
- **Before deploying:** `config.inc.php` forces `display_errors` on / `E_ALL` ("STRICT DEVELOPMENT") — switch to the PRODUCTION line.
- **Structure pass done (2026-10-04):** `libraries/` is PHP-only; every front-end library lives in `layouts/v7/lib/<name>/{js,css}` (or `layouts/vlayout/lib/` for legacy-only ones). Register new ones as `"~layouts/".Vtiger_Viewer::getDefaultLayoutName()."/lib/<name>/..."`. Details and open items in the skill log.
- **Cleanup pass done (2026-10-04):** ~23 MB of unused files removed and the broken web installer retired (setup is `bin/setup.sh`; an unconfigured checkout answers 503 with that hint). Remaining cleanup follow-ups (Popper sitting in `todc`, dangling `libraries/bootstrap/` references, moving front-end libs out of `libraries/`) are listed at the end of the skill log.
- **Phase 2:** `gridster` → GridStack is DONE (2026-10-03). Still deferred (deep functional coupling): `gantt` (Project), `pjax` navigation, `select2` 3→4, Smarty 4→5, and PHPExcel → PhpSpreadsheet. Known and unfixed: `RemoveWidget` is not tab-aware (removing a widget on one dashboard tab can delete the same widget type on another); a flatpickr `onReady` console error on dashboard date fields.

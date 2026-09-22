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
```
--ink:#10151C  --teal:#0E7C66  --teal-deep:#0A5C4B  --teal-soft:#E8F7F1
--paper:#FFFFFF  --mist:#F4F6F5  --mist-dark:#EDF0EF  --muted:#6B7280
--line:#E4E8E6  --danger:#E4572E  --radius:8px
```
Fonts: Space Grotesk (headings), Inter (body/UI).

## Working rules
- **Instruction-only** — give exact file/line changes; don't edit the user's local files directly. A cloned copy can be used for analysis/testing before instructing.
- **No shims or compatibility layers.** Direct call-site rewrites only, even when it's more files. This was explicitly decided during the Bootstrap JS API migration.
- Full-tree sweeps for class/attribute renames — not per-screen. Per-screen review already missed instances once (Dashboard `data-toggle`).
- Before deleting any plugin as "unused," check for *internal* wrapper dependents (e.g. `application.js`/`app.js` helper functions), not just external `.tpl`/module references — jstorage looked dead externally but had live internal callers.
- Verify root cause with real data (DevTools computed styles, actual rendered HTML/CSS) before proposing fixes — don't guess repeatedly from screenshots alone.

## Full status, decisions, and completed work
See `.claude/skills/vtiger-bs5-migration/SKILL.md` for the complete migration log: every sweep done, every JS API rewrite, the full plugin tier audit, and known gotchas. Load it whenever working on this migration.

## Currently pending
As of 2026-09-22, the app-wide BS3→BS5 class/attribute sweep and the jQuery-plugin→vanilla-JS conversion (including the `.navbar-right` fix) have been applied and verified against a live grep of the repo — see the skill log for exact scope and gotchas. Screens still need a manual pass in the browser to confirm nothing regressed (Settings module dropdowns/modals, Inventory line-item popovers, top nav alignment, Leads/Potentials collapsible field blocks).

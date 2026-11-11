# snoka-helper — Fork Specification

**snoka-helper** is a private white-label fork of [flywp/fly-helper](https://github.com/flywp/fly-helper)
(synced to upstream **v1.9.1**). End users must never see it; FlyWP integration must keep working.

## 1. Design principles (do not violate on merge)

1. **Keep upstream identifiers byte-identical.** File names (`flywp.php`), namespaces (`FlyWP\`),
   class names, option keys (`flywp_*`), slugs (`PAGE_SLUG = 'flywp'`), REST routes (`fly-api/*`),
   text domain (`flywp`), and constants (`FLYWP_*`) are integration surface. Never rename them.
   (An earlier plan to rename everything to `Snoka\*` / delete Email+Frontend was tried and
   **rejected** — it broke the FlyWP control-plane integration.)
2. **Fork changes are additive and display-only**, with two deliberate exceptions:
   `includes/Api/Health.php` (fatal fix, §4) and dead-code removal in `includes/Admin.php`
   (§3, dashboard button plumbing).
3. **Invisibility, not renaming, is the whitelabel.** Hiding is enforced by `Visibility::can_see()`,
   never by deleting features.

## 2. Divergence inventory (fork vs upstream v1.9.1)

| File | Change | Why |
|---|---|---|
| `flywp.php` | Header rebranded (Name/Author/URIs → Snoka, License Proprietary + ponytail); version kept in sync with upstream; registers `SNOKA_ADMIN_USER` default `''`; `admin_notice()` gated on `Visibility` | Identity + invisibility |
| `includes/Visibility.php` | **New.** `can_see()`: true only when current login `=== SNOKA_ADMIN_USER`. Empty/undefined constant fails closed. `WP_CLI` always sees (§5) | Invisibility gate |
| `includes/Branding.php` | Hooks also register when hidden without a brand; `all_plugins` unsets our row when hidden; `site_health` removes our entry (header name via `get_plugin_data` + default name); `update_email` strips our line when hidden, rebrands when visible | Invisibility + brand |
| `includes/Admin.php` | Page not registered when hidden; `admin_init` guard dies on direct `?page=flywp`; dashboard-button/site-info plumbing removed (§3) | Invisibility, no panel links |
| `includes/Admin/Adminbar.php` | Early return when hidden; no-brand icon is `logo.png` | Invisibility + brand |
| `includes/Admin/Plugins.php` | Settings action link gated on `Visibility` | Invisibility |
| `includes/Brand.php` | Defaults are Snoka (`DEFAULT_NAME`, `DEFAULT_URL`, email footer); `icon()` falls back to `assets/logo.png` when unset — never under another brand; email masthead uses it | Snoka default brand |
| `views/admin.php` | No-brand masthead uses `brand()->icon()`; **dashboard button removed** | No panel links |
| `views/email.php` | Panel "SMTP Configuration" link removed | No panel links |
| `includes/Api/Health.php` | `require file.php` for `get_home_path()` (upstream fatal on frontend `wp` hook) | Bug fix, §4 |
| `readme.txt` | Title → Snoka; upstream changelog/version lines kept in sync | Identity |
| `assets/logo.png` | **New.** Default brand icon; shipped in zip | Brand asset |
| `tests/` | Stub designates `csadmin`, defines `FLYWP_PLUGIN_URL/FILE`, `get_plugin_data`; 4 hidden-mode + 4 visibility tests; brand expectations updated to Snoka | Cover the fork |

## 3. Removed surfaces (deliberate, display-only)

- Settings-page **dashboard button** (`views/admin.php`) and email-tab **SMTP Configuration**
  panel link (`views/email.php`), plus their dead plumbing (`fetch_site_info()`, `get_site_url()`,
  `$app_site_url`). Side effect and intent: no outbound FlyWP-panel links in wp-admin and no
  per-page-load site-info API fetch. `Brand::dashboard_url()` itself is kept (upstream API + tests).

## 4. Upstream bug fix carried by the fork

`?fly-api=health` with a valid key fataled: `WP_Debug_Data::get_wp_server()` calls
`get_home_path()`, which lives in `wp-admin/includes/file.php` — never loaded on the frontend
`wp` hook. Fix: guarded `require_once` alongside the existing `update.php`/`misc.php` requires.
4 lines, same file pattern. Candidate for upstreaming.

## 5. Visibility contract (locked decisions)

- `SNOKA_ADMIN_USER` set → only that login sees the plugin (plugins list, dashboard page,
  admin bar, notices, Site Health, update-email line). Everyone else, **including other
  administrators and multisite super admins**, sees nothing. No bypass.
- Constant empty/undefined → **nobody** sees it (fail closed).
- `WP_CLI` → **always sees it.** `wp plugin list/status/update` resolve through the
  `all_plugins` filter, which has no user under CLI; hiding there broke management and would
  break FlyWP's own SSH-based updates. CLI operators are server admins, never browser end users.
- HTTP cron has no `WP_CLI`, so scheduled update emails still read as hidden (line stripped).
  `wp cron event run` under CLI shows the branded line — operator-invoked, accepted.
- Control-plane sync never consults `Visibility`: `Api`/`Router` bearer auth, `UpdatesData`
  (`get_plugins()` bypasses the filter by design), magic login, StaticSite engine, cache/email
  engines, `FLYWP_BRAND` handling.

## 6. White-label constants (set per client site, survives restarts)

```php
define( 'FLYWP_API_KEY', '…' );    // written by FlyWP; gates the full stack
define( 'FLYWP_BRAND', bin2hex( json_encode( [  // reseller brand (upstream feature)
    'name' => '…', 'url' => 'https://…', 'icon' => 'https://…/icon.png', // icon optional
] ) ) );
define( 'SNOKA_ADMIN_USER', 'csadmin' );  // the ONLY login that sees the plugin
```

- Brand `url` forbids query/fragment (dashboard path is appended); `icon` allows query strings.
  Both must be `https://`. Invalid → field ignored, never fatal.
- No `FLYWP_BRAND` → Snoka defaults (name/url/logo.png). Named brand without icon → name as text.
- Add defines where they **survive management**: managers like DDEV/FlyWP can regenerate
  `wp-config.php` (observed: restart wiped both defines in the sandbox). Prefer the host's
  persistent config mechanism; after any restart, verify with
  `wp plugin list` (CLI always sees the plugin) and a logged-out `?fly-api=ping` (pong).

## 7. What stays untouched (verify on every merge)

`Api`/`Router`/routes/bearer auth, `FlyApi` endpoint, `UpdatesData` cron payload,
magic login (`Frontend/MagicLogin`, `MagicLoginToken`), StaticSite engine + WP-CLI commands,
cache/email/optimization engines, `Brand::dashboard_url()`, `Branding::plugin_row_meta()`.

## 8. Upstream merge procedure

```bash
git fetch upstream --tags          # upstream remote: https://github.com/flywp/fly-helper.git
git merge upstream/main            # on a merge/* branch off this fork
```

Conflict strategy: take upstream for functional files, then re-apply §2 (our files are listed
above — that list is the whole surface). Version checklist: `flywp.php` header + `$version`,
`composer.json`, `package.json`, `readme.txt` Stable tag + changelog entry. Never change
License away from Proprietary (re-add ponytail if header conflicts).

Verification (mandatory, in `sites/snoka-helper-sandbox`):
`vendor/bin/phpunit` (144+) → `syntax-check` + `custom-check` → login matrix
(`csadmin` sees row/page/admin-bar/Site-Health; `clientadmin` sees none; direct URL dies;
bearer `?fly-api=*` 200s) → rebuild `snoka-helper.zip` (Mode C: `--no-dev` optimized
autoloader, 96+ files, `assets/logo.png` + `vendor/autoload.php` present, no dev refs).

## 9. Sandbox reference

`sites/snoka-helper-sandbox` (DDEV, `disable_settings_management: true` so `wp-config.php`
survives restarts), plugin bind-mounted, users `csadmin` / `clientadmin` (admins),
`FLYWP_API_KEY=sandbox-test-key`, `SNOKA_ADMIN_USER=csadmin`. If the router moves ports
(observed `:33001` when 443 is taken), use the URL from `ddev describe`.

## 10. Residual risks (accepted)

- Hidden ≠ uninstallable: a knowledgeable admin could still hit
  `plugins.php?action=deactivate&plugin=…` directly, and `plugin-editor.php` opens files by path.
  A running plugin can't veto its own deactivation; obscurity + control-plane sync is the mitigation.
- Fail-closed lockout: with no constant set, nobody sees the UI. Recovery via wp-config/wp-cli;
  the plugin keeps running and syncing throughout.

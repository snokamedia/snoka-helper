# WordPress Plugin Agent Instructions

## Project Overview

You are a WordPress plugin development agent. This repository follows **modern WordPress conventions (6.x+)** targeting **PHP 8.1+** with **no backward compatibility concern** for older PHP or WordPress versions. Use the latest WordPress APIs, PHP 8.x features, and block editor components without legacy shims.

## Architecture

```
{plugin-slug}/               # Must match the `name` field in package.json
├── {plugin-slug}.php        # Bootstrap: main plugin file with valid WP plugin header
├── readme.txt
├── composer.json
├── package.json             # name field here must match the directory slug
├── phpcs.xml.dist
├── phpstan.neon
├── includes/                # PHP runtime classes only (autoloaded per mode)
│   ├── Plugin.php
│   ├── Blocks/
│   ├── Rest/
│   ├── Admin/
│   ├── Domain/
│   ├── Infrastructure/
│   ├── Services/
│   ├── Support/
│   └── autoload.php              # Mode B only: PSR-4 autoloader (first-party)
├── src/                     # JS/CSS build inputs only (compiled to build/ by wp-scripts; never ships)
│   ├── index.js
│   ├── style.css
│   ├── editor.css
│   └── blocks/
│       └── {block-name}/
│           ├── block.json
│           ├── edit.js
│           ├── editor.scss
│           ├── render.php   # Copied to build/ during build; ships via build/
│           ├── style.scss
│           └── index.js
├── build/                   # Compiled JS/CSS + copied block assets (distributable output)
├── languages/
├── assets/
├── templates/
└── tests/
```

Composer PSR-4 mapping convention (for dev tools — PHPStan, PHPCS, PHPUnit):
```json
{
  "autoload": {
    "psr-4": {
      "Snoka\\{PluginSlug}\\": "includes/"
    }
  }
}
```
This mapping stays in `composer.json` for all modes. Only Mode C uses `vendor/autoload.php` at runtime — Modes A and B have their own bootstrap/autoloader.

## Runtime Bootstrap Modes

Choose your runtime mode at project setup. This decision affects autoloading, packaging, and the release checklist — it is stable for the life of the project.

| Mode | Use when | Bootstrap | `vendor/` in zip |
|---|---|---|---|
| **No autoload** | Tiny plugin, few classes, no namespace complexity | Manual `require_once` | No |
| **First-party autoload** | Default — namespaced modern PHP, zero runtime Composer packages | `includes/autoload.php` (PSR-4) | No |
| **Composer runtime** | One or more runtime Composer packages | `vendor/autoload.php` | Yes |

The rest of this document documents the **First-party autoload** mode as the default. Sections where behavior differs by mode are marked with a **Mode note** callout.

### How to Read This Document

Before applying any rule, pattern, or command from this document, first verify the project's actual runtime constraints:

1. **Check the project** — open `composer.json`. Does `require` have any entries, or is it only `require-dev`?
2. **Identify the mode** — which Runtime Bootstrap Mode does this project use? (table above)
3. **Trust the user over the doc** — if the user states a constraint that contradicts what this document says, the user is right. Don't force-fit a pattern that doesn't apply.

**Example:** If this document says "mode X is the default" but the project has zero runtime Composer deps and the user said so, use Mode A or B — not Mode C.

## Tech Stack

- **PHP**: 8.1+ (typed properties, readonly classes, enums, named arguments, match, constructor promotion, first-class callable)
- **WordPress**: 6.x, latest supported release APIs — no polyfills or compat shims
- **JS Build**: `@wordpress/scripts` with ESNext modules and `block.json` metadata
- **Blocks**: `register_block_type_from_metadata()`, dynamic server-rendered blocks where appropriate
- **REST**: Controller classes per resource on `rest_api_init`
- **DB**: Custom tables with `$wpdb::prepare()` only when CPTs + meta are insufficient
- **Testing**: PHPUnit 10+ via `yoast/phpunit-polyfills` + `wp-phpunit/wp-phpunit`

## Development Workflow

### Principle: develop from source, not from the zip

You edit files in your project directory — they are live in WordPress immediately. The zip is for **distribution only**, not day-to-day development.

### Local environment

This workspace uses **DDEV** for all WordPress local development. See the root `AGENTS.md` for DDEV setup, sandbox orchestration, and common commands.

**Do not use `wp-env`**, `npx wp-env`, or any other local WordPress environment. DDEV is the only supported tool.

```bash
ddev start
ddev exec wp plugin list
```

The plugin repo is bind-mounted into the DDEV container — edits appear immediately on browser refresh. Your development loop is: edit PHP (live), `pnpm start` (watch mode for JS/CSS), refresh browser.

### Development loop

1. **Edit PHP** — changes are live immediately on next request (no build step needed)
2. **`npm run start`** — Watch mode: JS/CSS rebuild automatically on file save
   (add `--hot` for HMR/fast refresh in supported environments; may need `allowedHosts` config with custom domains like DDEV)
3. **Refresh browser** — for PHP changes only; JS/CSS updates are applied automatically by the watcher

You **never** run `npm run zip` during development. That's only for testing the install experience or shipping a release.
`npm run build` is a production build — use it only before packaging, not during iteration.

## Composer & Autoloading

Your runtime mode (chosen above) determines how autoloading and Composer are used.

### Version control (`.gitignore`)

Applies to all modes:
- **Do not commit** `vendor/` to the repository — add it to `.gitignore`
- **Do commit** `composer.json` and `composer.lock` — they are the source of truth

---

### Mode A: No autoload

Small plugins with few classes and no namespace complexity — skip the autoloader entirely.

**Bootstrap pattern:**
```php
<?php
declare(strict_types=1);
/**
 * Plugin Name: My Plugin
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/class-plugin.php';

add_action( 'plugins_loaded', 'my_plugin_init' );
```

- No autoloader file needed
- Composer is optional — skip it entirely, or use it only for dev tooling
- No `vendor/` in the zip
- `npm run zip` runs both build and package (see Key Commands)

---

### Mode B: First-party autoload (default)

Namespaced modern PHP plugin with zero runtime Composer packages. Composer is for dev tooling only (PHPStan, PHPCS, PHPUnit).

**`includes/autoload.php`:**
```php
<?php
declare(strict_types=1);

spl_autoload_register(
    static function (string $class): void {
        $prefix   = 'Snoka\\YourPlugin\\';
        $base_dir = __DIR__ . '/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (is_readable($file)) {
            require_once $file;
        }
    }
);
```

**Plugin bootstrap requires `includes/autoload.php`**, not `vendor/autoload.php`.

**Composer PSR-4 mapping** stays in `composer.json` so dev tools find your classes:
```json
{
  "autoload": {
    "psr-4": {
      "Snoka\\{PluginSlug}\\": "includes/"
    }
  }
}
```

**Key rules:**
- `composer.json` has only `require-dev` entries (no runtime `require`)
- `vendor/` excluded from the zip
- `npm run zip` runs both build and package (see Key Commands)
- End users never need `composer install`

**Optional optimization** — for larger plugins, replace `autoload.php` with a generated classmap autoloader (build-time script scans `includes/` and emits a static `$class → $path` map). Stays in Mode B — no vendor/ shipped.

---

### Mode C: Composer runtime

Plugin has actual runtime Composer packages (e.g. a markdown parser, API client, SDK).

**Plugin bootstrap:**
```php
require_once __DIR__ . '/vendor/autoload.php';
```

**Key rules:**
- `vendor/` and `composer.json` included in the zip
- `composer.lock` excluded (stays in repo for reproducible builds)
- `files` array in `package.json` must list `vendor` and `composer.json`
- End users never need `composer install`

**Release packaging** — build from an isolated environment (CI or staging directory), not by toggling your live dev setup:
```bash
# ⚠️ NEVER: composer install (with dev deps) then zip vendor/
# The autoloader will reference dev-only packages → fatal error on activation

# ✅ CORRECT: clean build with --no-dev
rm -rf vendor
composer install --no-dev --optimize-autoloader
npm run build
npm run plugin-zip
```

---

### What goes in vs stays out of the zip

| Include | Exclude |
|---|---|
| `build/` (compiled JS/CSS + block assets), `includes/` (PHP runtime), templates, assets | `node_modules/`, `src/` (build inputs only — never ships) |
| `vendor/` **(Mode C only)** + `composer.json` **(Mode C only)** — **must be built with `--no-dev`** | `vendor/*/tests/`, `vendor/*/docs/`, `vendor/*/examples/` (Mode C) |
| License files where required | `.git/`, `.github/`, CI config files |
| `readme.txt`, `block.json` files | `composer.lock` (omit from zip) |
| | `phpunit.xml`, `phpstan.neon`, `.editorconfig` |

### `package.json` `files` templates

Explicit allowlist. `package.json` is always included automatically when `files` is present (npm-packlist behavior).

**Caveats:** The `files` field uses npm-packlist semantics, which can be surprising:
- `package.json` is **always** included, even if not listed — you cannot exclude it
- Root-level files like `readme.txt` or license files may behave differently than expected
- Inclusion rules interact with `.gitignore` and `.npmignore` in non-obvious ways

If you need precise control over zip contents, prefer a `.distignore` file instead (see below).

**Modes A & B** (no `vendor/`):
```json
{
  "files": [
    "build",
    "includes",
    "languages",
    "assets",
    "your-plugin.php",
    "readme.txt"
  ]
}
```

**Mode C** (adds `vendor` and `composer.json`):
```json
{
  "files": [
    "build",
    "includes",
    "vendor",
    "composer.json",
    "languages",
    "assets",
    "your-plugin.php",
    "readme.txt"
  ]
}
```

### `.distignore` (preferred over `files` for release control)

`.distignore` is a distribution-focused exclusion file that `wp-scripts plugin-zip` respects. Unlike the `files` field (npm-packlist semantics, always includes `package.json`, root-file quirks), `.distignore` mirrors `.gitignore` syntax and is more intuitive for excluding dev-only files from the release zip.

Create a `.distignore` at the project root:

```gitignore
# .distignore — files excluded from the release zip
.git/
.github/
node_modules/
src/
tests/
.editorconfig
.gitignore
phpcs.xml.dist
phpstan.neon
phpunit.xml
composer.lock
```

**When to use:**
- Prefer `.distignore` for most plugins — it is simpler and more predictable
- Use `files` when you need npm-packlist-based allowlisting (e.g. your `package.json` is shared with npm registry publishing)
- You can use neither — `plugin-zip` will include everything not excluded by `.gitignore` (which includes `node_modules/` and `vendor/` by default)

> Both `files` and `.distignore` can coexist; `files` takes precedence when present. If using `.distignore`, remove `files` from `package.json` to avoid subtle interactions.

### Third-party library requirements
- The plugin must be functionally complete — no runtime dependency on external services or tools
- Respect third-party licenses; include license files where required

### Dependency Management

**Preference hierarchy** — before adding any Composer package:

1. Use a WordPress core API if it already solves the problem (HTTP requests, cron, sanitization, REST routing, escaping)
2. Use a small internal utility if the need is simple and project-specific
3. Add a Composer runtime dependency only when the functionality is substantial, standardized, or impractical to maintain in-house

**require vs require-dev**

| Section | Ships in zip | Examples |
|---|---|---|
| `require` | Yes (Mode C only) | PSR contracts, Action Scheduler, API clients |
| `require-dev` | No — stripped during packaging | PHPUnit, PHPStan, PHPCS, WPCS, Rector, debug tools, test fixtures, local CLI helpers |

**Distribution test** — assume anything in `require` ships to end users. Treat adding a runtime dependency as a product decision, not a coding convenience. Does the zip need to grow for this? Will it conflict with other plugins?

**Conflict awareness** — runtime dependencies share a global namespace with other plugins. Prefer well-known, stable packages. Avoid packages likely to collide with the WordPress ecosystem.

**Documenting rationale** — when adding a new runtime dependency, include a comment or commit message explaining why a WordPress API is insufficient.

## Development Commands

```bash
# Install
pnpm install && pnpm approve-builds --all && composer install

# Build JS (dev first — use start for iteration, build for production)
npm run start          # Watch mode: auto-rebuild on save (add --hot for HMR, --blocks-manifest for block registration)
npm run build          # Production build: one-shot, minified, for packaging
npm run format         # Prettier for JS/CSS/JSON

# Lint
npm run lint:js        # ESLint via wp-scripts
npm run lint:css       # Stylelint via wp-scripts
composer lint-php      # PHPCS (WordPress-Extra + PHPCompatibilityWP)
# PHPCBF auto-fix: NOT listed here — see Tooling Safety below

# Analyse
composer analyse-php   # PHPStan with phpstan-wordpress

# Test
composer test          # PHPUnit with testdox output
composer test-coverage # HTML coverage report

# Quality Gate (run before every commit)
composer ci            # validate → lint-php → analyse-php → test → audit (checks syntax, not dependency classification)

# Package
npm run zip              # Modes A/B: build JS → zip. Mode C: requires --no-dev build first (see Release Checklist)
npm run plugin-zip       # Low-level: zip only, with --root-folder matching plugin slug (@wordpress/scripts)

# Security
composer composer-audit
```

### Tooling Safety

**PHPCBF (PHP Code Beautifier and Fixer)** is a dangerous command — it rewrites files in-place and will corrupt JS, CSS, and JSON files if the PHPCS config doesn't explicitly exclude them. This project's `phpcs.xml.dist` must include these exclusions:

```xml
<!-- phpcs.xml.dist — required exclusions -->
<exclude-pattern>*/build/*</exclude-pattern>
<exclude-pattern>*/node_modules/*</exclude-pattern>
<exclude-pattern>*/vendor/*</exclude-pattern>
<exclude-pattern>*.js</exclude-pattern>
<exclude-pattern>*.css</exclude-pattern>
<exclude-pattern>*.scss</exclude-pattern>
<exclude-pattern>*.json</exclude-pattern>
```

**When to use PHPCBF:** after editing any PHP file, run PHPCBF on that file to clean up all formatting issues — including pre-existing ones. The edited file must pass `composer lint-php` with zero warnings before you commit. Leaving auto-fixable warnings, even in code you didn't write, is a quality gate failure.

Always pass explicit `.php`-only paths — never run PHPCBF on the project root.

```bash
# Correct — always do this on files you touched:
vendor/bin/phpcbf includes/your-file.php   # single file
vendor/bin/phpcbf includes/                # directory of PHP files

# NEVER this (will corrupt non-PHP files):
vendor/bin/phpcbf                          # DANGEROUS — scans project root
```

## Coding Conventions

### PHP

- **Namespaces**: All classes in plugin namespace (e.g. `Snoka\Events`), autoloaded from `includes/` (see Runtime Bootstrap Modes)
- **Strict typing**: `declare(strict_types=1)` on every file
- **Typed everything**: typed properties, parameter types, return types on all methods
- **`final` by default**: service classes are `final` unless extension is a designed public API
- **`readonly`**: use for DTOs, value objects, and injected dependencies
- **Enums**: PHP 8.1 backed enums for finite states (status, type, mode)
- **Named arguments**: prefer named args for functions with 3+ optional params
- **Match**: use `match()` over `switch` for value matching
- **Constructor promotion**: use for all constructor-injected dependencies
- **Hooks**: register in a `register_hooks()` method on the booted class, **never** in the constructor
- **Side-effect free constructors**: constructors only accept dependencies, never call `add_action`/`add_filter`/IO
- **No singletons**: one plugin bootstrap instance + regular service objects; no magic singletons
- **Service pattern**: thin controller → service layer → persistence, never logic in hooks directly
- **Templates**: keep in `templates/` dir, loaded via extract + include, business logic stays in classes
- **Composer**: `composer-validate` and `composer-normalize` before committing composer.json changes

### JavaScript

- **ESNext modules**: import/export, destructuring, async/await
- **`@wordpress/scripts`**: use provided build toolchain, do not customize webpack
- **Dependency extraction**: use `*.asset.php` files from wp-scripts; declare WP script handles
- **Block metadata**: `block.json` for all blocks; `edit.js` + `render.php` (or render callback)
- **i18n**: `@wordpress/i18n` for all user-facing strings
- **Conditional enqueues**: load admin/frontend/editor JS only where needed
- **No global jQuery**: use `@wordpress/element` (React) or vanilla DOM; avoid `$` dependency

### CSS

- `npm run lint:css` must pass
- BEM-like naming for custom CSS
- Use WordPress admin styling conventions where applicable

## Security (Mandatory — Every Path)

Every PHP endpoint that mutates state MUST follow this pattern:

```
1. Capability check
2. Nonce verification (if browser-initiated)
3. Input validation (type, range, allow-list)
4. Input sanitization (context-appropriate)
5. Prepared SQL or WordPress API
```

### Capability Checks

```php
// ✅ Correct
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'Access denied.', 'snoka-events' ) );
}

// ✅ CPT-specific
if ( ! current_user_can( 'edit_post', $event_id ) ) {
    return new WP_Error( 'forbidden', __( 'Cannot edit.', 'snoka-events' ), [ 'status' => 403 ] );
}

// ❌ WRONG — logged-in is not authorization
if ( is_user_logged_in() ) { ... }

// ❌ WRONG — too broad for destructive actions
if ( current_user_can( 'read' ) ) { ... }
```

### Nonces

```php
// ✅ Admin form
wp_nonce_field( 'snoka_save_event', 'snoka_event_nonce' );
check_admin_referer( 'snoka_save_event', 'snoka_event_nonce' );

// ✅ AJAX
check_ajax_referer( 'snoka_ajax_action', 'nonce' );

// ✅ REST (use X-WP-Nonce middleware, no manual nonce in REST routes)
// Browser REST mutations use wp-api fetch with credentials: 'include'
```

### Input Validation & Sanitization

```php
// ✅ Validation
$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
$status = in_array( $status, [ 'draft', 'publish' ], true ) ? $status : 'draft';
$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

// ❌ WRONG
$event_id = $_POST['event_id']; // no sanitization
$status   = sanitize_text_field( $_POST['status'] ); // use validation for enums, not text sanitization
```

### Output Escaping

```php
// ✅ Context-aware escaping
echo esc_html( $title );
echo '<input value="' . esc_attr( $value ) . '" />';
echo '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
echo '<textarea>' . esc_textarea( $content ) . '</textarea>';
echo wp_kses_post( $description ); // limited HTML
echo '<script>var data = ' . wp_json_encode( $data ) . ';</script>';

// ❌ WRONG
echo $title;
echo '<input value="' . $value . '">';
echo '<script>var x = "' . $label . '";</script>';
```

### SQL

```php
// ✅ Prepared — values
$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}events WHERE id = %d", $event_id );

// ✅ Whitelisted — identifiers/ORDER
$order = in_array( strtoupper( $order ), [ 'ASC', 'DESC' ], true ) ? $order : 'DESC';
$sql = "SELECT * FROM {$wpdb->prefix}events ORDER BY created_at {$order}";

// ❌ WRONG — string interpolation
"SELECT * FROM {$wpdb->prefix}events WHERE id = $event_id"

// ❌ WRONG — prepare doesn't protect keywords
$wpdb->prepare( "ORDER BY created_at $order", ... )
```

### REST API Security

```php
// ✅ Every route needs explicit permission_callback
register_rest_route( 'snoka/v1', '/events', [
    'methods'             => WP_REST_Server::READABLE,
    'permission_callback' => fn() => current_user_can( 'edit_posts' ),
    'callback'            => [ $this, 'get_items' ],
    'args'                => [
        'per_page' => [
            'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0 && $v <= 100,
            'sanitize_callback' => 'absint',
        ],
    ],
] );

// ❌ WRONG — public mutation
'permission_callback' => '__return_true'
// Only acceptable for genuinely public read-only endpoints
```

### AJAX Security

```php
add_action( 'wp_ajax_snoka_save', function () {
    check_ajax_referer( 'snoka_ajax', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => __( 'Forbidden', 'snoka-events' ) ], 403 );
    }
    // ... sanitize input, do work, send response
} );
```

## Block Development

### Structure
- Each block gets its own directory under `src/Blocks/{block-name}/`
- Every block requires a `block.json` — this is the source of truth for name, title, icon, attributes, supports, and editor script/style handles
- Prefer `register_block_type_from_metadata()` for block.json-based registration. Use manual `register_block_type()` with PHP arrays when metadata-driven registration is insufficient
- For multi-block plugins, use `@wordpress/scripts` `--blocks-manifest` flag in `build`/`start` to generate a blocks manifest, then register via `wp_register_block_types_from_metadata_collection()` (WP 6.8+) or `wp_register_block_metadata_collection()` (WP 6.7). Single-block plugins can skip the manifest and use `register_block_type_from_metadata()` directly.

### Block Architecture
- **Dynamic blocks** (server-rendered) for any data-driven content: lists, calendars, queries, dynamic data. Use a `render.php` file or `render_callback` in PHP
- **Static blocks** (client-rendered) only for purely presentational content with no backend dependency
- Keep `edit.js` (editor UI) and `render.php` / `render_callback` (frontend) fully separated — never couple them
- Use `useBlockProps()` in both edit and save/render for proper block wrapper attributes
- Inspector controls (`InspectorControls`) for user-configurable settings; toolbar controls (`BlockControls`) for quick actions

### Attributes & Supports
- Define attributes with explicit types and defaults in `block.json`
- Use `supports` for built-in features: `align`, `anchor`, `className`, `spacing`, `typography`, `color`
- Prefer core `supports` over custom attribute implementations whenever WordPress provides them
- Use `variations` for blocks that have multiple display modes (list/grid/calendar)

### Best Practices
- Prefer enqueuing block assets via `block.json` metadata. Reserve manual `wp_enqueue_script()` / `wp_enqueue_style()` calls for non-block assets or exceptional integration cases
- Use `@wordpress/scripts` dependency extraction — WP handles from `*.asset.php` declare your deps automatically
- Load editor CSS only in the editor, frontend CSS only on the frontend (configured in `block.json` `editorStyle` vs `style`)
- Avoid frontend JS hydration when server-side rendering is sufficient
- Use Interactivity API (`@wordpress/interactivity`) for any client-side interactivity on dynamic blocks — not custom jQuery or vanilla JS
- Make blocks keyboard-navigable, include ARIA labels, and test with screen readers
- Lazy-register blocks so editor assets only load when the block is used
- **Escaping in render callbacks**: dynamic block output in `render.php` follows the same strict escaping rules as templates — `esc_html()`, `wp_kses_post()`, `esc_attr()` as appropriate
- **Internationalization**: use `wp_set_script_translations()` in PHP for block/editor script translations; use `__()`, `_e()`, `esc_html__()` consistently with the same text domain across PHP and JS

### File Organization (per block)
```
src/Blocks/event-list/
├── block.json
├── edit.js
├── editor.scss
├── render.php
├── style.scss
└── index.js
```

## REST API

- Register routes on `rest_api_init` via controller classes
- One controller per resource (e.g. `Events_Controller`, `Venues_Controller`)
- Every route gets: `permission_callback`, `args` with `validate_callback` and `sanitize_callback`
- Return `WP_REST_Response` for success, `WP_Error` for failures with proper status codes
- Service layer is source of truth; REST controllers are thin adapters
- Use `register_rest_field()` or `register_rest_route` for exposing meta — always with `get_callback` + `permission_callback` for private meta

## Database

- **Default to CPTs + post meta** unless query patterns justify custom tables
- Register all meta with `register_post_meta()` / `register_term_meta()` — include `type`, `sanitize_callback`, `auth_callback`, `show_in_rest`
- **Custom tables** only for: high-volume relational data, date-range queries, predictable indexing needs
- Always use `$wpdb->prepare()` for custom SQL
- Schema changes in `dbDelta()` inside an `upgrade()` method, not on every page load
- Cache priming for batch operations

## Activation & Uninstall

### Version-based migrations (preferred over activation hooks)

Instead of relying on `register_activation_hook()` for setup changes, use a version check run on every `plugins_loaded`. This avoids requiring deactivation/reactivation during development — changes take effect on page refresh.

```php
$installed_version = get_option( 'snoka_plugin_version', '0.0.0' );

if ( version_compare( $installed_version, '1.1.0', '<' ) ) {
    // Add DB table, set option, register capability, etc.
    update_option( 'snoka_plugin_version', '1.1.0' );
}

// Repeat for each migration step. Fresh installs run all migrations.
update_option( 'snoka_plugin_version', CURRENT_PLUGIN_VERSION );
```

This pattern handles fresh install (no version → runs all) and updates (runs only new steps) equally — no uninstall/reinstall needed during development.

### Activation hooks

- Keep `register_activation_hook()` only for truly one-time setup: setting rewrite rules, scheduling cron events, initial option defaults. Use version-based migrations (above) for everything else.
- Uninstall cleanup must use `uninstall.php` or a dedicated hook (`register_uninstall_hook()`). Never leave orphaned options, meta, or custom tables.
- Use `deactivation_hook()` only for scheduled task cleanup (`wp_clear_scheduled_hook()`), not for data removal.

## Quality Gate

Run `composer ci` (validate → lint-php → analyse-php → test → audit) before committing. Also run `npm run build` if JS changed.

**PHPCBF safety:** `composer ci` does not run PHPCBF — only `composer lint-php` (read-only check). Never run `vendor/bin/phpcbf` on the project root; it will corrupt JS/CSS/JSON files (see Tooling Safety above). Instead, run PHPCBF on individual `.php` files you edited (`vendor/bin/phpcbf path/to/file.php`) so they pass with zero warnings before committing.

For release packaging, see the Release Checklist below.

## Release Checklist

Only for testing the install experience or shipping a release — not for day-to-day development.

1. **Build the zip** — approach depends on your runtime mode:

   All modes should define `plugin-zip` in `package.json` with `--root-folder` to control the zip root independently of `package.json name`:
   ```json
   "plugin-zip": "wp-scripts plugin-zip --root-folder=plugin-slug"
   ```

   **Modes A & B** (no `vendor/` in zip):
   ```bash
   npm run build && npm run plugin-zip
   ```
   That's it — no composer toggle needed.

   **Mode C** (Composer runtime): build from an isolated environment to avoid shipping dev dependencies:

   > **⚠️ Critical**: Running `composer install` (with dev deps) and then zipping `vendor/` will cause a **fatal error on plugin activation**. The Composer autoloader eagerly requires files from dev-only packages (phpunit, phpstan, wp-cli, etc.) that don't exist in the zip. You **must** use `--no-dev` to regenerate the autoloader with only production packages.

   ```bash
   # CI: clean checkout → composer install --no-dev → npm run build → npm run plugin-zip
   rm -rf vendor
   composer install --no-dev --optimize-autoloader
   npm run build
   npm run plugin-zip

   # Staging script (solo dev compromise):
   rsync -a --exclude='node_modules' --exclude='vendor' . .release/plugin-slug
   cd .release/plugin-slug
   composer install --no-dev --optimize-autoloader
   pnpm install --no-frozen-lockfile && pnpm approve-builds --all && pnpm build
   npm run plugin-zip
   ```
2. **Plugin Check** — Run via WP Admin (Tools → Plugin Check) or `wp plugin check <plugin-main-file>` via WP-CLI
3. **Archive inspection** — Unzip and confirm: single top-level folder, `includes/` exists, `src/` absent, no `node_modules/`, no dev config files. Mode C: also confirm `vendor/` contains only runtime code (no `*/*/tests/`, `*/*/docs/`, etc.). If you used `.distignore`, verify it correctly excluded everything you intended.
4. **Smoke test** — Install zip in a WordPress environment, confirm no PHP fatals on activation and page load

### Zip Structure & Naming

Use `plugin-zip --root-folder={slug}` (see Release Checklist) to control the root folder name explicitly — independent of `package.json name`. This avoids coupling zip structure to a single config field.

| Requirement | Why |
|---|---|
| `--root-folder` matches plugin slug directory | Zip extracts to the expected WordPress plugins folder |
| `--root-folder` is stable across releases | Prevents duplicate installations on update |
| Main PHP file has valid plugin header | WordPress needs the header to discover the plugin |
| Zip extracts to a single top-level folder | WordPress installs into `wp-content/plugins/{slug}/` |

Correct structure inside the zip:
```
snoka-log.zip
└── snoka-log/                  # controlled by --root-folder, not package.json name
    ├── snoka-log.php           # main file with valid plugin header
    ├── includes/               # PHP runtime classes
    ├── build/                  # Compiled JS/CSS + block assets
    ├── languages/
    ├── readme.txt
    ├── ... runtime files
    └── vendor/                 # Mode C only: Composer autoloader + runtime deps
```

Wrong patterns that cause update issues:
- Files at zip root (no top-level folder) — WordPress extracts into `plugins/_macosx/` or similar
- Versioned folder (`my-plugin-1.2.3/`) — creates a new folder each update instead of replacing
- `package.json` `name` different from the actual directory — zip filename doesn't match plugin slug

## Commit Conventions

- `feat:` — new feature
- `fix:` — bug fix
- `refactor:` — code change without feature/fix
- `test:` — adding/fixing tests
- `chore:` — tooling, CI, dependency updates
- `docs:` — documentation
- `security:` — security hardening
- `perf:` — performance improvement

## Workflow Rules

1. **Understand first** — grep/read the codebase to understand existing patterns before writing code
2. **Mimic conventions** — match the code style, architecture patterns, and naming of the existing code
3. **Security-first** — every state-changing code path must have capability + nonce + validation + sanitization
4. **Modern PHP** — use typed properties, named arguments, enums, readonly classes, match, constructor promotion
5. **No backward compat** — use the latest WordPress APIs; do not write polyfills or fallbacks for old WP/PHP
6. **Verify with tooling** — run lint, static analysis, and tests after every significant change
7. **Write tests** — PHPUnit for PHP logic, integration tests for REST/DB paths
8. **Commit after important phases** — feature complete, bug fix, refactor, security patch, test suite addition
9. **Write in plain language** — for any content longer than a few sentences, consult `@WRITING.md` and follow its rules
10. **No comments in code** — unless documenting a hook parameter or a non-obvious side effect
11. **No `@package` or `@author` tags** — namespace and class name are sufficient

## Consult Perplexity/Proxima Frequently

This is a critical workflow rule — do not guess when you can look it up.

- **Before any decision** about WordPress APIs, block editor patterns, security conventions, or plugin architecture, consult `proxima_ask_perplexity` first
- **Before writing code** that uses a WordPress API you are not 100% certain of, look it up via Perplexity
- **Phase checks**: after every significant phase (feature, refactor, security patch), use Perplexity to verify your approach is still current with WordPress 6.x best practices
- **When uncertain**: if you find yourself second-guessing your approach or lacking certainty about an API, pattern, or convention — stop and query Perplexity immediately. Do not guess.
- **For verification**: after completing a block, REST endpoint, or security-sensitive path, ask Perplexity "review this against current WordPress plugin best practices" before marking done
- **External sources**: prefer linking to or quoting WordPress developer docs, plugin review guidelines, and `@wordpress/scripts` docs — not blog posts or tutorials

**See also:** AGENTS.md Rule 2 (Runtime Reality First) — user-stated constraints override documented defaults.

The goal: zero guessing. If you are unsure, Proxima/Perplexity is your safety net. Use it.

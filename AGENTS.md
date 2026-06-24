# snoka-helper

> **Plugin-specific instructions:** Create `AGENTS.local.md` in this
> directory with custom agent rules. The shared rules below always apply.

---
## Shared Agent Rules

## Task Type

See `../../wp-agent/AGENTS.md` — same task type rules apply:

- **Plugin code work**: follow all rules, consult Perplexity for APIs.
- **Operational work** (sandbox, seeding, DDEV, pnpm, backups, building): execute commands immediately. No planning, no Perplexity, no WORDPRESS.md.

## One-Command Sandbox

Provision a full sandbox (create + DDEV start + plugin mount + blueprint) in a single bash tool call:

```bash
wsl.exe -d Ubuntu -- bash /home/snoka/.ddev/commands/host/sandbox-create-blueprint <sandbox-name> <plugin-slug>
```

The host command at that path handles PATH setup and all DDEV operations. After it finishes, the sandbox is running at `<name>.ddev.site` with the plugin activated and its demo content loaded.

**Idempotent:** Safe to rerun. If sandbox exists with running DDEV, re-applies blueprint. If exists but DDEV not running, starts it first. Use `--force` to destroy and recreate.

## Workspace Context

You are inside a plugin repo at `/home/snoka/Projects/WordPress/plugins/<slug>`.

- **Shell runtime trap** — the bash tool runs in PowerShell, not WSL. Never run `export PATH`, `cd`, or `ddev` directly from the bash tool — always write a `.sh` script file and execute via `wsl.exe -d Ubuntu -- bash /path/to/script.sh`. See `../../AGENTS.md` section "Shell Runtime Context (CRITICAL)".
- **Temp scripts** — write `.sh` files in the sandbox dir (`sites/<sandbox>/tmp/`), plugin dir, or a `tmp/` dir you create. Clean them up after execution.
- **Docker pre-check** — before running DDEV, verify Docker is healthy: `docker info 2>&1 | head -3`. If it hangs, Docker Desktop is crashed. See `../../AGENTS.md` "Docker Desktop: Crash Recovery".
- **Sandboxes** — live in `../../sites/` (DDEV-managed WordPress instances)
- **Central rules** — `../../wp-agent/` contains the canonical set of agent rules
- **Shared assets** — `../../shared/blueprints/assets/` has pre-downloaded theme/plugin zips
- **DDEV only** — Rule 11 in the root AGENTS.md
- **pnpm only** — never `npm install`. After every `pnpm install`, run `pnpm approve-builds --all`

When starting work that involves **writing WordPress plugin code** for the first time, **read `WORDPRESS.md` first** — it contains all architecture, security, and coding convention guidance. After that, use it as a reference; you do not need it in every context. For operational or sandbox tasks, WORDPRESS.md is not needed — skip it.

## Core Rules

1. **Consult before guessing (code only)** — before any decision about WordPress APIs, patterns, or architecture, use `proxima_ask_perplexity`. Do not guess when you can look it up. **Exception:** trivial operational steps (ddev start, running a script, pnpm install, etc.) do not need consultation — just run them.
2. **Runtime Reality First** — before proposing any architecture or workflow, verify the project's actual runtime constraints. If the user states a constraint that contradicts the convention doc, trust the user — derive your solution from verified constraints, not documented defaults. Never force-fit a pattern (Composer, autoloading, build tools) when the user has explicitly said it doesn't apply.
3. **Security-first** — every state-changing path needs capability check + nonce + validation + sanitization + escaping. See `WORDPRESS.md` for exact code patterns.
4. **No backward compat** — target PHP 8.1+ and WordPress 6.x latest. Use modern APIs, no polyfills.
5. **Quality gate** — run `composer ci` (lint → phpstan → test → audit) **and** `npm run build` (if JS changed) before committing. Do **not** run `npm run zip` during development — zip is for testing the install experience or shipping a release (see Release Checklist in `WORDPRESS.md`).
    - **Never run PHPCBF on the full project** — it rewrites files in-place and will corrupt JS/CSS/JSON if the PHPCS config doesn't exclude them (see `phpcs.xml.dist` exclusions in WORDPRESS.md). However, **always run PHPCBF on every `.php` file you edit** — use explicit paths: `vendor/bin/phpcbf path/to/file.php`. This cleans up all formatting issues in that file, including pre-existing ones. Leaving auto-fixable warnings is a quality gate failure.
6. **Modern PHP** — typed everything, readonly classes, enums, named arguments, constructor promotion, match expressions.
7. **Block metadata** — every block uses `block.json`; register single blocks with `register_block_type_from_metadata()`, and for multi-block plugins prefer metadata collection registration on WordPress 6.8+.
8. **Write in plain language** — for any content longer than a few sentences, consult `@WRITING.md` and follow its rules.
9. **Commit after important phases** — feature complete, bug fix, refactor, security patch, test suite.
10. **SVG icons** — WordPress admin CSS forces `fill: currentColor` on all SVGs inside `.components-button`. Always use Lucide-style stroke icons (`fill="none"` + `stroke="currentColor"`) for UI icons. Never put multi-color SVGs inside buttons. See `SVG_ICONS.md`.
11. **DDEV only** — use DDEV for all WordPress local development. Never use `wp-env`, `npx wp-env`, Docker Compose, or any other local environment.
12. **pnpm only** — never `npm install`. After every `pnpm install`, run `pnpm approve-builds --all` to approve build scripts. Verify the store path after install.
13. **Backup after commit** — after any meaningful commit, run the Windows backup script via `powershell.exe`. See root `AGENTS.md` for the exact command.
14. **Git identity** — pass identity via env vars on every commit:
    ```bash
    GIT_AUTHOR_NAME="Snoka Media" GIT_AUTHOR_EMAIL="info@snoka.ca" \
    GIT_COMMITTER_NAME="Snoka Media" GIT_COMMITTER_EMAIL="info@snoka.ca" \
    git commit -m "..."
    ```

## Key Commands

| Command | Purpose |
|---|---|
| `npm run start` | Watch mode — JS/CSS rebuild on save (use during dev) |
| `npm run build` | Production build — run before commit and packaging |
| `composer lint-php` | PHPCS check |
| `composer analyse-php` | PHPStan analysis |
| `composer test` | PHPUnit |
| `composer ci` | Full quality gate |
| `pnpm approve-builds --all` | Approve pnpm build scripts (run after every `pnpm install`) |
| `ddev wp-plugin-build <slug>` | Build plugin on host, verify artifacts in container, activate |
| `npm run zip` | Package zip — **Mode C WARNING**: requires `--no-dev` build first, see Release Checklist in `WORDPRESS.md` |

### Zip Build Requirement (Mode C)

**Critical**: If the project uses `vendor/` at runtime (Mode C — Composer runtime), you **must** build the zip from an isolated environment with `composer install --no-dev --optimize-autoloader`. Running `npm run zip` directly from a dev environment will include dev-only packages in the autoloader, causing fatal errors on activation. See the Release Checklist in `WORDPRESS.md` for the exact steps.

## When to Read WORDPRESS.md

The full file is meant for **initial project setup and reference**. For day-to-day work, these AGENTS.md rules are sufficient. Reach back into `WORDPRESS.md` when dealing with:

- Security-sensitive code paths (capabilities, nonces, escaping, SQL)
- Block registration and metadata patterns
- REST API controller structure
- Database decisions (CPTs vs custom tables)
- Commit message format
- Runtime mode selection (autoloading, packaging, Composer patterns)
- Any uncertainty about WordPress conventions

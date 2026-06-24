# WordPress Abilities API

> Requires WordPress 6.9+. This API is part of the "AI Building Blocks for WordPress" initiative.

The Abilities API provides a standardized, machine-readable way to expose plugin functionality to AI agents, automation tools, and other plugins via PHP, REST, and JavaScript. Each registered ability is a discrete callable unit with defined inputs, outputs, permissions, and a category.

**This file lives in every block plugin directory and in the project root.** Agents reading this file should use it as the authoritative reference when adding or updating abilities in any plugin.

---

## When to Register Abilities

Not every block or plugin feature should declare abilities. Use this checklist before registering anything:

**Good candidates:**
- Actions that create, read, update, or delete data (posts, settings, plugin-managed records)
- Operations that an agent could meaningfully invoke without a UI (e.g. "generate a map embed", "clear the cache", "export club member list")
- Features where an agent needs to know what inputs are valid and what output to expect

**Poor candidates or skip entirely:**
- Purely presentational blocks with no server-side data operation (e.g. a video block that only renders an iframe — there is nothing for an agent to invoke)
- Blocks that wrap third-party embeds where the actual operation happens outside WordPress
- Blocks with no inputs and no meaningful output beyond markup
- Anything where registering an ability would duplicate a standard WordPress REST endpoint without adding value
- Abilities that would be dangerous to expose to REST without a hardened permission check

If a block or feature falls into the "skip" column, add a comment in the plugin's main PHP file noting why abilities were not registered. This prevents future agents from assuming the omission was an oversight.

---

## File Structure Convention

Each plugin that registers abilities should organize the code as follows:

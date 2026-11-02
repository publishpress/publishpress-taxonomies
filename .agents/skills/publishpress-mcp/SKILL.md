---
name: publishpress-mcp
description: Implement or extend Taxonomies Free and Pro MCP abilities using native taxonomy, term, preset, schedule and settings services.
---

# PublishPress Taxonomies MCP integration

Taxonomies owns abilities; Hub owns transport and exposure. Start with `inc/mcp/registry.php`, `contracts.php`, `operations.php`, `terms.php` and `manage.php`. Pro adds field contracts in `includes-pro/mcp.php` and loads the shared Free code through `lib/composer.json`. Do not duplicate the integration or edit vendor copies.

Follow native `SimpleTags_*`/`TaxoPress_*` classes, snake-case methods and PHP 7.4 compatibility. Register private abilities outside admin-only bootstrap, independently of the authenticated user's capabilities. Enforce actual capabilities at execution and preserve application-password restrictions; Administrator status is not a bypass.

Use the native settings model in `inc/helper.options.admin.php`, default helpers and existing preset save/copy/delete services. Auto Links has a per-request cache: refresh it for writes and read-back. Taxonomy edit validation accepts the original slug explicitly; do not simulate POST state or mint nonces. Use the shared native preset-title normalizer and avoid double unslashing titles, templates or arrays. Native registration flags use true/false strings, while site checkboxes and preset selects have distinct JSON contracts. Verify exact types and native stored values.

Reuse the Pro native linked-term planner for assignments; retain direction semantics and validate all changed taxonomy/term permissions before writing. Term-copy previews use opaque metadata revisions and the exact native content scope, without returning metadata. Use native term, post, schedule, merge and CSV services with strict validation, revisions and previews. CSV export shares term descriptions and post IDs with the connected client: retain object permissions and bounds. Authorize every affected object before merge/deletion and all bulk entries before writing. Use native protected-feature permissions rather than administrator-role shortcuts. Keep credentials and saved provider diagnostics outside inputs and outputs.

Auto Terms counting is not a preview. The native matcher has an explicit local preview mode that returns before persistence. MCP applies through permission-preserving WordPress APIs; never reuse hook-removal or SQL fallbacks to force assignments. Local matching uses only the primary taxonomy; additional targets are specific to the native OpenAI batch flow. Provider execution stays native-only. Use shared native setters for post overrides, custom URLs, synonyms, schedules and inline metabox labels. Label revisions include their dedicated options as well as the plugin container. Use the shared Posts query for term-to-content lookup and filter every returned post by native permissions. The native CSV writer accepts a final assignment-authorizer for newly created term IDs; retain MCP preflight and final object checks.

Maintenance actions must preflight the whole bounded scope. Reuse native conversion, reference migration, usage-threshold and match-selection services. Preserve default terms, complete hierarchies, metadata, native references and Pro ordering/linked identities; do not rewrite third-party content or configuration. Recheck per-object revisions during batches and report partial outcomes honestly.

Run `tests/Mcp/smoke.php` with the released Adapter and real native persistence. Test Free and source-assembled Pro separately, including malformed types, field round trips, denied capabilities, bulk preflight, revisions and unrelated-data preservation. Distinguish these tests from release-ZIP and HTTP-session verification. See `docs/mcp-integration.md` for current usage and limits. Do not leave audit reports, modify vendor dependencies, stage or commit without an explicit request.

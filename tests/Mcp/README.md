# Taxonomies MCP regression tests

Use an isolated WordPress installation with a disposable database named `taxonomies_mcp_free` or `taxonomies_mcp_pro`. The test rejects other database names. Activate the respective edition, with the shared Free source bundled in Pro. Block external HTTP and mail with test-only MU filters.

Run from a PHP process, passing the native WordPress loader and a released MCP Adapter 0.7.1 checkout with its dependencies:

```sh
TAXONOMIES_MCP_MODE=free php tests/Mcp/smoke.php /isolated/wordpress/wp-load.php /isolated/mcp-adapter
```

For Pro use `TAXONOMIES_MCP_MODE=pro`. The suite performs real Abilities/Adapter tool execution and native database writes, including every declared preset field, settings types, previews, read-back, revisions, bulk preflight and partial failures, native Posts lookup, schedules, primary-taxonomy local matching, merge, CSV transfer and per-term assignment denials and Pro directional relationship propagation, cross-taxonomy denials, term and configuration copies, metadata revisions and ordering. The maintenance cases exercise taxonomy renames/conversions, complete-hierarchy moves, all native preset reference stores, metadata and hidden-state preservation, literal name replacement, match-based assignments, protected rare-term cleanup, scope limits and partial persistence failures. Synthetic fixtures and application passwords stay in the disposable site.

Set `TAXONOMIES_MCP_CAPABILITIES_FILE` to the existing Capabilities `includes/application-passwords.php` to exercise actual application-password restrictions. That optional component is not bundled or modified by this test.

These are native WordPress workflows through the released Adapter tool layer. They are not remote HTTP session tests, built release-ZIP tests or real external-provider executions.

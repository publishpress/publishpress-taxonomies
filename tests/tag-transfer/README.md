# Term Transfer Regression Tests

Local-only, opt-in tests. Use a disposable database or take a database backup
first. Never point these scripts at production. No real user CSV is included.
Fixtures use random `ttt-test-` / `ttt-http-` prefixes and are removed in `finally`.
The runtime suites throw on failure; they do not terminate WP-CLI with `exit`.

## Reproduce With DevKinsta

Run from the integrated checkout. Copy outside `wp-content/plugins`; do not
activate, replace, or reactivate the installed plugin. These commands use PHP
7.4 and WP-CLI in the existing local container. Adjust explicit paths/container
for another disposable local site. Requirements: existing administrator, enabled
Manage Terms setting, `simple_tags` capability, cURL extension, writable `/tmp`.

```sh
docker cp . devkinsta_fpm:/tmp/taxonomies-transfer-integration

local_test() {
  docker exec \
    -e TAXOPRESS_TEST_LOCAL=1 \
    -e TAXOPRESS_TEST_WP_ROOT=/www/kinsta/public/metaslider \
    -e TAXOPRESS_TEST_PLUGIN_DIR=/tmp/taxonomies-transfer-integration \
    -e TAXOPRESS_TEST_HTTP_TOKEN=local-integration-test-token-2026 \
    devkinsta_fpm "$@"
}

local_test php7.4 /usr/local/bin/wp --allow-root --skip-plugins \
  --path=/www/kinsta/public/metaslider eval-file \
  /tmp/taxonomies-transfer-integration/tests/tag-transfer/service-runtime.php

local_test php7.4 \
  /tmp/taxonomies-transfer-integration/tests/tag-transfer/csv-mocks.php
```

Start the following in a separate terminal, using the same environment/function:

```sh
local_test php7.4 -S 127.0.0.1:19274 \
  /tmp/taxonomies-transfer-integration/tests/tag-transfer/http-router.php
```

Then run the HTTP suite and stop the server with Ctrl-C in its terminal:

```sh
local_test php7.4 /usr/local/bin/wp --allow-root --skip-plugins \
  --path=/www/kinsta/public/metaslider eval-file \
  /tmp/taxonomies-transfer-integration/tests/tag-transfer/http-runtime.php
```

For a different port, set `TAXOPRESS_TEST_HTTP_PORT` in the runtime command and
change the server address. Replace the example token with a fresh secret for
each session. No server is needed for the first two suites.

## Isolation And Coverage

Service tests load only `TaxoPress_Tag_CSV_Service`, not the new plugin bootstrap.
They verify CSV fidelity against raw WordPress term storage, unused tags, exact
same-ID assignments, default skip/update behavior, unrelated relationship
preservation, resilient missing/unsupported assignment skips, unique skip counts,
authorization failures, legacy CSV, idempotence, unchanged post
records and published-post term counts. Fixture posts are drafts.

CSV mocks never bootstrap WordPress or access a database. They generate 159 tags,
241 associations and 31 IDs in memory. Coverage includes parser fidelity,
preflight caching, malformed IDs/CSV/UTF-8, permission failures and configured
row/association/byte limits. Mutation functions throw if called.

HTTP router requires loopback peer, loopback Host, CLI server and secret header.
Before `wp-load.php` it excludes all installed normal plugins in memory, blocks
external WordPress HTTP calls and cron scheduling, and returns `4.0.0` from
`pre_option_simpletags-version`. This last guard prevents constructor upgrades
of existing 3.x plugin options. It includes the external checkout on
`muplugins_loaded`, before plugin initialization hooks. It never fires
`admin_init` (a guard throws if accidentally fired), activation or installers.
Legacy selector preference writes are suppressed in memory during render.
Stored `active_plugins` is never changed. MU plugins/drop-ins are not excluded;
use an isolated database if they have bootstrap side effects.

HTTP tests dispatch real registered export/import callbacks with real multipart
uploads. They test POST, nonces, feature toggle, `simple_tags` and taxonomy
capabilities, resilient assignment handling through parse/preflight/import, permission
failures, post preservation, idempotence, all six real Manage Terms tabs and
their default/order, page-level `render_notice()` placement and consumption,
`render(false)` compatibility, checkbox markup and escaped result details. SQL snapshots compare
all options (except the tested result transient), posts, post-tag terms and
relationships before/after render and after fixture cleanup. Existing result
transient contents/timeout are restored. A mode-0600 option snapshot is printed
as `PRE_BOOTSTRAP_OPTIONS_BACKUP`; keep it private and outside the repository.
Unexpected unrelated options are reported, not silently overwritten.

These are not installation, migration, multisite, browser accessibility or
concurrent-write tests. No plugin activation or full installation is claimed.
Use an idle local site: unrelated cron/plugin writes invalidate snapshots.
Post/term auto-increment sequences advance while creating disposable fixtures.

## Verified Source And Results

The standalone CSV suite was rerun on 13 October 2026: **189 PASS, 0 FAIL**,
including matching/mismatched CSV context and hierarchical-parent validation. The
database-backed service and HTTP suites still require the disposable WordPress
environment described above before release. Current source hashes are:

| File | SHA-256 |
| --- | --- |
| `inc/class-taxopress-tag-csv-service.php` | `038599f4ed633ac0bd3f3a4bb1ba15c617ba6a89949b1afe1789231e877721ed` |
| `inc/class-taxopress-tag-transfer.php` | `33ac89ffb5cab2deec53e3f26fffed31b31b07c743ac2e5ee1eb6d8c71aaff9a` |
| `inc/class.admin.manage.php` | `a7487b369772f40f088601a8d811b8147bca02a04d917a6483b6afa18b401ba1` |

Service APIs accept optional taxonomy and post-type arguments after the existing
parameters: `parse_csv()`, `validate_assignments()`, `import_rows()`, and
`write_csv()`. Adapter: `TaxoPress_Tag_Transfer::render($show_results=true,
$taxonomy='post_tag', $post_type='post')`;
`admin_post_taxopress_import_tags` / `admin_post_taxopress_export_tags`.
Exports start with `taxopress_terms_csv,1,{taxonomy},{post_type}`, followed by
the `slug,name,description,post_ids` header. Hierarchical taxonomies add
`parent_slug` before `post_ids`. Context mismatches are rejected; legacy files
without context and legacy three-column input remain supported.
Downloads use `publishpress-taxonomies-{taxonomy}-{post-type}.csv`.
Missing or incompatible content assignments never abort otherwise valid terms.
The legacy skip argument controls whether those skips are warning-only or also
produce one aggregate import error; raw skipped IDs are not rendered.

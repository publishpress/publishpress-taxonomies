# Tag Transfer Regression Tests

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
preservation, strict atomic rejection, opt-in missing/unsupported skips, unique
skip counts, authorization failures, legacy CSV, idempotence, unchanged post
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
capabilities, strict/skip propagation through parse/preflight/import, permission
failures, post preservation, idempotence, all six real Manage Terms tabs and
their default/order, `render(false)` notice preservation, active `render(true)`
consumption, checkbox markup and escaped result details. SQL snapshots compare
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

WP 6.8.2 / PHP 7.4.33 local run: service **331 PASS, 0 FAIL**;
CSV mocks **184 PASS, 0 FAIL**; HTTP **55 PASS, 0 FAIL**.
Source and container hashes matched:

| File | SHA-256 |
| --- | --- |
| `inc/class-taxopress-tag-csv-service.php` | `f2aa362e63de1c118b2f24b888c71a84132c39e20e5663eceada27b83cbc6eac` |
| `inc/class-taxopress-tag-transfer.php` | `ba4941de19a7645c3d9b7548cab38f8e2e57a5dc04490d7167a4076bb67a825d` |
| `inc/class.admin.manage.php` | `49e76a287093f66f45be81cd62fb6d10fc0159b083213eeb26c362c653f12ff3` |

Service APIs: `parse_csv($stream, $skip_missing_posts=false)`,
`validate_assignments($rows, $check_permissions=false, $skip_missing_posts=false)`,
`import_rows($rows, $update_existing=false, $skip_missing_posts=false)`,
`write_csv($stream, $check_permissions=false)`.
Adapter: `TaxoPress_Tag_Transfer::render($show_results=true)`;
`admin_post_taxopress_import_tags` / `admin_post_taxopress_export_tags`.
Header: `slug,name,description,post_ids`; legacy three-column input is supported.

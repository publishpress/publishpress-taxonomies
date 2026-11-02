# PublishPress Taxonomies MCP

Taxonomies provides private actions through the WordPress Abilities API. Connect a compatible AI client through PublishPress Hub and the separately installed WordPress MCP Adapter. Enable only the actions the connection needs. Pro loads the shared integration from its bundled Free dependency.

## Permissions and editing

The connected WordPress account needs `admin_simple_tags` for site settings, and `simple_tags` for taxonomy, term and preset management. Post operations require native post and taxonomy permissions. Application-password restrictions and editor locks still apply; an Administrator role does not override a denied capability.

Start with `list-features` for native screen labels, enabled states, edition requirements and related actions, then read the relevant settings, configuration, taxonomy or post. Discovery returns field types, choices, defaults and `expected_hash`. Send that revision with a patch. Use `dry_run: true` to preview a write, then save with the same revision. After a conflict, read the current values before trying again. Omitted fields remain unchanged. JSON booleans and integer IDs must use their actual types, rather than strings such as `"false"` or `"123"`.

Configuration fields that use native two-choice selects expose string choices `"0"` and `"1"`; site checkboxes expose booleans. Native `true`/`false` storage is normalized to the discovered contract. Optional Auto Links character limits accept an integer or an empty string to clear the limit; numeric strings are rejected. Inline metabox labels use nonempty text up to 80 characters and keep both native stored representations synchronized. Lists remain lists, including empty lists. Secret values are absent from discovery and responses.

## Supported operations

All action names use the `publishpress-taxonomies/` prefix.

| Native area | MCP operations | Requirements |
| --- | --- | --- |
| Site settings, metabox access, Fast Update, inline tab labels and feature switches | `get-plugin-settings`, `update-plugin-settings` | Site settings permission; native dynamic choices; inline labels also require native label-edit permission |
| Terms Display, Terms for Current Post, Related Posts, Auto Links, Auto Terms | `list-presets`, `get-preset-fields`, `get-preset`, `save-preset`, `delete-preset` | Feature enabled and permitted taxonomy targets; Free configuration limits apply |
| Pro configuration copies | `copy-preset` | Pro active; permitted source configuration |
| Managed taxonomy definitions, labels and attached post types | `list-taxonomies`, `get-taxonomy`, `save-taxonomy`, `delete-taxonomy` | Management and native post-type permissions; deletion requires an unused managed definition |
| Managed taxonomy slug rename | `preview-rename-taxonomy`, `rename-taxonomy` | Taxonomy management enabled, site settings permission, native term/post permissions; new slug must be unused |
| Convert a managed taxonomy into an existing taxonomy | `preview-convert-taxonomy`, `convert-taxonomy` | Same permissions; compatible post types/hierarchies and no destination name/slug conflicts |
| Move selected terms between taxonomies | `preview-move-terms`, `move-terms` | Both taxonomies permitted; complete hierarchies; source default terms remain protected |
| Literal find/replace in term names | `preview-replace-term-text`, `replace-term-text` | Manage Terms and Terms enabled; every affected term and post editable |
| Rare-term cleanup | `preview-cleanup-terms`, `cleanup-terms` | Manage Terms and Terms enabled; native deletion permissions; default terms excluded |
| Match and change post assignments | `preview-match-terms`, `match-terms` | Manage Terms enabled; native assignment permissions and editor locks |
| Terms, names, slugs, descriptions, parents and custom Auto Links URLs | `list-terms`, `get-term`, `save-term`, `delete-term` | Native taxonomy and term permissions; custom links enabled for the taxonomy |
| Manage Terms merge | `preview-merge-terms`, `merge-terms` | Existing source/target terms; every affected post and child term editable |
| Posts search by term, title or post type | `list-posts` | Native Posts feature enabled; only readable, editable posts returned; page size up to 100 |
| Post term assignments and Mass Edit | `get-post-terms`, `set-post-terms`, `bulk-set-post-terms` | Native post/term assignment permissions and no editor lock |
| Per-post Auto Links and Auto Terms overrides | `set-post-features` | Native post permission; feature enabled |
| Local Auto Terms matching | `preview-autoterms`, `apply-autoterms` | `simple_tags`, current preview revision, permitted post and taxonomy targets |
| Auto Terms schedules | `get-autoterms-schedule`, `update-autoterms-schedule` | `simple_tags` to read; site settings permission to save, enabled configurations and permitted target post types; weekly in Free, hourly/daily also in Pro |
| Auto Terms logs | `list-autoterms-logs` | Summaries for permitted posts and taxonomies; saved configurations and provider diagnostics excluded |
| Native term CSV transfer | `export-terms`, `import-terms` | Native term/content permissions; export includes term names, slugs, descriptions and associated post IDs |
| Pro synonyms and linked terms | `get-term-relations`, `set-term-relations` | Pro features enabled for the taxonomy; native Linked Terms installation complete |
| Pro term copying | `preview-copy-term`, `copy-term` | Native Terms management enabled; exact native published-content scope editable; metadata is copied internally and never exported |
| Pro term ordering | `get-term-order`, `set-term-order` | Native ordering enabled; omitted terms retain their positions |

A preset uses one of these `kind` values: `tag_cloud`, `post_tags`, `related_posts`, `autolink`, `autoterm`. Use ID zero to create one and the revision from `get-preset-fields`. For a new term, use ID zero and the `new_expected_hash` from `list-terms`. New taxonomy definitions use `new_expected_hash` from `list-taxonomies`.

## Scope and limits

Posts search follows the native public post-type scope, including editable drafts. It returns identifiers, titles, types and statuses, without content bodies or private result totals. Permissions can leave a page partly or entirely empty; use explicit page numbers to continue browsing.

Post term assignments apply native Pro linked-term propagation and direction settings. Previews include the resulting taxonomy selections. Every changed taxonomy and term must be assignable before any selection is written; changes to current relationships or direction invalidate post revisions. Taxonomy discovery includes the actual native capability mappings.

Bulk assignment accepts up to 20 different posts and validates all entries before writing. Individual term changes require every affected post to be editable and are bounded to 500 affected posts. Term-order operations are bounded to 500 terms and accept up to 200 selected positions. Transfers are bounded to 200 terms, 500 associations and 1 MiB of CSV. Use native administration for larger operations.

Multi-object persistence is not a database transaction. A native failure can occur after earlier writes. Bulk responses identify incomplete results; other unconfirmed writes require reading current values before retrying. Do not assume an error means nothing was saved.

Local Auto Terms previews reuse native matching for the primary taxonomy without logs, counters, term creation or assignment writes. Pro additional-taxonomy targets belong to the native OpenAI batch flow. External-provider execution stays in native administration. Pro provider preferences can be configured, but API credentials, service endpoints and licenses stay in native administration. Scheduled native tasks retain their ordinary background behavior and can use already configured providers.

The ordinary `save-taxonomy` action keeps slugs immutable. Dedicated rename and conversion actions operate on managed definitions; core and third-party source definitions remain protected. Arbitrary metadata editing, log deletion and licensing remain in native administration. Display presets configure native shortcodes and blocks; MCP does not render arbitrary shortcodes or execute PHP, SQL or file paths.

## Conversion and Manage Terms

Start these operations with their `preview-` action. Apply the same inputs with the returned `expected_hash`; `dry_run: true` repeats validation without writing. Previews list affected terms and posts, protected defaults, destination visibility and native settings/preset references. The scope is bounded to 200 candidate terms and 500 affected posts. Larger operations require a narrower scope or native administration.

`rename-taxonomy` uses `source` and an unused `new_name`. It preserves term IDs, term-taxonomy IDs, hierarchy, metadata and post relationships while changing the managed definition. `convert-taxonomy` uses `source` and an existing `destination`, moves every source term and removes the managed source definition. Native preset and metabox taxonomy references follow the destination. Names or slugs that conflict with existing destination terms are rejected before writing. Hardcoded references in post content, theme/plugin code and third-party configuration are not rewritten.

`move-terms` uses `source`, `destination` and explicit integer `term_ids`. It retains the source definition. Move complete hierarchies together; a child cannot leave its parent behind, and a parent cannot leave children behind. Default terms cannot leave an active source taxonomy. A destination must support every affected post type and preserve any hierarchy. With Pro active, linked-term identities and manual ordering follow the preserved IDs. Hidden-term state is refreshed for the affected taxonomies using native rules.

`replace-term-text` takes `taxonomy`, `find` and `replace`. Matching is literal and case-sensitive. It changes term names while preserving IDs, slugs, descriptions, metadata, hierarchy and assignments. It does not change post bodies. Empty, overlong or conflicting resulting names reject the plan.

`match-terms` takes `taxonomy`, `post_type`, `match_ids`, `target_ids` and a `mode` of `add`, `remove` or `replace`. All IDs refer to existing terms. Nonempty match IDs select posts carrying any of those terms; the selected post type still limits the scope. Empty match IDs select the native published/inherited content scope. Replace removes the matching selections and adds the targets; unrelated selections remain. Previews include native Pro linked-term propagation.

`cleanup-terms` takes `taxonomy` and integer `below_count` from 1 to 100. It uses the native stored usage threshold and excludes default terms. Every affected post and child term must be permitted and unlocked. Usage, relationships and revisions are rechecked before applying. Draft-only assignments can exist even when the stored usage count is zero; they are included in the affected-post preview.

These workflows are not transactions. Native hooks or persistence failures can leave earlier writes saved. Batch responses identify completed entries; unconfirmed conversions require reading current definitions, terms and references before retrying. Creating a destination definition or moving terms can succeed before a later reference update fails.

## Example requests

- "Show the Taxonomies settings and enable the Auto Links feature. Preview first."
- "Create a Terms Display for the topic taxonomy, ordered by name."
- "Show the fields available for my Auto Terms configuration."
- "Create a hierarchical Topics taxonomy for posts and expose it in REST."
- "Find the posts assigned to Review."
- "Add Review as a topic, then assign it to the post called Draft plan."
- "Disable Auto Links for this post using the native post setting."
- "Preview local Auto Terms for this post, then apply the current preview."
- "Preview merging these two topic terms into Review."
- "Export this taxonomy's native term CSV with its post assignments."
- "In Pro, add Reviewing as a synonym of Review and link it to Related."
- "In Pro, preview copying Review with its native metadata and assignments."
- "In Pro, put Related before Review in the native term order."
- "Preview renaming the Topics taxonomy slug to subjects, keeping its terms and settings."
- "Preview moving this complete term hierarchy into Subjects."
- "Replace Old with New in topic names. Show the affected terms first."
- "Find posts tagged Review and add Ready, preserving their other terms."
- "Preview deleting topics used fewer than two times, keeping the default term."

Resolve names to the exact discovered identifiers before writing. Ask for clarification when names match several posts, terms or configurations.

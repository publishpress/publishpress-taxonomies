# Term author regression checks

Run `php tests/term-authors/run.php` from the free plugin checkout. These standalone checks exercise the real term author service, taxonomy setting readers, and Terms column selection with in-memory WordPress fixtures. They do not connect to a site or write to a database.

Coverage includes global defaults and individual overrides, current-user attribution, background creation, explicit authors and No author, preserving unrelated metadata changes, nonces, permissions, invalid and cross-site users, taxonomy moves, display escaping, deleted users, column visibility, author filter validation, query scoping, and native hierarchical pagination.

## Local WordPress integration checks

`integration.php` exercises real WordPress queries, author saves, native list rendering, filtered counts, search, custom taxonomies, manual ordering, and cache invalidation. Use a local development site with an administrator. Run with plugins and themes skipped; external HTTP and email are blocked in the test process. The test creates temporary taxonomies, terms, and an author account and removes them in `finally`. Existing settings are overridden only in memory.

For example, in PowerShell from the plugin root:

```powershell
$env:TAXOPRESS_TERM_AUTHOR_TESTS = '1'
php vendor/wp-cli/wp-cli/php/boot-fs.php --path=C:/path/to/local/wordpress --skip-plugins --skip-themes eval-file tests/term-authors/integration.php
Remove-Item Env:TAXOPRESS_TERM_AUTHOR_TESTS
```

On a disposable WordPress site, also verify:

1. Enable **Settings → Term Authors → Enable Term Authors**. Under **Taxonomies → Edit taxonomy → Admin Area → Term Authors**, confirm that **Use global setting** follows that choice, **Disabled** turns authors off for one taxonomy, and **Enabled** keeps them on when the global setting is off. Save and reopen each choice.
2. Add a term with its default author, another with a different author, and another with No author. Confirm the native Author column after each add.
3. Edit and Quick Edit those terms on both the native taxonomy screen and the PublishPress Taxonomies Terms screen. Reopen Quick Edit to confirm persisted selections, including after the Ajax row replacement.
4. On Terms, switch the Quick Edit destination taxonomy between enabled and disabled taxonomies. The author field should follow the destination. Save into an enabled destination and confirm the author; disabled destinations should retain existing metadata without updating it.
5. Hide the Author column through Screen Options and confirm Quick Edit still loads the stored author. Switch between rows with different authors and unassigned terms.
6. Run existing Copy and Set parent bulk actions. Existing terms should retain their authors; newly copied terms should be attributed to the user performing the copy.
7. Create a term in an enabled taxonomy through a background process with no current user. It should remain unassigned.
8. Turn authors off and on. Existing assignments should reappear. Existing author permissions and ordinary taxonomy workflows should be unchanged.
9. Filter by a user and by No author on both Terms screens. Combine the filter with search, taxonomy/post type filters, sorting, pagination, and manual term ordering. Confirm that child terms appear even when their parent has another author, and that parent selectors still contain all eligible terms.

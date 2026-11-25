# Terms bulk parent action

Run the standalone WordPress-mocked regression checks from the plugin root:

```sh
php tests/terms-parent/run.php
node --check assets/js/admin.js
```

The harness checks hierarchical taxonomy gating, taxonomy and per-term edit
permissions, nonce rejection, same-taxonomy parent and selection validation,
self/descendant conflicts skipped individually, top-level moves, descendants,
duplicate selections, unchanged parents, and persistence failure counts. It also
checks detailed mixed-result notices, skipped-only notices, and escaped names.
Every notice variant is checked for the `taxopress-notice` class required to
survive TaxoPress's `.notice:not(.taxopress-notice)` hiding rule.

Manual UI checks on a disposable WordPress site:

1. Open TaxoPress > Terms. Confirm Set parent is absent for all-taxonomy and Tags or any non-hierarchical taxonomy views. 
Filter to Categories or another hierarchical taxonomy and confirm it appears alongside Delete and Copy.
2. Select several terms, choose Set parent, search for the destination parent,
   and Apply. Check the success count and the resulting hierarchy.
3. Repeat from the bottom bulk-action bar. Switching bars must reset the other
   action; only the active parent selector should be submitted.
4. Choose No parent (top level). Confirm child terms remain attached to each
   moved term and names, slugs, descriptions, metadata, and post assignments stay
   intact.
5. Select multiple terms e.g Holiday, Fat, wellness, and School and choose one of them as the parent term, e.g Holiday.
   Confirm the other terms moved under Holiday and the warning names every moved
   term and explains Holiday's self-parent skip. Test Holiday alone as well.
   If a selected term is an ancestor of the chosen parent, confirm only that
   conflicting term is skipped and the notice explains the circular hierarchy.
6. Switch back to Copy/Delete and verify hidden parent controls do not block
   submission. Check keyboard access and the searchable parent selector.

# Posts-screen Auto Terms checks

Run from the repository root:

```sh
php tests/posts-autoterms/run.php
node --check assets/js/posts-autoterms.js
```

The standalone PHP harness mocks WordPress and the term-processing engine. It
checks configuration opt-in, post types/statuses, capabilities, nonce validation,
duplicate-run locks, error cleanup, scoped assets, and credential-free browser
configuration. It does not exercise a live WordPress installation or AI provider.

Manual Free/Pro checks:

1. Confirm no button appears for existing/default-off configurations.
2. In Auto Terms > edit configuration > When to Use > Posts, enable
   **Run Auto Terms button** and save. Configure matching post types/statuses.
3. On the Posts list, confirm **Run Auto Terms** appears next to Add Post.
4. Click without selecting rows: no requests should run.
5. Select posts and run; only selected posts should be passed to the configuration.
6. With multiple opted-in configurations, select one using the configuration menu.
7. Verify saved post replacement/matching rules, per-post exclusions, provider
   failures, and resulting terms/logs.
8. Disable the toggle and save: the button should disappear (unless another
   configuration is enabled). Open stale tabs must not bypass the disabled setting.

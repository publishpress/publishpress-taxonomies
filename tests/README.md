# OpenAI existing-term classification regression tests

Run from the repository root:

```sh
php tests/openai-term-ids.php modules/taxopress-ai/classes/TaxoPressAiApi.php
```

The standalone harness runs the production provider method with mocked WordPress
HTTP and metadata functions. It checks catalog IDs, compound names and punctuation,
invalid responses, cache invalidation, preview diagnostics, and capability checks.
It makes no live API calls and does not test live WordPress term assignment.

The existing-term ID path activates when the configured OpenAI prompt includes
`{site_terms}`. Prompts without it retain the existing new-term extraction path.

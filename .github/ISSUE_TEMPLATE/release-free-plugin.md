---
name: Release Free Plugin (Team Only)
about: Step-by-step checklist for releasing the Free plugin version.
title: Release PublishPress Taxonomies v[VERSION]
labels: release
assignees: ''
type: task
---

Before releasing the Free plugin, ensure every step in this checklist is completed.

> **Release principle:** Prioritize quality over velocity for all standard releases.
> For urgent/security releases, maintain essential quality and security checks while balancing delivery speed responsibly.

> **Versioning:** Follow [Semantic Versioning](http://semver.org/) for release and prerelease numbers. Use full version strings with three numeric segments—**MAJOR.MINOR.PATCH** (e.g. `2.14.1`), not shortened forms. Optional prerelease identifiers follow that base (e.g. `-beta.N` or `-rc.N`).

### Pre-Release Preparation Checklist

**GitHub Milestone**

- [ ] Verify the GitHub milestone for `<version>` exists and all associated issues and pull requests are closed — resolve or defer any open items before proceeding

**Branch Setup**

- [ ] Create a new branch named `release-<version>` from the latest `development` branch
- [ ] Integrate all required hotfixes and new features into the release branch (via direct merge or pull request), ensuring all code has undergone code review (self-review or review by another team member)

**Dependencies**

- [ ] Run `composer update --no-dev --dry-run` and review the output for dependencies needing updates
- [ ] Update any required dependencies with `composer update <vendor/package>:"<version-constraint>"`
- [ ] List all dependency changes (with versions) in `CHANGELOG.md`
- [ ] Review and resolve open Dependabot pull requests and alerts

**Build Assets**

- [ ] Build JS/CSS files by running `composer build:js` (if applicable)

**Code Quality**

- [ ] Run `composer check:all` to verify the codebase has no warnings or errors
- [ ] Run `composer test Unit` to execute Unit tests and confirm all tests pass (if applicable)
- [ ] Run `composer test Integration` to execute Integration tests and confirm all tests pass (if applicable)

**RC Package & Pre-Release Team Review**

> **Repeat anytime:** You may repeat this entire block (build → share → collect feedback → fix → rebuild) as often as needed and **at any stage** of the release process—not only here—whenever the team needs an installable package for testing.
>
> **Prerelease naming:** Use **beta** builds when sharing packages during active development or early testing (e.g. `<version>-beta.1`, `<version>-beta.2`). Use **release candidate** (RC) builds for final acceptance testing before localization and the official release (e.g. `<version>-rc.1`, `<version>-rc.2`). Increment the prerelease number for each new package you share.
>
> ⚠️ This step must be completed **before** starting the Localization phase. Text changes from team feedback directly affect translatable strings, so translations should only be updated after all copy is finalized.

- [ ] Build a package with `composer build` and share it with the team via the `#testing` Slack channel — use a **beta** or **rc** suffix per [Semantic Versioning](http://semver.org/) (e.g. `2.14.1-beta.1` during development, `2.14.1-rc.1` for final pre-release testing), incrementing the number for each new build sent to the team
- [ ] Collect and address feedback from the team (functional issues, copy/text changes, UI wording, etc.)
- [ ] Apply any required fixes or text changes to the release branch — revisit earlier steps (Branch Setup, Dependencies, Build Assets, Code Quality) as needed before re-building the package
- [ ] Re-build and re-share the package (incrementing the beta or rc number) if any changes were made
- [ ] Confirm with the team that the **rc** build is approved and no further text changes are expected before proceeding to Localization (betas are for earlier cycles; localization should follow the final **rc** sign-off)

**Localization**

> **Localization (only if needed):** If no translatable strings or text have changed in this release, you can skip this section.

- [ ] Run `composer translate:pot` to update the .pot file
- [ ] Commit the updated .pot file if changes are detected
- [ ] Run `composer translate` to update AI-assisted translations
- [ ] Commit all translation/i18n updates together
- [ ] Create translation review issues:
    - [ ] For ES, FR, and IT: Open a GitHub issue titled `Translate and Review ES, FR, and IT for Release v<version>` and assign it to `@wocmultimedia`.
    - [ ] For PT-BR: Open a GitHub issue titled `Translate and Review PT-BR for Release v<version>` and assign it to `@ValdemirMaran`.
      **Do not** append any ZIP package, .pot file, or translation files in the issue — just provide the issue and description.
- [ ] Follow up on translation issues (Expect this may take 1–2 days per language. Quality is better than velocity for regular releases. For urgent/security releases, proceed without updated translations when needed, but communicate clearly with the translator and team. Do **not** disclose sensitive vulnerability details until the security release is published.):
    - [ ] Wait for `@wocmultimedia` (ES, FR, IT) to review and confirm/close the translation issue(s).
    - [ ] Wait for `@ValdemirMaran` (PT-BR) to review and confirm/close the translation issue(s).
- [ ] After the translator responds, run `composer translate:download` to fetch the latest translations
  - Skip this step for urgent or security releases
- [ ] If you make any manual edits to language files, run `composer translate:upload` to synchronize your changes with the translation system before proceeding.
- [ ] Run `composer translate:compile` to generate language files (MO, JSON, PHP)
- [ ] Add a summary of translation changes to `CHANGELOG.md`
- [ ] Commit compiled translation files and `CHANGELOG.md` updates to the release branch

**Version & Documentation**

- [ ] Review `CHANGELOG.md` and refine user-facing descriptions as needed
- [ ] Verify the release date in `CHANGELOG.md` is correct
- [ ] Run `composer set:version <version>` to update plugin version numbers in all required files
- [ ] Commit the version and changelog updates to the release branch

**Build & Test**

- [ ] Build the release package with `composer build` (generates the package in `./dist`)
- [ ] Review the `composer build` output and confirm the package file list is correct
  - Ensure configuration and development-only files are excluded from the final package
  - If needed, update `.rsync-filters-pre-build`, `.rsync-filters-post-build`, `.distignore`, and `.gitattributes`
  - Shared pack excludes live in `vendor/puublishpress/dev-workspace/.rsync-filters-pre-build.default` and related files; use `composer run pack:dir:with-debug` only when the package must include source maps or JSX sources
- [ ] Share the generated package with the team for testing via the `#testing` Slack channel

### Release & Deployment

- [ ] Open a PR and merge `release-<version>` into `master`
- [ ] Merge `master` back into `development`
- [ ] Create the GitHub release using a tag from `master`
  - This triggers the automatic SVN deployment

### Post-Release Validation

- [ ] Monitor [GitHub Actions](https://github.com/publishpress/publishpress-taxonomies/actions) and confirm all release and deployment workflows complete successfully
- [ ] Verify the [WordPress.org plugin page](https://wordpress.org/plugins/simple-tags/) shows the new version and updated release information
- [ ] Test updating to the new version on a staging site and run a basic smoke test of core functionality
- [ ] Close the GitHub milestone for `<version>`

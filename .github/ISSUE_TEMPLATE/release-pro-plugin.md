---
name: Release the Pro version (team only)
about: Describes default checklist for releasing the Pro plugin;
title: Release TaxoPress Pro v[VERSION]
labels: release
assignees: ''

---

To release the Pro plugin please make sure to check all the checkboxes below.

### Pre-release Checklist

- [ ] Create the release branch as `release-<version>` based on the development branch
- [ ] Make sure to directly merge or use Pull Requests to merge hotfixes or features branches into the release branch
- [ ] Start a dev-workspace session.
- [ ] Update the `composer.json` file changing the version constraint to the Free plugin to use the most recent stable release tag
- [ ] Run `composer update` and check if there is any relevant update. Check if you need to lock the current version for any dependency. The `--no-dev` argument is optional here, since the build script will make sure to run the build with that argument.
- [ ] Update the changelog - make sure all the changes are there with a user-friendly description and that the release date is correct
- [ ] Update the version number to the next stable version.
- [ ] Commit the changes to the release branch
- [ ] Build the zip package with `composer build`, creating a new package in the `./dist` directory.
- [ ] Send to the team for testing

### Release Checklist

- [ ] Create a Pull Request and merge the release branch it into the `master` branch
- [ ] Merge the `master` branch into the `development` branch
- [ ] Create the Github release (make sure it is based on the `master` branch and correct tag)
- [ ] Update EDD registry and upload the new package
- [ ] Make the final test updating the plugin in a staging site

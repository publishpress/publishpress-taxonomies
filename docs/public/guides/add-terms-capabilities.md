---
title: "Who Can Create and Add Terms in Taxonomies?"
order: 4
doc_category: publishpress-taxonomies
section: Capabilities
---

# Who Can Create and Add Terms in Taxonomies?

It is possible to control who can add terms to posts in Taxonomies. This plugin uses the same capabilities as the WordPress core. So if a user can add terms in WordPress, they can add terms in Taxonomies.

By default, anyone who can edit posts can also do this:

- Add Categories to a post, but not create new Categories.
- Add Tags to a post and create new Tags.

You can use a plugin such as [PublishPress Capabilities](https://wordpress.org/plugins/capability-manager-enhanced/) to control these settings in more detail. Go to “Capabilities” then “Capabilities” in the admin menu. Click the “Taxonomies” tab.

On the this screen you will see the “Manage” column. This will allow users access to main screen for each taxonomy. So if you check the box for “Categories”, users in this role will have the ability to edit, add, delete, and manage all your Categories.

![Manage Categories](https://docs-media.publishpress.com/publishpress-taxonomies/capabilities-terms-df0871ebaf.webp)

*capabilities terms*

It is possible to get more detailed control over who can edit your taxonomies. In the sidebar, look for the “Detailed Taxonomy Capabilities” area. Check the boxes for your taxonomies and in the main area you will now see “Edit”, “Assign” and “Delete” boxes.

![Detailed Taxonomy Capabilities area](https://docs-media.publishpress.com/publishpress-taxonomies/unique-capabilities-4ec5750999.webp)

*unique capabilities*

One change you can make here is to prevent a user from assigning any terms to your posts. In the screenshot, you are blocking users in the Author role from managing, editing, assigning or deleting terms.

![Capabilities control Author access](https://docs-media.publishpress.com/publishpress-taxonomies/remove-term-access-57a392d09a.webp)

*remove term access*

If you make the changes shown in the screenshot above, users in that role will have no access to the normal Tags and Categories metaboxes. In the screenshot below, you can see that those boxes are missing.

![Tags and Categories control](https://docs-media.publishpress.com/publishpress-taxonomies/missing-terms-boxes-7223200041.webp)

*missing terms boxes*

These changes will apply to [PublishPress Taxonomies](https://publishpress.com/links/taxonomies) also. If the Taxonomies metabox is available to users in this role, they will see this message on the “Create Terms” tab: “You do not have the required capabilities to manage any of this post attached taxonomies”

![No capabilities permissions](https://taxopress.com/wp-content/uploads/2025/04/no-capabilities-permissions-1024x252.png)

*no capabilities permissions*

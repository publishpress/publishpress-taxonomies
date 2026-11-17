---
title: "Capabilities / Permissions in Taxonomies"
order: 44
doc_category: publishpress-taxonomies
section: Capabilities
---

# Capabilities / Permissions in Taxonomies

Table of Contents

- [Capabilities for the Taxonomies Metabox](#0-toc-title)
- [Capabilities for the Taxonomies Admin Area](#1-toc-title)

## Capabilities for the Taxonomies Metabox [#](#0-toc-title)

If you want to control who has access to the Taxonomies metabox underneath each post, you can do that with the [manage_categories](https://publishpress.com/links/manage_categories) capability. We recommend using the [PublishPress Capabilities](https://wordpress.org/plugins/capability-manager-enhanced/) plugin to assign this permissions.

- Go to the “Capabilities” screen.
- Choose the user role you want to edit in the top-left corner.
- In the “Taxomomies” area, you can see the “[manage_categories](https://publishpress.com/links/manage_categories)” option.

![manage_categories in PublishPress Capabilities](https://docs-media.publishpress.com/publishpress-taxonomies/manage-categories-2253fa5423.webp)

## Capabilities for the Taxonomies Admin Area [#](#1-toc-title)

There are two permissions that can give users access to the Taxonomies admin area:

- `simple_tags`: this will give access to the “Settings” screen.
- `admin_simple_tags`: this will give access to the screens such as “Taxonomies”, “Related Posts”, “Mass Edit Terms”, “Manage Terms”, and more.

You can use these to customize user access to the Taxonomies plugin screens with the [PublishPress Capabilities](https://publishpress.com/links/capabilities) plugin.

- Go to the “Capabilities” screen.
- Choose the user role you want to edit in the top-left corner.
- In the “Taxonomies” area, you can see the two Taxonomies permissions:

![TaxoPress in PublishPress Capabiltiies](https://docs-media.publishpress.com/publishpress-taxonomies/taxopress-capabilities-d0ad32e9ff.webp)

If you give both these permissions to a user in [the Subscriber role](https://publishpress.com/knowledge-base/subscriber/), this next screenshot shows what they will see after logging in.

![TaxoPress admin area for Subscribers](https://docs-media.publishpress.com/publishpress-taxonomies/taxopress-settings-subscriber-ace49b1943.webp)

These permissions are automatically added to your site by the `/inc/class.admin.php` file. These permissions are automatically removed when you uninstall the plugin. This is done by the `uninstall.php` file.

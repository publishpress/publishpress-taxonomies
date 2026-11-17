---
title: "Allow Auto Links to Skip Content Areas"
order: 11
doc_category: publishpress-taxonomies
section: Auto Links
---

# Allow Auto Links to Skip Content Areas

Table of Contents

- [Skip Content Areas in Taxonomies Free](#0-toc-title)
- [Skip Content Areas in Taxonomies Pro](#1-toc-title)

When using the Auto Links feature in [PublishPress Taxonomies](https://publishpress.com/links/taxonomies), you may want to avoid adding links to some areas of your posts. For example, some users don’t want to automatically add links to headings inside their content.

## Skip Content Areas in Taxonomies Free [#](#0-toc-title)

In the Free version of [PublishPress Taxonomies](https://publishpress.com/links/taxonomies), the Auto Links feature allows you to skip some specified elements such as headings, “script”, “style”, “pre”, and “code”. Here’s how to stop Auto Links from adding links inside these elements:

- Go to Taxonomies > Auto Links.
- Edit an Auto Links entry and click the “Exceptions” tab.
- Check the boxes for any elements you want to avoid.

![Auto Links elements to skip](https://docs-media.publishpress.com/publishpress-taxonomies/auto-link-elements-2200d28d9c.webp)

It is also possible to skip certain parts of your content using the HTML markup:

- Inspect the element on the page. Find the name of the wrapper for your element.
- Add the wrapper name into this field: “Prevent Auto Links inside classes or IDs”.

![](https://docs-media.publishpress.com/publishpress-taxonomies/prevent-classes-ids-e21f79765f.webp)

## Skip Content Areas in Taxonomies Pro [#](#1-toc-title)

This feature requires the Pro version of the Taxonomies plugin

If you are using Taxonomies Pro, you are also able to add your own elements to skip. Look for the “Element tag” box in the screenshot below. Enter the name of the HTML element, without the brackets. For example, enter “blockquote” and click the “Add” button.

![kip Content Areas](https://docs-media.publishpress.com/publishpress-taxonomies/skip-elements-eaf7795ddd.webp)

*skip elements*

You will now see your new element added to the list. You can check the box and the Auto Links feature will stop adding links to that element.

![Skipped elements in Auto Links](https://docs-media.publishpress.com/publishpress-taxonomies/skipped-element-c8871832ad.webp)

*skipped element*

Finally, Taxonomies Pro has the option to skip specific shortcodes and blocks. Terms inside these shortcodes and blocks will not have Auto Links applied.

![](https://docs-media.publishpress.com/publishpress-taxonomies/exclude-blocks-5372e32600.webp)

*exclude blocks*

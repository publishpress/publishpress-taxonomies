---
title: "Auto Terms: Existing"
order: 1
doc_category: publishpress-taxonomies
---

# Auto Terms: Existing

Table of Contents

- [How to use Auto Terms with existing content](#0-toc-title)
- [Safeguards for Large Sites](#1-toc-title)

Taxonomies Pro can automatically add terms to existing content. This “Existing Content” feature allows you to add terms to content that was previously created without any current terms.

## How to use Auto Terms with existing content [#](#0-toc-title)

- Go to Taxonomies > Auto Terms Existing.
- In the top left corner you can choose an [Auto Terms](https://publishpress.com/knowledge-base/introduction-to-auto-terms/) setting. This is where you can choose the post types to scan, and the taxonomies to use. For example, you can choose to can only Posts and add only Categories. Or you can choose to scan WooCommerce Products and add Product Tags.

![ existing content feature](https://docs-media.publishpress.com/publishpress-taxonomies/add-exisiting-content-7f4fec4070.webp)

*add exisiting content*

- Click the “Add Auto Terms to existing content” button.
- You will now see a progress bar showing the terms being added.

![Using the Existing Content feature](https://docs-media.publishpress.com/publishpress-taxonomies/existing-content-result-40976ef1d2.webp)

*existing content result*

If you realize you made a mistake adding any terms, you can use other Taxonomies features such as [Remove Terms](https://publishpress.com/knowledge-base/remove-terms-tab/) or [Delete Terms](https://publishpress.com/knowledge-base/remove-rarely-used-terms/) to clean up the terms.

## Safeguards for Large Sites [#](#1-toc-title)

If you have a site with many posts, or if you are not on powerful server, you may experience timeouts when scanning content. To use this Existing Content feature, we provide some safeguards to help you analyze a lot of content successfully. These will prevent timeouts if you’re trying to process a lot of content or using an underpowered server.

As you saw in the images above, the “Existing Content” screen has the following four safeguards:

- **Exclude previously analyzed content**: Once a post has been analyzed it will not be analyzed again. This stops the “Existing Content” from repeatedly checking the same posts. *Check this box to help avoid timeouts*.
- **Limit per batches**: This enables you to add Auto Terms to content in batches. *Set this to a lower number to avoid timeouts*.
- **Batches wait time**: This is the wait time (in seconds) between processing batches of Auto Terms. *Set this to a higher number to avoid timeouts*.
- **Limit Auto Terms, based on published date**: This setting allows you to add Auto Terms only to recent content. *Set this to a limited time range to avoid timeouts*.

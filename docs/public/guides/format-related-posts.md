---
title: "Change the Format of Related Posts"
order: 19
doc_category: publishpress-taxonomies
---

# Change the Format of Related Posts

The Related Posts feature in Taxonomies shows posts in a list format. You can modify the information shown for each post link. You can see the default format in the screenshot below:

![Related Posts example](https://docs-media.publishpress.com/publishpress-taxonomies/related-posts-five-275eff9d9c.webp)

*related posts five*

This format is controlled by the “Term link format” setting in Taxonomies > Related Posts > Advanced:

![Term link format in TaxoPress](https://docs-media.publishpress.com/publishpress-taxonomies/term-link-format-1354a76713.webp)

*term link format*

For example, you can change this display by adding “LINK” to the “Term link format, as in this screenshot below:

![Term link format change](https://docs-media.publishpress.com/publishpress-taxonomies/term-link-format-changed-f1b7a9a1a2.webp)

*term link format changed*

This next screenshot shows the result of those changes:

![Related Posts link change](https://docs-media.publishpress.com/publishpress-taxonomies/related-posts-five-changed-2d4a84898d.webp)

*related posts five changed*

Here is the code that Taxonomies provides by default:

```
<a href="%post_permalink%" title="%post_title% (%post_date%)">%post_title%</a>
```

Here are the items you can see inside that code:

- `%post_permalink%` The URL of the post
- `%post_title%` The title of the post
- `%post_date% `The date of the post (this shows inside a tooltip)

Here are other items you can choose to include:

- `%post_tagcount%` The number of tags used by both posts
- `%post_comment%` The number of comments on the post
- `%post_id%` The ID of the post
- `%post_relatedtags%` A list of tags used by both the current post and the related posts
- `%post_excerpt%` The post excerpt
- `%post_content%` The post content
- `%post_thumb_url%` The URL of the post’s featured image.
- `%post_category%` The categories attached to the posts.
- `%post_relatedtags%` The tags attached to the posts.
- `%tag_description%` The description of the term

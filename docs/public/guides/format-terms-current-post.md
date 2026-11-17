---
title: "Change the Format of Terms for Current Post"
order: 21
doc_category: publishpress-taxonomies
---

# Change the Format of Terms for Current Post

The “Terms for Current Post” feature in Taxonomies shows all the terms for the post you’re viewing. You can see an example in the screenshot below:

![Terms for Current Post example](https://docs-media.publishpress.com/publishpress-taxonomies/related-posts-2-d51a1b68bc.webp)

This format is controlled by a setting in Taxonomies > Terms for Current Post:

![Terms for Current Post settings](https://docs-media.publishpress.com/publishpress-taxonomies/tcp-advanced-216da5cde9.webp)

Here is the code that Taxonomies provides by default:

```
<a href="%tag_link%" title="%tag_name%" %tag_rel%>%tag_name%</a>
```

Here are the items you can see inside that code:

- `%tag_link%` – The URL of the tag
- `%tag_name%` – The name of the tag
- `%tag_rel% `– This provides rel tag markup (it creates `rel="tag"`)

Here are more items you can add to change the format:

- `%tag_feed%` – Replaced by the RSS tag link
- `%tag_id%` – Replaced by the tag ID
- `%tag_description%` – The description of the term
- `%tag_name_attribute%` – Replaced by the tag’s name, formatted for attribute HTML

You can also add HTML elements to this formatting. For example, this image below shows the HTML required to make a list with strong text:

![Terms for Current Post link format](https://docs-media.publishpress.com/publishpress-taxonomies/tcp-list-23b7c279db.webp)

The settings you saw in the image above will produce the following output:

![Terms for Current Post custom output](https://docs-media.publishpress.com/publishpress-taxonomies/terms-html-4f5dc8bf95.webp)

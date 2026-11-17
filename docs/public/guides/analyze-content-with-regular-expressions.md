---
title: "Use Regular Expressions to Analyze Content"
order: 6
doc_category: publishpress-taxonomies
section: Auto Terms
---

# Use Regular Expressions to Analyze Content

Taxonomies Pro allows you to use Regular Expressions to change how Auto Terms analyzes your posts.

By default, Taxonomies runs this code to determine if a term exists in content:

> `if(strpos($content, ' '.$term.' ') !== FALSE)` {//add $term}

That code will change slightly if the “Whole words” setting is unchecked in the “Options” tab.

![The "Whole words" setting](https://docs-media.publishpress.com/publishpress-taxonomies/01-the-whole-words-setting-f762b18738.webp)

If that box is unchecked, Taxonomies will add terms even if they are not part of a whole word. In that situation, Taxonomies uses this code:

> `stristr( $content, $term )`

You can add your own Regular Expressions under the “Advanced” tab of Taxonomies Pro.

An example regular expression `/\b({term})\b/i` will match the whole word. Taxonomies will replace `{term}` with the term name before the regex action.

![](https://docs-media.publishpress.com/publishpress-taxonomies/regex-1024x375-1-2950dc6356.webp)

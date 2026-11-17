---
title: "Change the Format of Terms Display"
order: 20
doc_category: publishpress-taxonomies
section: Terms Display
---

# Change the Format of Terms Display

The “Terms Display” feature allows you to show all the terms in one taxonomy.

To manage a format of a terms display, go to Taxonomies > Terms Display > Display Format:

The format you can see in the following image is controlled by a setting in Taxonomies > Terms Display > Display Format:

![Term link format for Terms Display](https://docs-media.publishpress.com/publishpress-taxonomies/terms-display-format-881a90ed15.webp)

*terms display format*

Here is the code that the Display Format area provides for you by default:

```
<a href="%tag_link%" id="tag-link-%tag_id%" class="st-tags t%tag_scale%" title="%tag_count% topics" %tag_rel% style="%tag_size% %tag_color%">%tag_name%</a>
```

Here are the items you can use inside that code:

- `%tag_link%` – The URL of the term
- `%tag_description%` – The term description
- `%tag_id%` – The ID of the term
- `t%tag_scale%` – The weighted size of the term in the display
- `%tag_count%` – The number of times the term is used
- `%tag_rel%` – This provides rel tag markup (it creates rel=”tag”)
- `%tag_size%` – The font size for the term
- `%tag_color%` – The font color for the term
- `%tag_name%` – The name of the term
- `%tag_name_attribute%` – The name of the term with any HTML stripped out

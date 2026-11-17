---
title: "Logs for Auto Terms"
order: 33
doc_category: publishpress-taxonomies
---

# Logs for Auto Terms

Table of Contents

- [Managing the Auto Terms Logs](#0-toc-title)
- [Technical Details for Logs](#1-toc-title)

A log is available for the Auto Terms feature. This is useful because you can set the Auto Terms feature to run on a schedule. So it can be very useful to know what happened on your site when you’ve set up an automatic process.

On all of the Auto Terms screens, you will see the “Logs” button, as shown in the image below:

![](https://docs-media.publishpress.com/publishpress-taxonomies/01-the-logs-button-1f04e3c27a.webp)

This “Logs” area has one entry for every time a post is processed. In the image below, you can see that Auto Terms ran for five posts. In this example, Auto Terms added terms to four of the posts. Here’s what each column means in the Logs table.

- **Post**: This is the post that was analyzed.
- **Post type**: This is the post type that Auto Terms is analyzing.
- **Taxonomy**: This is the taxonomy that Auto Terms is using to search for matches.
- **Source**: This can be “Manual post update,” “Existing content”, or “Schedule”.
- **Terms added**: These are the terms that were successfully added to the post.
- **Status message**: This explains the outcome of the process in more detail.
- **Date**: This shows the date and time that the post was analyzed.
- **Settings**: This link goes to the settings that are controlling this process.

![TaxoPress log entries](https://docs-media.publishpress.com/publishpress-taxonomies/tp-log-entries-demo-1024x478-1-25c5da81b9.webp)

## Managing the Auto Terms Logs [#](#0-toc-title)

If you have a busy site, the Logs area may become busy. We have provided a few tools to help you manage the logs:

- **Disable Logs**: This button will completely disable the Logs feature.
- **Delete All Logs**: This will remove all the logs stored on your site.
- **Limit the number of logs**: This will set a maximum number of logs to store. Only the most recent logs will be kept. So if the limit is set to “1000”, Taxonomies will keep the 1000 most recent log entries and delete anything older.

![](https://docs-media.publishpress.com/publishpress-taxonomies/logs-control-1024x173-1-3ab40e190b.webp)

## Technical Details for Logs [#](#1-toc-title)

The [PublishPress Taxonomies](https://publishpress.com/links/taxonomies) logs are stored as a private post type called `taxopress_logs` in the `_posts` table.

The details of each log are stored in the `_postmeta` table. This image shows the details stored for each log:

![](https://docs-media.publishpress.com/publishpress-taxonomies/term-meta-1024x413-2-2624498da0.webp)

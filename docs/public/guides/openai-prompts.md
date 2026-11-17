---
title: "OpenAI Prompts (Pro)"
order: 40
doc_category: publishpress-taxonomies
section: Integration
---

# OpenAI Prompts (Pro)

Table of Contents

- [Sample Prompts](#0-toc-title)
- [Ordering the Term Results](#1-toc-title)
- [Choosing a Language for Term Results](#2-toc-title)
- [Exclude Words from Term Results](#3-toc-title)
- [Search Only Existing Terms](#4-toc-title)
- [Choose a Case for Term Results](#5-toc-title)

This feature requires the Pro version of the Taxonomies plugin

TaxoPress Pro integrates with OpenAI to scan your content and suggest existing terms. This feature has the ability to customize the prompts used to return results from OpenAI. You can see this in the “OpenAI Prompt” setting:

![OpenAI settings](https://docs-media.publishpress.com/publishpress-taxonomies/openai-terms-61cab50d20.webp)

*openai terms*

Here are some suggestions for modifying the OpenAI prompt. These are only ideas and OpenAI will accept a wide variety of prompts so feel free to unleash your imagination and test your own use-cases. It’s worth reading [the OpenAI documentation on creating prompts](https://help.openai.com/en/articles/6654000-best-practices-for-prompt-engineering-with-the-openai-api).

## Sample Prompts [#](#0-toc-title)

In these first examples, you can use the prompt to return a maximum or minimum number of terms:

```
Extract a maximum of 20 tags from the following content: '{content}'
```

```
Extract a minimum of 30 categories from the following content: '{content}'. 
```

OpenAI is very flexible about the phrases used, with the exception of {content} which much be included. For example, these variations will all work.

```
Extract keywords from the following content: {content}
```

```
Analyze the following content and extract keywords: {content}
```

```
Extract keyword from the following content: """{content}"""
```

## Ordering the Term Results [#](#1-toc-title)

In the next example, you can use prompts to sort the tags in a specific order.

```
Extract tags from the following content: {content}. Order the tags by alphabetical order
```

```
Extract tags from the following content: {content}. Order the tags by popularity
```

## Choosing a Language for Term Results [#](#2-toc-title)

You can use prompts to specify a language for the terms. This does not have to be the same language as the original content.

```
Extract tags from the following content: {content}. Make sure all Tags are Spanish dictionary words.
```

```
Extract tags from the following content: {content}. Make sure all Tags are written in Japanese characters.
```

## Exclude Words from Term Results [#](#3-toc-title)

You can use prompts to exclude certain words:

```
Extract tags from the following content: {content}. Do not use the words "WordPress", "Plugins" or "Themes".
```

```
Extract tags from the following content: {content}. Do not use any curse words.
```

## Search Only Existing Terms [#](#4-toc-title)

It’s also possible to restrict OpenAI to searching only the existing terms on the site. Use {site_terms} in your prompt as in this example:

```
Scan the following content '{content}' and choose keywords only from this list: '{site_terms}'
```

## Choose a Case for Term Results [#](#5-toc-title)

You can add request that OpenAI return results using a specific case. Let’s take the example of the term “TShirts”.

- PascalCase: **“…return tags in PascalCase where applicable”** : This should return **TShirts**
- camelCase: **“…return tags in camelCase where applicable”** : This should return **tShirts**
- kebab-case : **“…return tags in kebab-case where applicable”** : This should return **T-shirts**
- Capital: **“…return tags in Tshirts where applicable”** : This should return **TShirts**
- Upper: **“…return tags in Uppercase where applicable”** : This should return **TShirts**
- Lower: **“…return tags in lowercase where applicable”** : This should return **TShirts**

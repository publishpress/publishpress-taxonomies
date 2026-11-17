---
title: "Synonyms and Linked Terms in the Database (Pro)"
order: 57
doc_category: publishpress-taxonomies
---

# Synonyms and Linked Terms in the Database (Pro)

Table of Contents

- [Synonym Data](#0-toc-title)
- [Linked Terms Data](#1-toc-title)

This feature requires the Pro version of the Taxonomies plugin

Data for the both the Synonyms and Linked Terms features are stored in the _termmeta table in the WordPress database.

## Synonym Data [#](#0-toc-title)

In this example below, we’ve added 3 synonyms to the “Apple” term. The main “Apple” term has an term ID of 2 in the database.

![TaxoPress synonyms ](https://taxopress.com/wp-content/uploads/2023/10/synonyms-example-1024x672.png)

This next screenshot shows the synonym entries for this term in the database. The term_id is set to 2. The synonyms are stored in a serialized array which is the WordPress standard for term meta with multiple entries.

![TaxoPress synonyms in term meta](https://taxopress.com/wp-content/uploads/2023/10/synonyms-data-1024x689.png)

## Linked Terms Data [#](#1-toc-title)

In this example below, we’ve added 3 linked terms to the “Fruit” term. The main “Fruit” term has an term ID of 22 in the database.

![Linked terms example](https://taxopress.com/wp-content/uploads/2023/10/linked-terms-data-1024x832.png)

This next screenshot shows the linked terms entries for this term in the database. The term_id is set to 22. The linked terms are stored in a serialized array which is the WordPress standard for term meta with multiple entries.

![Linked Terms database](https://taxopress.com/wp-content/uploads/2023/10/linked-terms-database-1024x622.png)

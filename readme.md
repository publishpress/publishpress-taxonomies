# PublishPress Taxonomies

PublishPress Taxonomies allows you to create and manage Tags, Categories, and all your WordPress taxonomy terms. PublishPress Taxonomies can help with all your WordPress content, from adding Tags and Categories to Pages, to managing WooCommerce Product Categories and bbPress Topic Tags. 

With PublishPress Taxonomies, you can organize, optimize, and showcase all your WordPress content:

* **Organize**: In PublishPress Taxonomies, you can manage taxonomies, and also terms. There are tools for you to add, rename, remove, delete and even merge terms. You can add any term or taxonomy to any post type.
* **Optimize**: PublishPress Taxonomies can improve your SEO with automatic term management. PublishPress Taxonomies can automatically suggest relevant terms for your content and link those terms to the correct archive pages.
* **Showcase**: PublishPress Taxonomies allows you to use your terms for advanced features such as Related Posts and Tag Clouds.

## Key Features in PublishPress Taxonomies ##

1. **Manage Terms and Taxonomies**: You can create new taxonomies and edit all the settings for each taxonomy.
2. **Terms Display**: This feature allows you to create a customizable display of all the terms in one taxonomy.
3. **Terms for Current Post**: This enables you to create a customizable display of all the terms assigned to the current post.
4. **Related Posts**: Shows lists of posts with similar tags and categories.
5. **Auto Links**: If you have a term called “WordPress”, the Auto Links feature will find any instances of “WordPress” in your content and add links to the archive page for that tag.
6. **Auto Terms**: If you have a term called "WordPress", Auto Terms can analyze your posts, and when it finds the word "WordPress", it can add that term to your post. 
7. **Suggest Terms**: This feature can show a metabox where you can browse all your existing terms. A second metabox can analyze your content and find new ideas for terms. 
8. **Mass Edit Terms**: Quickly add or remove taxonomy terms from any content in WordPress.
9. **Manage Terms**: Import/export taxonomy terms, add, rename, merge, remove, delete and even delete unused terms.

### Terms Import/Export

The first Manage Terms tab can import or export terms for any public taxonomy and
post type selected in the screen filter. Exports include unused terms and use
UTF-8 CSV with `slug,name,description,post_ids`. Hierarchical taxonomies add a
`parent_slug` column so category relationships are retained. `post_ids` contains
pipe-delimited content IDs.
Term metadata, post content, and new posts are not transferred.

Back up the destination database first. The same post IDs must identify the same
intended posts on both sites; the tool cannot verify migration identity. Imports
add associations without replacing unrelated terms. Existing term names,
descriptions, and parents are updated by default. Missing or unsupported content
assignments are skipped without blocking valid terms and assignments. Results
report readable assignment and affected-content counts without listing raw IDs.
Malformed IDs and permission denials are never skipped. Validation precedes writes, but runtime failures may leave
partial results; there is no transaction or automatic rollback.

Import limits: 5 MiB, 5,000 terms, and 25,000 associations per file, including
skipped targets. Name/slug limits are 200 bytes each; descriptions 10,000 bytes.
Export is uncapped; split large CSV files with a CSV-aware tool and retain headers.
Preserve the original CSV to retain Unicode, multiline descriptions, backslashes,
and reversible spreadsheet-formula escaping.

The Manage Terms feature must be enabled. Transfer requires `simple_tags` and
the selected taxonomy's management capability; import also requires its editing
capability. Associations require assignment permission and permission to edit
every compatible content item. Export checks permission to read every associated
content item before delivering any attachment. Results appear as dismissible
page-level notices, scoped to the current user/site, and expire after five minutes.


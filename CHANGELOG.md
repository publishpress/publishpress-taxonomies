The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [3.54.0] - 22 September, 2026
* Security: Prevent reflected XSS in Terms row-action URLs
* Fixed: Prevent creation of a destination term when merging terms and no source terms exist
* Fixed: Preserve the destination term when it is included among the terms being merged
* Fixed: Fix Auto Links excluded-term matching
* Update: Add per-post Auto Terms status controls
* Update: Add per-post Auto Links status controls and enable overrides

## [3.53.0] - 01 September, 2026
* Fixed: Mass Edit pagination is broken, #3014
* Update: Enforce native taxonomy capabilities for term actions, #3013
* Update: Bound Auto Terms existing-content processing, #3012
* Update: Restrict administrative taxonomy term lookups, #3011
* Update: Harden AI integrations and metabox mutations, #3010
* Add a loco.xml file, #3009

## [3.52.0] - 20 August, 2026
* Update: Normalize Auto Terms specific-term values, #3001
* Update: Add permission checks to Pro configuration copy actions, #3000
* Update: Restrict synonym validation to TaxoPress managers, #2999
* Add permission checks to term copy actions, #2998
* Harden Linked Terms list and bulk actions, #2997
* Strengthen Manage Terms request validation, #2995
* Tighten access checks for term suggestion lookups, #2993
* Add request validation to legacy tag creation, #2991
* Harden TaxoPress AI term result rendering, #2989

= [3.51.0] - 22 July, 2026 =
* Fixed: Fatal memory exhaustion on Terms screen (st_terms) with hierarchical taxonomy — not related to term count, #2967
* Fixed: Related Posts thumbnail image resolution setting is ignored, #2964
* Fixed: Terms screen causes memory exhaustion on large sites, #2962
* Fixed: Improve AI preview post validation, #2968. Thanks to Duy for the report
* Fixed: Terms screen can run out of memory on large sites, #2961
* Update: Remove the lock icon in the Pro version, #2963
* Update: Include changelog in readme.txt for plugin update dialog box, #2959

= [3.50.0] - 13 May, 2026 =
* Update: Add CHANGELOG.md file, #2944
* Update: Add Bundled Translations Library, #2943
* Update: migrate to shared dev-workspace, #2941
* Update: Move plugin structure to match PublishPress, #2940


= [3.45.0] - 16 April, 2026 =
* Fixed: Issue with metabox overlap in WordPress 7.0, #2929
* Fixed: Uncaught TypeError: array_keys(): Argument #1 in class.admin.manage, #2921
* Update: Related Posts settings, #2928
* Update: Update readme file from WordPress.org #2936

= [3.44.0] - 22 January, 2026 =
* Fixed: Auto Terms Existing Content and Schedule aren't adding terms correctly, #2898
* Update: Update description for automatic terms display selection mode, #2893
* Update: Add a description to Posts and Manage Terms tabs, #2894
* Update: Description for Frontend Scripts, #2895
* Update: Add AutoTerms Log link to Existing Content page, #2897

= [3.43.0] - 13 January, 2026 =
* Fixed: "Post term separator string" not working, #2879
* Fixed: Taxopress adds inline css in the head, #2877
* Update: Allow Synonyms to be added using a separator. #2880
* Update: Add Whole Words setting for Auto Links, #2876
* Update: Add {site_terms} placeholder for site-wide term search in OpenAI AutoTerms, #2875
* Update: Move "Schedule" to the Free version, #2862
* Update: Allow users to disable frontend scripts, #2756
* Update: Allow users to select specific terms in "Terms Display", #2609
* Removed: Remove unavailable OpenAI models, #2874

= [3.42.0] - 08 December, 2025 =
* Fixed: Terms screen is blank in wordpress 6.9, #2865

= [3.41.0] - 01 December, 2025 =
* Fixed: Autocomplete doesn't handle characters correctly, #2853
* Fixed: Mass Edit search box is unclear, #2852
* Fixed: Auto Terms and Custom Fields not working, #2841
* Fixed: Minimum term usage for Auto Links is not applied, #2823
* Improved: Improve Manage Terms batch merge permissions, #2844
* Improved: Improve AI “Existing Terms” ordering and performance, #2842
* Improved: Improve "Suggest Terms to Merge" suggestions and UI, #2840
* Update: Add Post Type Filters to the Taxonomy screen, #2846

= [3.40.1] - 03 November, 2025 =
* Fixed: Uncaught TypeError: Cannot read properties of null (reading 'removeEditorPanel'), #2830
* Fixed: Security improvement in Autoterm post_types/post_status parameters, #2827
* Fixed: Can't add 2 terms with the same name, #2826
* Fixed: Dashboard is too wide for the screen, #2820
* Update: Translations updates for TaxoPress Pro 3.40.0, #2825

= [3.40.0] - 16 October, 2025 =
* Fixed: Preview dropdown doesn't show some characters correctly, #2780
* Fixed: Issue with some GPT 5 models, #2778
* Fixed: Auto Links not working with Elementor, #2808
* Update: Show term slug in Mass Edit Terms, #2777
* Update: Show term slug in the metabox, #2774
* Update: Move Metabox Settings to Fast Update Screen, #2773
* Update: Scan and Automatically Suggest terms to merge, #2729
* Update: Preview for different user roles and post types, #2795
* Update: Update to Menu links: Rename "Fast Update" to "Metaboxes", #2791
* Update: Place License tab to the end of all tabs, #2804
* Update: Only show the pencil edit icon on the Metaboxes screen, #2792
* Removed: Remove Title feature from Auto Links, #2809

= [3.38.0] - 17 September, 2025 =
* Fixed: Frontend css loads on all pages, #2757
* Fixed: Autolink Feature is slowing down the admin area, #2754
* Fixed: Some settings not apllied in suggest existing terms, #2748
* Fixed: Schedule autoterms not running, #2747
* Fixed: Problem with GPT 5, #2745
* Fixed: Dropdown in the Metabox is hard to use, #2733
* Fixed: Choose Terms screens are over-complex, #2701
* Update: Update the readme from WordPress.org, #2746
* Update: Improve UI for the "Schedule" feature, #2743
* Update: Allow users to rename the metabox tabs, #2736
* Update: Allow users to disable the filters in the Metabox, #2734
* Update: Lock icon for "Add" buttons, #2721
* Update: Italian Translation Update for TaxoPress FREE 3.37.4, #2744

= [3.37.4] - 02 September, 2025 =
* Update: Enable "Add terms if synonyms found" by default, #2727
* Update: Update the OpenAI models, #2728
* Fixed: Error with "Existing taxonony terms", #2732

= [3.37.3] - 10 August, 2025 =
* Update: Add security checks for autocomplete, #2722
* Update: Update tooltip library. #2720
* Update: Add cropping for Related Posts images #2704
* Fixed: Related Posts doesn't display correct inside post editor, #2705

= [3.37.2] - 09 July, 2025 =
* Fixed: Uncaught Error: array_merge(): Argument #1 must be of type array, string given in taxonomies-functions, #2698
* Fixed: Auto Links new engine message is under two tabs, #2702
* Fixed: No tags text is missing on the frontend, #2712
* Fixed: unable to uncheck categories in post editor, #2697
* Fixed: Term Order doesn't support nested Categories, #2695
* Update: Hide the Custom URL column on Terms screen, #2700
* Update: Update "Taxonomy Terms Table" name, #2699

= [3.37.1] - 03 July, 2025 =
* Fixed: Allow Users to disable TaxoPress Ordering Methods, #2687

= [3.37.0] - 23 June, 2025 =
* Fixed: Warning issue from Auto Links, #2663
* Fixed: Not all design settings apply to Terms Display designs, #2658
* Update: Move Auto Terms tab, #2661
* Update: Update "Metabox" tab settings, #2660
* Update: Update "Term Management" label, #2659
* Update: New tab for "Layout", #2657
* Update: Add a message for empty previews, #2655
* Update: Improve default display for Related Posts, #2651
* Update: Improve default display for Terms Display, #2650
* Update: Allow users to disable either "Custom URLs" or "Auto Links", #2632
* Feature: Design options for Terms for Current Post and Related Posts, #2656
* Feature: Display options for Terms for Current Post, #2653
* Feature: Allow users to order terms via the Terms Screen, #1794

= [3.36.0] - 02 June, 2025 =
* Update: Add "Edited with TaxoPress" row in taxonomies table, #2640
* Update: Show the "Delete TaxoPress Edit Data" button for only taxonomies edited with taxopress, #2639
* Update: Add a Loco.xml file to the Pro Version, #2634
* Update: Move IBM Watson / Dandelion / LSEG to a Legacy status, #2577
* Update: Update message "Posts tagged with X", #2576
* Update: Add support for term descriptions in displays, #2429
* Feature: Allow users to copy terms with metadata, #2522
* Feature: Add previews feature to related posts, current posts and terms display, #2521
* Fixed: PHP error with the Rank Math SEO plugin, #2626

= [3.35.1] - 12 May, 2025 =
* Fixed: "Maximum terms to display" issue with Hidden Terms, #2625
* Fixed: Hide "Link Categories", #2608
* Fixed: Missing the Free banner on the Fast Update screen, #2591
* Fixed: Conflict between TaxoPress and Amelia, #2584
* Update: Update the copyright, #2600
* Update: Visual clean up of Metaboxes tab, #2593
* Update: Visual clean up of Merge tab and Manage Terms screen. #2592
* Update: Pro nudge for Metabox Taxonomy Display, #2581
* Update: Pro nudge for the Schedule feature, #2580
* Update: Pro nudge for Linked Terms and Synonyms, #2578
* Update: Text description for Linked Terms and Synonyms, #2579
* Update: Make sure all Pro features are promoted in the Free version, #2528
* Feature: Setting for showing the slugs, #2595
* Update: TaxoPress v3.33.0 Translation Update ES-FR-IT, #2563, #2564
* Update: TaxoPress v3.34.0 Translation Update ES-FR-IT, #2601, #2602

= [3.34.0] - 22 April, 2025 =
* Update: Remove Hidden Terms from Auto Links, #2582
* Update: Clarify the Exclusions message, #2573
* Feature: Allow Users to disable Custom URL Field for Taxonomies, #2568
* Update: Allow users to see the slug, not just the name in the Merge feture, #2567
* Feature: Add a Parent / Child display format, #2526
* Feature: Allow users to specify maximum or minimum length for new terms, #2525
* Update: Reposition filters on the Manage Terms screen, #2475
* Update: Add Ajax Support for merging large terms, #2438
* Fixed: Add simple-tags/vendor/publishpress/wordpress-version-notices/tests/ to ignore directory list, #2566

= [3.33.0] - 24 March, 2025 =
* Update: Change "Metaboxes" to "Fast Update" and make it share same look as post metabox, #2493
* Feature: Allow users to prevent some characters from being added to terms, #2500
* Feature: Allow admins to control which terms users can add, #2259
* Update: Move extra options to the "Settings" area, #2497
* Update: Move the remaining "Metaboxes" settings, #2439
* Update: Add button to delete edited taxonomies data from TaxoPress/fix deleted taxonomy data showing in TaxoPress, #2547
* Update: Add support for new OpenAI models, #2534
* Update: Update Create Terms capability requirement text for metabox create tab, #2523
* Fixed: PHP 8 phpcs error in vendor/sabre/vobject, #2533
* Update: Update the "Hide unused terms" feature, #2326
* Update: Small visual fixes for metabox filters, #2529
* Update: Update widget message, #2518
* Fixed: Maxiumum terms to display is ignored for Non-Hierarchical Taxonomies, #2519
* Update: TaxoPress v.3.32.0 Translation Update ES-FR-IT, #2517

= [3.32.0] - 03 March, 2025 =
* Fixed: PHP Parse error: syntax error, unexpected ‘=>’ on PHP 5.6 or lower, #2505
* Fixed: Maximum terms to display, #2504
* Update: Enable select2 search on autoterms schedule and Terms Display page, #2507
* Update: TaxoPress v.3.31.0 Translation Update ES-FR-IT, #2503

= [3.31.0] - 27 February, 2025 =
* Feature: Allow users to set a custom URL for Auto Links, #2470
* Feature: Allow users to create tags manually via the metabox, #2085
* Update: Add custom taxonomies to "Remove Default Metaboxes" settings option, #2456
* Update: Make "Show Existing Terms" the default, #2450
* Update: Make sure post terms is always included in "Show All Existing Terms" tab irrespective of settings restriction, #2444
* Update: Update the "Hide unused terms" feature, #2326
* Feature: Add a "Copy" option to taxopress screen tables, #2432
* Update: Block other plugins from loading their notices on our screens, #2476
* Update: Add back filter to metaboxes, #2223
* Update: Allow multiple Auto Terms to be scheduled, #2464
* Update: Update Taxonomies in settings "Metabox options" to only public taxonomies, #2445
* Fixed: Auto Links PHP Error: mb_convert_encoding(): Handling HTML entities via mbstring is deprecated, #2471
* Update: Updated text for Auto Terms logs, #2469
* Update: Show only a subset of a Taxonomy in Terms Display, #1043
* Fixed: Display issue for "Exceptions" in Safari, #2398
* Update: Update the promo box text, #2462
* Fixed: TaxoPress is adding menu separator with wrong filter, #2427
* Fixed: Related posts count bug, #2463
* Update: Translation Updates ES-FR-IT v3.30.0, #2457

= [3.30.0] - 29 January, 2025 =
* Update: Add settings to Control how the metgabox Taxonomy box appears:  Select / Dropdown / Checkbox, #2128
* Update: Allow users to merge multiple terms with the same name, #2357
* Fixed: Issue with commas in "Use only some terms in the selected taxonomy.", #2301
* Update: Update {post_terms} text, #2389
* Update: Improve metabox permission error message, #2433
* Fixed: Update and sanitize legacy widgets title, #2435
* Update: Add a configuration link on Metabox description, #2440
* Update: Small text update for Auto Term preview area, #2416
* Update: Add back a "Display automatically" option to Related Posts, #2413
* Update: Remove extra space in Existing Content "Stop" button link, #2414
* Update: Text update for Related Posts, #2408
* Update: remove the "required" label from maximum number of categories and tags, #2409
* Update: TaxoPress v.3.28.0 Italian French Spanish translation updates, #2407

= [3.28.1] - 04 December, 2024 =
* Fixes: Missing Taxonomies after v3.28.0 upgrade, #2401

= [3.28.0] - 03 December, 2024 =
* Update: Add new Auto Term tab "When to Use", and move applicable settings to each subtabs, #2200
* Update: Add a Pause button to the Existing Content screen, #2210
* Update: Auto Terms: allow users to remove existing terms when adding new terms via Auto Terms, #1910
* Update: Add more details to "no terms added" in Existing Content, #2289
* Update: Use select2 search for term filters to improve term load, #2294
* Fixed: Custom taxonomy filter isn't working, #2372
* Fixed: Translation warning from 6.7, #2356
* Fixed: Database error: table taxopress_linked_terms doesn’t exist, #2368
* Update: Make "Schedule Auto Terms" into a menu link, #2144
* Fixed: Auto Term Error message goes off the screen, #2334
* Update: Add OpenAI settings to exclude terms not in {post_terms} if used in custom prompt, #2335
* Update: Move Auto Term post type to new tab, #2361
* Update: Automatically display related posts in selected post types, #2313
* Update: Equal width for Linked Terms columns, #2344
* Update: Text change for Linked Terms screren, #2343
* Update: New "Display Format" tab, #2314
* Fixed: Media Tags are not appearing in the output of get_taxonomies(), #2298
* Update: Make Auto Term Content and Title option in Auto Term content area, #2327
* Fixed: "Exclude terms from terms display" should cover lowercase and uppercase, #2325
* Update: Text change for Terms screen, #2345
* Update: Text change for Related Posts option, #2341
* Fixed: Missing translation strings in TaxoPress Free, #2295
* Fixed: Missing strings in TaxoPress Pro, #2349
* Update: TAXOPRESS v3.27.0 - Translation Updates ES-FR-IT, #2339

= [3.27.0] - 06 November, 2024 =
* Update: Add setting to Make Linked Terms 1-way or 2-way, #2099
* Update: Add a subscript 1 or 2 to linked term to make it easy to know if a linked term is the primary term or secondary term, #2099
* Update: Allow Auto Terms to search more content areas (Taxonomies and Fields), #2096
* Update: Add a "Preview" area inside Auto Terms, #2231
* Update: Add support for more statuses in Auto Terms, #2246
* Update: Add {post_terms} token to OpenAI custom prompt, #2123
* Update: Add settings to limit Synonyms to preffered taxonomies, #2094
* Fixed: OpenAI producing sentence instead of individual terms, #2258
* Fixed: "Show in Filter" not working for builtin taxonomies, #2291
* Fixed: Existing Content "Limit Auto Terms, based on published date" always defaults to "24 hours ago", #2281
* Update: Added Option to copy or migrate a term, #1055
* Update: Update metabox "no results" texts, #2273
* Update: Add a "Remove Terms" feature on the "Manage Terms" screen, #2271,
* Update: Add a "Hide Unused Terms" feature on the "Manage Terms" screen, #2271
* Update: Allow users to customize the date in Related Posts, #2249
* Update: Improve the "Categories" area in Related Posts, #2248
* Update: Allow users to choose the size / resolution of the image in Related Posts, #2284
* Update: Add a Current Post block, #2263
* Update: Add a Terms Display block, #2262
* Update: Move Maximum number of categories to advanced category, #2305
* Update: Small label change for blocks, #2304
* Update: New label and tab for related posts date format, #2302
* Update: Labels for post thumbnail, #2303
* Fixed: "Term link format" doesn't work for Box List, #2250
* Update: Add "Exclude Terms" feature for "Terms Display", #1376
* Update: Translation Updates ES-FR-IT v3.26.0, #2252

= [3.26.0] - 08 October, 2024 =
* Update: Move metabox API keys and settings from Metabox directly into Auto Terms, #2106
* Update: Allow multiple sources (Existing terms, OpenAI, etc) to be used together in Auto Terms, #2075
* Update: Allow users to choose OpenAI models, #2186
* Update: Improve the Delete Unused Terms feature, #1857
* Update: Set new defaults for Related Posts, #2218
* Feature: Allow Auto Term Post filter using 'taxopress_filter_autoterm_content', #2216
* Fixed: Not possible to save the "Maximum related posts to display" option in Related Posts, #2208
* Update: Use the "Auto Terms setting" approach for Metaboxes also, #2101
* Update: Provide layout options for Related Posts, #2155
* Update: Add sorting to Related Posts Table display, #2185
* Update: Skip the Media post type in Auto Terms, Auto Links, Terms Display, Terms for Current Post and Related Posts options, #2107
* Update: Translation updates for TaxoPress v3.25.1, #2193

= [3.25.1] - 05 September, 2024 =
* Fixed: Undefined variable $default_featured_media in Related Posts, #2187

= [3.25.0] - 05 September, 2024 =
* Fixed: "Exclude previously analyzed content" not working, #2169
* Update: Improve OpenAI failure message, #1951
* Update: Add a notice about not leaving the screen on Existing Content page, #2170
* Update: Add an edit link to Existing Content Auto Terms, #2172
* Feature: Add "Border Cloud" design option to Terms Display Format. #1193
* Feature: Add "Table" display in Terms Display format options, #1398
* Feature: Add a "WordPress Default" display for Terms Display, #1398
* Fixed: Auto Links not working on apostrophes, #2097
* Update: Remove the required from Auto Terms "Existing taxonomy terms *" source field label, #2108
* Update: Add "Save Settings" button to "Existing Content" page, #2137
* Update: Remove "TaxoPress AI", add "Auto Terms" to plugin action links, #2139
* Update: Update the text for "Display format" in "Terms Display, #2104
* Update: Translation Updates ES-FR-IT_TAXO_Free v3.24.1, #2162

= [3.24.1] - 23 August, 2024 =
* Fixed: Warning: Cannot modify header information preventing login on PHP 8, #2157

= [3.24.0] - 22 August, 2024 =
* Update: Add options to Exclude Blocks from Auto Links, #2134
* Update: Add options to Exclude Shortcodes from Auto Links, #2135
* Update: Allow %post_content% as an option in Related Posts, #2127
* Update: Allow %post_thumb_url% in Related Posts, #2114
* Update: Make Auto Links run early before other the_content filter, #2133
* Fixed: Auto Links issues with Preformatted code, #2120
* Fixed: Issue with Soundcloud block and Auto Links, #2119
* Fixed: Auto Links issues with < > in a paragraph, #2118
* Update: Text change for related post ,#2112
* Update: Update the text for "Display format" in "Terms Display, #2104
* Update: Update the header area for "Manage Terms", #2130
* Update: Update the header area for "Mass edit terms", #2129
* Update: Remove Upgrade boxes from the "Exisiting Content" screen, #2138
* Update: Translation Updates ES-FR-IT_TAXO_v3.23, #2125

= [3.23.0] - 07 August, 2024 =
* Update: Add role visibility settings for metabox, #1957
* Update: Allow users to remove the default Tags and Categories metaboxes, #2044
* Update: Re-organize TaxoPress Menu, #2069
* Update: Move Existing Content to new menu, #2022
* Fixed: Fatal error on some website related to legacy code, #2083
* Fixed: Auto Links doesn't work on apostrophes #2097
* Fixed: "Auto Terms Limit" not working in Classic Editor, #2076
* Fixed: Mass Edit Terms broken by the WordPress update notice, #2070
* Update: Translation Updates ES-FR-IT TAXO Free v3.22.0, #2068

= [3.22.0] - 16 July, 2024 =
* Fixed: TaxoPress AI Synonyms not working properly, #2013
* Update: Disable Media Tags by default, #2036
* Update: Small text improvements for the TaxoPress AI screen, #2059
* Update: Allow users to choose only one Auto Term source, #2021
* Fixed: The term_id been used to update the row where term_taxonomy_id = term_id, #2051
* Update: Update the PublishPress vendor libraries, #2038
* Update: Update Linked Terms description, #2047
* Update: Translation Updates ES-FR-IT_TAXO_Free_v3.21.1, #2058

= [3.21.1] - 26 June, 2024 =
* Fixed: Auto Links class/id exclusions not working on <p> elements, #2040
* Fixed: Plugin conflict with Linked Terms, #2045
* Fixed: TaxoPress conflict with the WP All Import plugin, #2037
* Fixed: Deprecated in class.client.autolinks.php on line 94, #2043
* Update: TaxoPress Italian French Spanish translation updates v. 3.21.0, #2035

= [3.21.0] - 22 April, 2024 =
* Feature: Add a new "Linked Terms" screen, #2006
* Update: Add button to fill in Linked Terms for existing posts, #1988
* Update: Add a search box to TaxoPress AI "Show All Existing Terms" Metabox Tab, #2017
* Fixed: WordPress lists fail when content includes "<", #1961
* Fixed: TaxoPress Post Quick Edit link not working in French, Spanish and Italian, #2020
* Update: Add Linked Terms taxonomies option in settings, #2007
* Fixed: TaxoPress v.3.20.0 Italian, French, and Spanish translation updates, #2014

= [3.20.0] - 28 March, 2024 =
* Removed: Remove deprecated Legacy Options and Shortcodes([st-the-tags], [st_tag_cloud]), #2004
* Update: Allow Linked Terms to connect across Taxonomies, #1858
* Update: Enable 2-way option for linked terms, #1736
* Update: TaxoPress v.3.13.0 - Translation updates ES-FR-IT, #2002

= [3.13.0] - 18 March, 2024 =
* Update: Add Custom Prompts to TaxoPress AI OpenAI settings, #1978
* Update: Add "Exceptions" tab for Auto Terms, #1797
* Fixed: PHP Fatal error: Uncaught Error: Object of class WP_Error could not be converted to string, #1989
* Fixed: Warning: preg_match(): Empty regular expression error, #1990
* Update: Add description in Taxonomies "Admin Area" options, #1823
* Removed: Remove the taxonomy templates tab, #1406
* Update: Update Taxonomy Archive page result description, #1824
* Update: Update Taxonomy slug description to Underscores from hyphens, #1949
* Update: TaxoPress Italian French Spanish translation updates, #1986

= [3.12.0] - 12 February, 2024 =
* Fixed: Auto Terms Regex Option not working, #1338
* Update: Support for Private Taxonomies in TaxoPress AI, #1950
* Update: Remove linked tags relationship after a tag is deleted, #1959
* Update: Add Linked Terms support to TaxoPress AI, #1903
* Update: Add the "Select all Tags" option to the metabox, #1947
* Update: Add some plugin links to the plugin description, #1946
* Fixed: PHP Errors: Unknown modifier, #1962
* Update: Add more information to REST API tab, #1825
* Fixed: Deprecated issue on PHP 8.3, #1967
* Update: Update the error message when adding tags that are already a synonym, #1964
* Fixed: TaxoPress AI screen UI issue, #1953
* Update: New Translation updates TaxoPress Free v.3.11.1, #1944

= [3.11.1] - 02 November, 2023 =
* Update: Add TaxoPress AI metabox default taxonomy settings, #1923
* Update: Show duplicate synonym name in synonyms error message, #1931
* Update: Add "Select All" and "Deselect All Toggle to TaxoPress AI tags, #1906
* Fixed: Problem with korean translation, #1933
* Update: Default choices for TaxoPress AI Preview, #1930
* Update: Return Mass Edit Terms from legacy to dashboard, #1920
* Update: Remove TaxoPress AI pro metaboxes in Free version, #1921
* Update: TaxoPress Free Italian French Spanish translation updates October 2023, #1926

= [3.11.0] - 25 October, 2023 =
* Feature: Added AI to the TaxoPress plugin, #1810
* Update: Combine “Settings” and “License” into a single menu, #1856
* Fixed: Merge feature deleting terms when using same terms in all input, #1904
* Update: Update sidebar banner, #1905
* Changes: Move Dandelion and OpenCalais to Pro, #1887
* Update: Add OL/LI option to Terms Display Format, #1860
* Fixed: Terms not displayed when using DESC ordering with numerical terms, #1859
* Update: TaxoPress PRO ES-FR-IT Translation updates September 2023, #1855
* Update: TaxoPress FREE Translation updates September 2023, #1854

= [3.10.2] - 26 September, 2023 =
* Fixed: Auto Link issue with non english contents after v3.10.1 update, #1848

= [3.10.1] - 25 September, 2023 =
* Feature: Allow users to have more than one taxonomy for "Suggest Terms", #1788
* Fixed: Allow users to choose multiple post types for Related Posts, #1774
* Fixed: Auto Link function issue with &lt; and &gt; content, #1829
* Feature: Redirect and welcome message, #1751
* Update: Prevent users from adding terms if it exists as a synonym, #1793
* Fixed: Fatal Error after adding a custom taxomomy to a post, #1804
* Fixed: Deprecated code in PHP 8.2, #1803
* Fixed: Include synonyms in this count Autolink Term Usage Limit, #1805
* Fixed: Allow Manage Terms box to expand for more content, #468
* Update: Add a Pro upgrade message for exceptions in Auto Links, #1807
* Fixed: Promo banner missing in Posts Screen, #1837
* Update: ES-FR-IT Translation Updates TaxoPress PRO 3.10, #1785
* Update: ES-FR-IT Translation Updates TaxoPress FREE August 2023, #1786

= [3.10.0] - 09 August, 2023 =
* Feature: Add new TaxoPress > Posts Screen, #1748
* Feature: Setting for which taxonomies to display on Posts screen, #1762
* Update: Move "Mass Edit Terms" to Legacy, #1759
* Update: Add "Count" column on the "Terms" page, #1757
* Update: Auto add tags when using clicktag in classic editor, #1654
* Update: Update settings "Save Changes" button and remove "Reset Options", #1770
* Update: Add "Text to display before list" to "Related Posts" and "Terms Display", #1600
* Update: Update Manage Terms Notification Messages, #1743
* Update: Add Terms Descriptions column on "Terms" screen, #1706
* Update: Update Synonyms error message, #1730
* Fixed: Prevent "Merge Terms" from automatically adding Default Category, #1741
* Fixed: Warning: Invalid argument supplied for foreach() on Posts page, #1740
* Fixed: Uncaught TypeError: array_map(): Argument #2 ($array) must be of type array, #1729
* Fixed: Duplicate not found label on Taxonomies screen, #1701
* Update: Restrict synonym options to Pro version, #1723
* Fixed: TAXOPRESS PRO - ES-FR-IT translation Updates July 21, 2023, #1722
* Fixed: TAXOPRESS FREE - ES-FR-IT translation Updates July 21, 2023, #1721

= [3.9.0] - 20 July, 2023 =
* Feature: Linked terms: Automatically add terms if main term is added [PRO] #801
* Update: Allow custom input in "Prevent Auto Links Inside elements" [PRO], #1653
* Fixed: Fix Exclude terms from Auto Links not working due to Case sensitivity, #1682
* Update: Add a synonyms option for Auto Terms, #1674
* Update: Make Auto Links Exclude terms bigger and Add Autocomplete to the field, #1680
* Update: Include synonymns consideration when showing suggest terms, #1671
* Update: Prevent a synonym from having the same name as existing terms, #1673
* Update: TaxoFree_ES-IT_TranslationUpdates_June2023, #1675
* Update: TAXO FREE French translation update June 2023, #1678

= [3.8.0] - 15 June, 2023 =
* Feature: Added TaxoPress Dashboard Page #1136
* Feature: Added term synonyms, ability to use multiple names for one tag [Pro Feature], #174
* Feature: Show synonyms on the Terms screen [Pro Version], #1658
* Feature: Synonyms option for Auto Links [Pro Version], #1656
* Update: Clarify the attachment options for Auto Links, #1623
* Fixed: Related Posts block is broken, #1652

= [3.7.4] - 17 May, 2023 =
* Fixed: Settings changes not saving, #1635
* Fixed: Auto Links has problems with the & character, #1638
* Fixed: Auto Links has problems with the < > characters, #1637
* Update: Update Pro links to the plugin page, #1636
* Update: Updating Translations ES-FR-IT_TaxoPress 28 April 2023, #1639

= [3.7.3] - 18 April, 2023 =
* Fixed: 3.7.2 Auto links issue, #1625
* Fixed: "Maximum number of links per post" not working, #1627
* Fixed: Term case isn't working, #1622
* Fixed: Fix "Maximum number of links for the same term", #1620

= [3.7.2] - 13 April, 2023 =
* Fixed: Fatal error on v3.7.1, #1610
* Fixed: Autolink not working with custom taxonomy, #1611

= [3.7.1] - 12 April, 2023 =
* Fixed: Auto Links not working since 3.7.0 update, #1602
* Fixed: Backslash issues when saving Auto Terms fields, #1601
* Fixed: Taxopress ES, FR, and IT Translation Updates, #1598

= [3.7.0] - 04 April, 2023 =
* Fixed: Fixed taxonomy default term not working,, #1216
* Fixed: Fixed terms taxonomy not retaining term taxonomy in quick edit, #1395
* Fixed: "<" Characters been stripped out when using Auto Links, #1580
* Fixed: Suggest Terms showing blank space in suggest term metabox, #1583
* Update: Introduced new way to sanitize fields, #1579
* Fixed: Display Terms not listing all terms, #1390
* Fixed: global_term_enabled deprecated error, #1396
* Update: Show private taxonomies terms when using private taxonomy filter in taxonomy screen, #1397
* Update: Move "Remove Terms" options to "Terms" screen from manage terms, #1368
* Update: Remove "Delete Terms" from "Manage Terms", #1367
* Update: Remove manage terms table, #1592

= [3.6.7] - 13 March, 2023 =
* Fixed: Suggest Terms adds child terms with Classic Editor, #1485
* Update: Allow Private Taxonomies in Auto Terms, #1471
* Update: Suggested improvement for Auto Terms, #1431
* Update: Accessibility improvements to Suggested Terms, #1470
* Fixed: Terms suggestions are not accessible, #1468
* Update: Accessibility improvements for all tabs, #1469

= [3.6.6] - 07 March, 2023 =
* Update: Increased minimum PHP requirement to PHP 7.2.5, #1553
* Update: PHP 8.1 compatability, #1555
* Fixed: Small fixes to Auto Links, #1459
* Update: Add Support for WP Cron in Auto Terms, #1540
* Update: Improve taxonomy REST API description, #1465
* Fixed: PHP issues with the Pimple version, #1476

= [3.6.5] - 21 February, 2023 =
* Fixed: Related Posts fatal error with WooCommerce filter, #1484
* Fixed: incorrectly applying the the_title filter by just passing a single parameter, #1545
* Fixed: PHP 8.1 crashing from using taxopress, #1480
* Update: Format option value cleanup, #1531
* Update: Notice warning about languages for Auto Terms and Auto Links, #1546
* Update: TaxoPress-ES-FR-IT-translationUpdate-August23, #1456

= [3.6.4] - 23 August, 2022 =
* Fixed: Auto link problem with ‘&’ in link #1377
* Fixed: Issues with apostrophes in Auto Links #1444
* Fixed: Warning: array_key_exists expects parameter 2 to be array, bool given #1439
* Update: Add Terms Display "Font size minimum" and "Font size maximum" validation #1354
* Fixed: Span problem on Auto Terms screen #1441
* Update: Update "Automatically fill colors between maximum and minimum" to reflect right label #1371
* Update: FR translation updated #1442

= [3.6.3] - 12 July, 2022 =
* Fixed: Error with Suggested Terms on post screen #1433

= [3.6.2] - 16 June, 2022 =
* Added: Allow more than one "Suggest Terms" metabox on post screen #1408
* Update: Shortcode input should be read only. #1420
* Fixed: The title of the metabox is always "automatic term suggestions" #1409
* Update: Small typo in Auto Terms #1410
* Update: Update the plugin description #1405
* Fixed: Plugin breaks on PHP 5.6 #1419

= [3.6.1] - 04 May, 2022 =
* Added: “Quick Edit” link to the Terms Screen #1359
* Added: Allow users to choose the taxonomy for a term in quick edit #1360
* Added: Filter for Public / Private Terms #1365
* Fixed: Change Taxonomies “Count” link to go to “Terms” screen #1366
* Added: “Post Type” and “Taxonomy” filters to the Terms screen #1358
* Fixed: Remove “Current Post” - Flickr, Technorati and Delicious “dead” links #1374
* Fixed: Extra space in Terms Display and %tag_link% #1375
* Fixed: Link to the Taxonomy from the Terms screen #1364
* Added: A “View” link to terms on the “Terms” screen #1357

= [3.6.0] - 30 March, 2022 =
* Added: New "Terms" Screen #1260
* Added: Add a Regex Option for Auto Terms #1338
* Fixed: Auto Links feature is hiding the product description #1353
* Fixed: Add "View" links for taxonomies #1334
* Fixed: In the "Existing Content" tab, we can drop the word "all" #1344
* Fixed: Replace "Terms for Current Post" with "Current Post" in menu #1345

= [3.5.3] - 23 March, 2022 =
* Fixed: Show existing terms only works with Automatic suggestions #1340

= [3.5.2] - 22 March, 2022 =
* Fixed: Mass edit terms is not saving #1331
* Fixed: Change terms suggestion action handler #1329
* Fixed: Not possible to save "No limit" #1326
* Fixed: Extend Option to limit auto terms schedule action to once per posts to existing content #1323
* Fixed: Option to limit auto terms schedule action to once per posts #1314
* Fixed: TaxoPress Pro seems to require TaxoPress Free #1321
* Fixed: One set of logs reports for a large, slow site #1322
* Fixed: More cautious settings for "Schedule" #1312
* Fixed: Auto Links are processing inside the head tag #1300
* Fixed: Remove "Uncheck this box to disable the "Automatic term suggestions" metabox."" #1305

= [3.5.1] - 09 March, 2022 =
* Fixed: Mass Edit Terms not working #1304
* Fixed: Add the ability to disable logs #1281
* Fixed: Add the ability to purge the logs #1282
* Fixed: Layout changes for Logs #1297
* Fixed: Translation updates #1289
* Fixed: New filters for Logs #1291
* Fixed: Add a light color for used tags #1287
* Fixed: Small updates for "Edit Terms for Current Post" #1292
* Fixed: "Disable Auto Terms" box not working for scheduling #1284

= [3.5.0] - 28 February, 2022 =
* Added: Add a Log for Auto Terms #1243
* Fixed: Safeguards auto terms for large sites #1249
* Fixed: Bug with on Suggested Terms #1250
* Fixed: Change "TaxoPress - Settings" to just "TaxoPress" in metabox #1246
* Fixed: Apply Auto terms batches and wait time to scheduled auto terms cron #1241
* Fixed: Ways to process large numbers of posts in Auto Terms #1200
* Fixed: Check compatability of all 3 widgets with WordPress 5.9 #1119
* Fixed: Disable Free version when people install Pro #1234
* Fixed: Tabbed Design for Terms for Current Post screen #1167
* Fixed: Tabbed design for Related Posts screen #1166

= [3.4.5] - 07 February, 2022 =
* Fixed: Can't delete terms in "Manage Terms" #1194
* Fixed: Correct spelling - Terms for Current Post #1134
* Fixed: Enable “Show content from all post types on archive page” by default #1138
* Fixed: Change name "Local tags" to "Existing terms on your site" #1139
* Fixed: Add "Edit this metabox" link #1140
* Fixed: Check "Existing taxonomy terms" by default #1155
* Fixed: Auto Links change “&rarr;” to &amp;rarr; #1158
* Fixed: Update "Terms Display" admin screen to use vertical tabs #1116
* Fixed: AShow the Display tags inside the plugin #1121
* Fixed: Small changes to "Terms Display" #1171
* Fixed: Stop making "Post Types" required in Taxonomy #1181
* Fixed: Disabling "Suggest existing terms on your site" causes trouble with other metabox #1174
* Fixed: Disable "Post" option in Auto Terms by default #1178
* Fixed: Suggest terms "show existing terms" changes #1176
* Fixed: Hide dropdown if there is only only option for post suggest terms #1228
* Fixed: Add a refresh button to Automatic term suggestions #1215
* Fixed: Show "Edit this metabox" only to users with "admin simple tags" #1214
* Fixed: Automatic terms suggestions not working with categories #1207

= [3.4.4] - 05 January, 2022 =
* Fixed: Add capability check for saving all screen form data
* Fixed: Santize all form entries
* Fixed: Add and validate all form nonce

= [3.4.3] - 15 December, 2021 =
* Fixed: Changes "Terms to Use" to Sources in Auto Terms
* Fixed: Improvement to Auto Terms Screen
* Feature: Add Dandelion and Open Calais to "Auto Terms"

= [3.4.2] - 30 November, 2021 =
* Fixed: Auto Terms required error
* Fixed: Remove extra space around "Save" button
* Fixed: "Add Auto Terms to existing content" not working for unpublished content
* Fixed: New setting: automatically run "Add terms to existing content"
* Fixed: Uncaught Error: Class 'taxopress_admin_ui' not found
* Fixed: Auto Terms is buggy with "Existing Content"
* Fixed: TaxoPress doesn't handle taxonomies correctly if a plugin is disabled
* Fixed: Allow Auto-Terms to work with imported posts

= [3.4.1] - 17 November, 2021 =
* Fixed: Fatal Error with "If Menu" plugin installed
* Fixed: Enable "Suggest existing terms on your site" by default
* Fixed: Add the registration key to taxonomy dropdowns
* Fixed: Two changes inside "Suggest Terms"
* Fixed: Small typo on "Remove Terms"
* Fixed: Add the option to run "Auto Terms" for different statuses

= [3.4.0] - 18 October, 2021 =
* Fixed: 'Show all local tags' does not display any tags, While saving the tags limit with very large number. #792
* Added: Auto Terms limit in settings #450
* Added: Allow users to choose any taxonomy, ordering and number of items in "Click Tags" #497
* Fixed: Autolink tags excludes paragraphs with html space-code #894
* Fixed: Another HTML tag issue with Auto Links #930
* Fixed: Cannot create autolink when using special characters(@#&^%) at the start or the end of the tag #885
* Added: New menu link for "Suggested Terms" #776
* Added: Enable/disable control for all features added to settings #946
* Fixed: Class ‘SimpleTags_Client_RelatedPosts’ not found #982
* Fixed: Manage Terms produces "Error. No enough terms for rename." #978
* Fixed: "Merge Terms" doesn't work #980

= [3.3.1] - 20 September, 2021 =
* Fixed: Fatal error when deleting plugin #889

= [3.3.0] - 20 September, 2021 =
* Fixed: Auto Links problem with " #824
* Fixed: Posts are auto tagged even when the content does not match the exact term. #602
* Fixed: CSS not working for "Terms for Current Post" div #823
* Feature: Update the menu link for "Auto Terms" #775
* Fixed: Improve the UI for Auto Terms "Keywords list" #822
* Added: Include Spanish translation files #829
* Added: Add Review requests #505

= [3.2.2] - 01 September, 2021 =
* Fixed: Able to save mandatory field 'Title' as empty field in Terms for current post. #812
* Fixed: Problem with ' signs in auto links #810
* Feature: Added div class and link class option to Term for Current Post settings #810
* Feature: Added div class and link class option to Term Display settings #810
* Feature: Added div class and link class option to Related Post settings #810
* Feature: Added term link class option to Auto Links settings #810

= [3.2.1] - 23 August, 2021 =
* Fixed: Notice: Undefined property: WP_Post_Type::$taxonomy on plugin activation #756
* Fixed: Couldn't save the related posts 'Title header' with 'None' #697
* Fixed: Auto link showing in WordPress admin #772
* Fixed: Remove unused option in TaxoPress screen edit pages. #709
* Fixed: Added exclusion for <style> elements in auto link #774
* Fixed: Imporove taxonomy page on small screen sizes #749
* Fixed: Add limit to all taxopress number input. #727
* Fixed: Limit taxonomy screen plural label input to 100 characters. #745
* Fixed: Remove slug edit tab for external taxonomies #755
* Fixed: Issue with 'e' character being typed in editor #770
* Fixed: Issue with '&' in auto link #770
* Fixed: Prevent usage of integers(number) only as taxonomy slug #744
* Fixed: Add an option to hide the display if result is empty in "Related Posts", "Terms Display" and "Terms for Current Post" #662
* Fixed: Many other screen improvement to taxopress pages

= [3.2.0] - 09 August, 2021 =
* Added: Autolink screen with improved features such as:
* Autolink: New autolink UI #501
* Autolink: Option to set autolink terms to lowercase, uppercase or retain content case #161
* Autolink: Option to exlude autolink if the tag is already applied to the article #147
* Autolink: Option to set minimum and maximum character length for autolink condition #132
* Autolink: Support for more all taxonomies #268
* Autolink: Option to restrict Autolink instance to a post type.
* Autolink: Option to exclude autolinks in certain classes/HTML Tags like headers, div class/id etc #160
* Deprecated: Move old Autolink settings to legacy #491

= [3.1.2] - 19 July, 2021 =
* Fixed: Term display font color empty value error notification #628
* Fixed: Improve settings success/error notification display #629
* Fixed: Free version promo banner color #647
* Fixed: Updated 'Mass edit terms', 'Manage Terms' and 'Auto Terms' dropdown filter taxonomy selection change #661
* Fixed: Limit terms display and related posts taxonomy options to the selected post type taxonomies #681
* Fixed: Terms display showing more than shortcode maximum value #653

= [3.1.1] - 14 July, 2021 =
* Fixed: TaxoPress related posts block compatibility with WordPress 5.8 #644
* Fixed: Related posts shortcode not working in Gutenberg #646
* Fixed: Undefined array key notice when 'Automatically fill colors between maximum and minimum' is unchecked in Terms Display edit #642
* Fixed: TaxoPress number input accepting negative value #658
* Fixed: Media tag taxonomy still remain attached to a post type after deselecting it #660
* Fixed: TaxoPress widgets compatibility with WordPress 5.8 #666

= [3.1.0] - 12 July, 2021 =
* Added: Add Related Posts screen #491
* Added: Add Related Posts widget #264
* Added: Add Related Posts Gutenberg block #328
* Deprecated: Move old Related Posts settings to legacy #491
* Fixed: Manage terms 'remove terms' not working when term is number only #610
* Fixed: Failed redirection with header error when adding/editing taxonomies #617
* Fixed: Categories and Tags showing on both Public and Private Taxonomies #621
* Fixed: Improve taxonomy edit screen error messages popup design #618

= [3.0.7.2] - 29 June, 2021 =
* Added: Add taxonomy privacy filter to taxonomy screen #599
* Fixed: Fixed bugs

= [3.0.7.1] - 23 June, 2021 =
* Fixed: Legacy setting showing on all settings pages #571
* Fixed: "Terms for current post" includes all post types #580
* Fixed: Terms been added automatically even when "disable auto tags" is selected #581
* Fixed: "Keywords list" is always added even when the option is unchecked in Auto Terms #575
* Fixed: Added support for hashtag when "whole word" option is selected in auto terms #586
* Removed: Remove "Tag cloud Shortcode" setting #570
* Removed: Remove "Term Group" from "Terms Display" method for choosing terms from the database options #567


= [3.0.7] - 15 June, 2021 =
* Added New Terms for Current Post menu
* Added New Terms for Current Post widget
* Deprecated Old Tags for Current Post settings
* Fixed bugs

= [3.0.6.1] - 03 June, 2021 =
* Bugs fix and screen tide up

= [3.0.6] - 01 June, 2021 =
* Added New Terms display menu
* Added New Terms display widget
* Deprecated Old Tag Cloud settings
* Deprecated Old Tag Cloud widget
* Fixed taxonomy archive page to include all asigned post type posts

= [3.0.5.2] - 18 May, 2021 =
* Fixed bugs

= [3.0.5.1] - 13 May, 2021 =
* Fixed bugs

= [3.0.5] - 10 May, 2021 =
* Introduced pro version
* Fixed bugs

= [3.0.4.3] - 29 April, 2021 =
* Fixed bugs

= [3.0.4.2] - 29 April, 2021 =
* Fixed Mass Edit Terms Bugs when media is selected
* Updated Taxonomy edit screen
* Updated default taxonomy list sort by name
* Updated to redirect to edit screen after creating new taxonomy
* Fixed other miscellaneous bugs


= [3.0.4.1] - 21 April, 2021 =
* Fixed last release bugs

= [3.0.4] - 21 April, 2021 =
* Added taxonomy manage page
* Added media tags taxonomy for media page
* Added option to add new taxonomy
* Added option to edit inbuilt and custom taxonomy
* Added option to assign taxonomy to custom post(s)
* Added option to activate and deactivate taxonomy

= [3.0.3] - 22 March, 2021 =
* Improved the "Manage Terms" page
* Added feature to untag all posts with specific tag to "Manage Terms" page
* Removed "Autocompletion with old input"
* Small adjustment to the AutoTerms screen
* Small improvements to metaboxes
* Added search to clicktags

= [3.0.2] - 10 March, 2021 =
* Restore Dandelion API
* Fixed Tags gets split when having an '&' in name
* Removed Tag4Site API
* Update Opencalais API URL
* Clean up the Auto Terms screen and change suggestion tag list to repeated field

= [3.0.1] - 03 March, 2021 =
* Fixed issue with the "tag link format" been stripped out in widgets
* Fixed input display for "Mass Edit Terms" screen going beyond wrapper
* Added valid color picker to color select field in TaxoPress > Settings > Tag cloud
* Updated OpenCalais broken API documentation link
* Removed Alchemy API
* Removed Zemanta API
* Removed Dandelion API
* Removed Proxem API
* Removed Yahoo API
* Hide suggested services in post if there is no active API key
* Delete "Technical informations" in manage terms
* Added auto links for all tags even if post doesn't contain tags
* Updated Auto terms filter layout
* Fixed "Click Tags" for the new input(Gutenberg) not working
* Fixed auto tag still been added after been disabled in general settings
* Updated plugin logo and banner for WordPress.org

= [3.0.0] - 24 February, 2021 =
* Enable 'Advanced Manage Terms', 'Related posts by terms' and 'Tag cloud Shortcode' by default
* Removed review request box and link
* Fixed metabox showing up empty if not used
* Removed unused 'Meta keyword' and 'Related Tags' tabs
* Organized all plugin options under one admin menu
* Update footer copyright text
* Changed plugin name to "TaxoPress"

= [2.63] - 14 January, 2021 =
* fixed an unescaped database query
* fixed a bug with custom post type name in URL when bulk editing posts

= [2.62] - 27 October, 2020 =
* removed all tracking code

= [2.61] - 30 September, 2020 =
* fixed issue with Maximum number of links for the same tag
* added dismissible request to rate
* added promo campaign for WP 301 Redirects

= [2.6] - 10 September, 2020 =
* fixes for WP v5.5
* WebFactory took over maintenance of the plugin
* 80,000 installs; 2,203,335 downloads

* Version 2.5.8
	* Fix issue that prevent Suggested Tags from working
  * Fix OpenCalais issue (thanks @timbearcub for the help)
* Version 2.5.6
	* Github actions...
* Version 2.5.6
	* Change: remove support of CKEditor for WordPress plugin (not maintained)
	* Bug: Try to fix compat with Classic Editor
	* Bug: Related posts do not appear in the RSS feed.
* Version 2.5.5
    * Bug: Improves the deactivation of the plugin when the PHP version is too old.
* Version 2.5.4
    * Bug #114 : fix tag disappear with quick edit
* Version 2.5.3
    * Feature: Add tracking feature
    * Bug #98 : Fix a PHP notice with link manager
    * Bug: Fix a PHP notice with $post global
    * Feature #113 : Add single option for auto-links
    * Feature #70 : Add a visibility option for click tags feature
    * Bug #35 : Fix a bug with multiple calls of the_content hook
    * Code refactoring in many files, remove old cleanup variables
* Version 2.5.2
    * Improve Gutenberg detection, fix bug with tag suggestion caused by some 3rd party plugins
* Version 2.5.1
    * WP.org bumping
* Version 2.5.0
    * Add PHP7.3 compatibility
    * Preparing compatibility with Gutenberg (Does not work with all features due to a bug into Gutenberg https://github.com/WordPress/gutenberg/issues/15147 )
    * Minor code refactoring
* Version 2.4.7
    * Compatibility WP 4.8
    * Fix bug for self-closing tags (https://github.com/BeAPI/simple-tags/pull/67)
    * Updated spanish translation (https://github.com/BeAPI/simple-tags/pull/73)
    * Fix WPalchemy suggestion for latest API version
    * Code refactoring for JS tags suggest
    * Fix JS bug with tabs and firefox (https://github.com/BeAPI/simple-tags/issues/58)
    * Fix autolinks for "number tags" / only numeric characters term (https://github.com/BeAPI/simple-tags/issues/72)
    * Add "access token" field settings for Dandelion suggestion API
    * Improve "proxem" error message
* Version 2.4.6
    * Compatibility WP 4.5
* Version 2.4.5
    * Add shortcode [st-the-tags] and [st_the_tags]
    * Fix array_flip wp_get_object_terms
* Version 2.4.4
    * Fix error upload WordPress.org
* Version 2.4.3
    * Fix metabox title WP 4.4
* Version 2.4.2
    * Make tags all same count mid-sized (pull request #29) (from Github contribution Sophist-UK)
    * Fix options Advanced Usage on widgets (pull request #28) (from Github contribution Sophist-UK)
* Version 2.4.1
	* Fix possible warning with preg_match function
	* Specify user-agent for some provider
* Version 2.4
    * Test OK vs WP 4.0.x
    * Fix Yahoo terms suggestion (use new API)
    * Fix conflict with ShareThis plugin
    * Add option for autolink title attribute
    * Add current state for "click tags" feature (opacity changed if tags is already selected)
    * Fix order by tag cloud
    * Implement dataTXT provider for suggest terms feature (from Github contribution SpazioDati/master)
    * Implement Tag4Site provider for suggest terms feature (from Sergey Zobin)
    * Fix shortcode usage with &
    * Add support of category name into tag cloud function
* Version 2.3.2
	* Move all get_option request into plugin class, use static
	* Add option for limit autolinks to singular view (default setting, you need change this setting in DB for enable autolinks into all views)
	* Replace old library autocomplete by new jQuery UI autocomplete
	* Add an alternative ENGINE text replacement for autolinks feature, use DOMdocument php extension
	* Fix tags autosuggestion from alchemy API
* Version 2.3.1
	* Rename jQuery autocomplete library, add prefix for fix JS conflict (event manager)
	* Fix OpenCalais suggest tags
	* Replace clean_page_cache by clean_post_tag
* Version 2.3
	* Convert all class to STATIC methods
	* Use error message API (self::displayMessage();)
	* Refresh admin UI (settings)
	* Upgrade JS librairies
	* Try to improve performance, use more WP functions instead custom SQL queries
	* Add UNINSTALL method for delete options created by plugins
* Version 2.2 :
	* Add compatibility with WP3.3
	* Move JavaScript register/enqueue to dedicated hook
* Version 2.1.2 :
	* Add some nonces for improve security for settings panel
* Version 2.1.1 :
	* Add a feature that allow deleting rarely used terms. (based on counter term)
	* Fix bug for allow Suggested Tags for page CPT.
* Version 2.1 :
	* Add compatibility with WP 3.2
	* Fix bug with autocompletion. (jQuery want now a strict content type text/plain)
	* Upgrade JS Libary (jQuery Autocomplete, jQuery Cookie, jQuery bgIframe)
* Version 2.0-beta9 :
	* Fix conflict with plugin using Google Library Javascript for jQuery. Example : "Use Google Libraries"
	* Add an option for choose input text or textarea for old tags field
	* Add an option for min chars autocompletion
* Version 2.0-beta8 :
	* Update POT.
	* Update french translation.
* Version 2.0-beta7 :
	* Add a metabox on write page that allow to deactive autolinks or autotags for a specific post
	* Add an option for restore old feature "Manage terms"
	* Add an option for restore old feature "Related posts"
	* Remove "Clean DB" and "Rename slugs" in manage terms
	* Remove "include cat" on "Related Posts" settings
	* Optimize function "Related posts"
	* Reorganize admin for have a tab features with all features available
	* Use a input text for Simple Tags autocompletion field
	* Remove ID of term from autocompletion
	* Fix autocompletion on mange terms page
	* Add a field for advanced usage on tagcloud widget
* Version 2.0-beta6 :
	* Add Japanese translation (thanks - kazuhisa)
	* Fix a bug with search and taxonomy param for mass edit terms. (ticket #233)
	* Fix a bug with auto tags and whole word option. (ticket #232)
	* Fix a bug with tag/pages for include pages in WP_Query for a tag
	* Improve performance of auto link
	* Fix a bug with max links by posts for auto links
	* Add an option for choose priority hook of auto links
* Version 2.0-beta5 :
	* Fix a bug and a notice with st_the_tags() that not display any tags...
* Version 2.0-beta4 :
	* Fix a fatal with error with autoterms.
	* Fix a bug with autoterms when saving post, somes times called twice...
	* Improve performance when saving posts.
	* Allow old tags field for each custom post type that use post tags.
* Version 2.0-beta3 :
	* Fix a bug when the st_the_tags() function is called.
* Version 2.0-beta2 :
	* Restore empty templates functions for skip errors
	* Fix a bug with autolinks
	* Restore custom post tags feature : st_the_tags()
	* Fix a bug with widget taxo option saving
* Version 2.0-beta1 :
	* This version is a back to fondamentals. Somes features are removed !
	* This version need testing !
	* Remove old marker <!--st_tag_cloud-->
	* Remove related posts
	* Remove related tags
	* Remove tags as HTML keywords
	* Remove nofollow options
	* Remove custom function for display current post tags
	* Remove auto-add post tags
	* Remove embedded tags
	* Change method for tags for page.
	* Improve memory consommation : all feature can be deactived.
	* Improve memory consommation : Stop use class variables for options
	* Support mass edit with CPT/CT
	* Improve AJAX call by using WordPress hook
* Version 1.8.1 :
	* Improve uninstaller
	* Improve code widgets
	* Improve support of custom taxonomies for simple tags features
* Version 1.8.0 :
	* Compatibility 3.0
	* Add an option for auto-tags
	* Remove somes notices
	* Fix a bug with tag cloud and empty terms
	* Fix a bug with comma on font-size CSS depending locales
* Version 1.7.5 :
	* Fix a bug with auto links terms. (type input)
	* Fix cache conflict key
	* Fix a opencalais bug on firt pass integration
	* Fix a bug with limit days/category filter for tag cloud.
	* Fix javascript autocomplete for allow enter new tag.
	* Re-add list of tags on manage page.
* Version 1.7.4.4 :
	* Fix a stupid during the first activation. (PHP4/PHP5)
* Version 1.7.4.3 :
	* Fix a bug with PHP 4.4 and plugin activation. (PHP4 suck...)
	* Remove base class for compatibility PHP4.
	* Fix sanitized HTML on admin.
	* Fix random order for tag cloud.
	* Try a fix for support WP-O-Matic cron.
* Version 1.7.4.2 :
	* Updated Traditional Chinese translation (thank to Neil Lin)
	* Fix a bug with selector taxonomy and user taxonomy. (thank to Nicolas Furno)
* Version 1.7.4.1 :
	* Updated italian translation
	* Fix a bug with old random value for widget/tag cloud call (thank to marc@gregel.com)
* Version 1.7.4 :
	* Release of version 1.7.4, to avoid confusion with the borked version 1.7.2 published in error.
	* Externalize options array on external file for diminue memory consommation. (call only on options page)
	* Fix importers embedded tags
* Version 1.7.2 :
	* Add taxonomy support
	* Rewrite manage page
	* New JS for autocomplete, use AJAX for better performance
	* Fix PHP4.
	* Fix bug with Yahoo API/Tag the net for long post
	* Add OpenCalais, Alchemy and Zemanta (first pass, API offers much more possibilities!)
* Version 1.7.1-rc1.2 : RC 1.2 (this version must be test !)
	* Fix compatibility PHP4
* Version 1.7.1-rc1.1 : RC 1.1 (this version must be test !)
	* Fix a bug with activation hook.
* Version 1.7.1-rc1 : RC 1 (this version must be test !)
	* Somes ajust for Wordpress 2.9
	* Remove 99,99% notices PHP from ST
	* Clean some part of the plugin.
* Version 1.7.1-a1 : Alpha 1
	* Check compatibility WP 2.9 ( fix somes UI bugs )
	* Fix a bug with save Widget options
* Version 1.7b1.1
	* Add exclude option in auto tags link.
* Version 1.7b1
	* Add compatibily WP 2.8
	* Remove support old WP versions
	* Change some things on architecture for optimize performance on admin. (stop copy class variables)
	* Use new API for Widgets
	* Use new API for Taxonomy
	* Remove somes "notices" PHP
	* Fix old cache method.
* Version 1.6.6
	* Add Belorussian translation
* Version 1.6.5
	* Fix redeclare class "Services_JSON_Error" bug
* Version 1.6.4
	* Fix a stupid bug with JavaScript add tags.
* Version 1.6.3
	* Fix a small bug with JSON class.
* Version 1.6.2
	* Click tags and suggested tags can work with default WordPress Tags.
	* The Simple Tags auto completion replace default WordPress Tags input.
	* Restore administration options.
	* Better management of Simple Tags with WP_Scripts and WP_Styles
	* Add an option for auto link tags.
	* Fix a potential error with compatibility old markers.
	* Update french and chineese translation.
* Version 1.6.1
	* Fix a JavaScript error for Suggested Tags.
* Version 1.6.0
	* Add compatibility with WordPress 2.7, use new API for HTTP and Admin. No new features !
* Version 1.5.7
	* Move autolink after ShortCodes
	* Add Italian and German translation
	* Fix a potential bug during the plugin activation.
* Version 1.5.6
	* Improve performance of MetaKeywords and Autolink
	* Conform HTML W3C
* Version 1.5.5
	* Restore full manage page
	* Fix duplicate tags for click tags and autocompletion
	* Lot's of optimization...
	* Update translations
	* Add spanish translation
* Version 1.5.3
	* Fix counter for tags. (you must re-save your posts)
	* Update translations (ja, ru)
* Version 1.5.2.1
	* Fix internationalization with Gengo
	* Update zh_CN translation
* Version 1.5.2
	* Fix widgets
	* Edit priority
	* Fix internationalization
* Version 1.5.1
	* Wait plugin_init for start ST :)
* Version 1.5
	* Add compatibility with WordPress 2.5
* Version 1.3.9
	* Fix nofollow rel feature
	* Remove warning with keywords feature
* Version 1.3.8
	* Fix internationalization when reset plugin options (and installation)
	* Fix auto link feature (word replaced keep original case)
* Version 1.3.7
	* Improved performance of Simple Tags (specially Related Posts)
* Version 1.3.6
	* Fix auto tags with all DB and this feature work also for auto tags save feature
	* Clean lot's url
	* Update japan language
* Version 1.3.5
	* Add an option for auto link case
	* Fix rel HTML for auto link
	* Restict tag cloud for published tag (exclude programmed)
* Version 1.3.4
	* Fix autolink (new algo)
* Version 1.3.3
	* Fix link to new page
	* Update languages
* Version 1.3.2
	* Update japan translation
* Version 1.3.1
	* Plugin compatible with mu-plugins without modifications (NOT TAGS SITE WIDE) (for truth !)
	* Include categories into Tag Cloud and Tag list
	* Improve performance with a better WP Object Cache management
	* Add a global option for rel="nofollow" attribute
	* Add an option to limit keywords in HTML header
	* Desactivate keywords generation if All In One SEO Pack is actived.
	* st_the_tags work outside loop now...
	* Add an option to uninstall all ST options
	* Add 2 options for auto tags features
	* Fix javascript autotag features
	* Fix lot's bugs in admin ;)
* Version 1.3
	* Plugin compatible with mu-plugins without modifications (NOT TAGS SITE WIDE)
	* Fix auto link tags now working without meta-keywords
	* New parameter for auto link tags (min usage)
	* Fix auto link PREG_REPLACE error.
	* New parameter for related posts (min tags shared)
	* Add marker %post_title_attribute% for related posts, to use into title attribute
	* Add Related Tags
	* Add Remove Related Tags
	* Fix alphabetical order in tag cloud with accent
	* New interface for Tags options, more options for easily configuration
	* AJAX  admin
		* AJAX Pagination for Manage Tags pages
	* Fix excessive memory consomation
	* Tags suggestion from Yahoo Term Extractions API, Tag The Net and Local Tags with AJAX request
		* Compatible with TinyMCE, FCKeditor, WYMeditor and quicktags
	* Click tags with AJAX request
	* Preview color in Tag Cloud Admin Options
* Version 1.2.4
	* Fix a bug with inline tags post
* Version 1.2.3
	* Fix Widgets Order/Selection
	* Improve auto link feature
	* Add a param to desactive font-size during tags cloud generation
	* Add a param "min_usage" to display a tag in tags cloud
	* Smallest size and largest size can be the same...
	* Update translation
* Version 1.2.2
	* Add more options for inline related posts / tags
	* Fix empty title bug
	* Add maximum param for the function st_the_tags()
	* Update translation
* Version 1.2.1
	* Fix limit for tag cloud
* Version 1.2
    * New features
		* Auto link tags in post content
		* Auto tags (new and old content)
		* Importer for embedded tags
	* Tag Cloud
		* Fix Tag Cloud Widgets
		* New way for order tags cloud ( 2 steps )
		* Allow embedded tag cloud
		* Add tag cloud for a specific category
	* Related Posts
		* New options for automatic insertion
		* Add a marker for post_excerpt
		* Fixed display nothing when no related posts
	* Current tags posts
		* New options for automatic insertion
	* Administration
		* Check WP version
		* Mass edit tags improved (search terms, filter untagged)
		* Removed untagged page
		* Improve special characters management (& ...)
		* Uniformize alert message
		* Autocompletion improved !
	* Others
		* Improved plugin cache
* Version 1.1.1
	* Fix compatibily with MySQL < 4.1 (Marker "related_tags" of Related Posts is desactived.)
* Version 1.1
	* Fix XML-RPC and Embedded Tags
	* Improve compatibility JS with others plugins (calendar)
	* Minor improve in administration (order keeping on update)
	* Cleaner for empty terms
	* Add limit days for Tag Clouds and Related Posts
	* Add the marker: %relatedtags% for Related Posts => Display tag shared between 2 posts.
	* True random for tag cloud
	* Tag cloud can generate class level for CSS size/color
	* Add Order to Tag Cloud Widgets
	* Add 2 functions
		* st_meta_keywords() : Display keywords for manual insert in header
		* st_the_tags() : Improved the official the_tags() functions.
	* Add marker for Technorati, Flickr and Delicious
* Version 1.0.4a (1.0.4a fix a small bug introduced in 1.0.4)
	* Update 3 translations for 1.0.4
	* Add Japanese translation
	* Fix "exclude tags" in related posts
* Version 1.0.3
	* Add 3 translations (german and chineses (zh_TW and zh_CN))
	* Fix a potential bug with posts relateds and WP Object Cache
	* Fix a bug with autocompletion JavaScript (tag not escape)
	* Possibility to customize date format in related posts
	* Optimization in meta keywords
	* New markers to related posts (see advanced usage), old markers are still available
* Version 1.0.2
	* Keywords
		* Fix a rare bug with problem encoding
		* Delete duplicate keywords
	* Related posts
		* Possibility to randomize post
		* New class for tag UL: "st-related-posts"
	* Tag cloud
		* Possibility to randomize tags
		* New class for tag UL: "st-tag-cloud"
	* Administration
		* Use WP roles instead old levels
		* Possibility to order "Mass edit Tags" by Date or ID
		* Counter now works on "Untagged" page"
		* Fix one bug on Mass Edit Tags, he was impossible to delete all tags for a post.
		* You can desactivate Widgets without have a fatal error
* Version 1.0.1
	* Fixes 2-3 minors bugs
* Version 1.0
	* Initial version

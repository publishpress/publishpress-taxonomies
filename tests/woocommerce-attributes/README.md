# WooCommerce global attribute compatibility

Run from the plugin root:

```sh
php tests/woocommerce-attributes/run.php
php tests/woocommerce-attributes/run.php --without-woocommerce
```

The standalone harness mocks WordPress and product persistence. It checks the
WooCommerce-absent path, ordinary taxonomies, non-products, invalid attributes,
orphaned assignments, append/replacement results, preservation of unrelated
attributes and visibility/variation flags, recursion prevention, save failures,
and assignments made during WooCommerce's outer product save.

Optionally pass `--attribute-class=/absolute/path/to/woocommerce/includes/class-wc-product-attribute.php`
to exercise the installed WooCommerce attribute class instead of the fixture.
This does not load WordPress or write to that site's database.

Live integration checks on a disposable WooCommerce test site:

1. Create a global Color attribute with Red and Blue terms. Create a product with
   “Red” in its description, without manually adding Color to the product.
2. Configure Auto Terms for products and `pa_color`. Run Existing Content and
   confirm Color/Red appears in the product's Attributes tab and additional info.
3. Repeat via product save, scheduled Auto Terms, selected-product processing,
   and the Auto Terms Preview's Update button. Merely previewing must not write.
4. Rerun on a product that already has the Red taxonomy relationship but no
   product attribute definition. Confirm the definition is repaired.
5. Add unrelated custom and global attributes; change Color's visibility,
   variation flag, and order. Append Blue, then replace with Blue. Confirm those
   settings and other attributes are retained and existing variations are intact.
6. Test a new product saved through WooCommerce CRUD with Auto Terms enabled,
   then reload it. Verify the outer save does not discard the attribute.
7. Disable WooCommerce and verify ordinary post/tag Auto Terms still works.

Only registered global attribute taxonomies on `product` are synchronized.
Local text attributes and `product_variation` posts are not targeted. New
attributes are visible by default and are never marked for variation use.

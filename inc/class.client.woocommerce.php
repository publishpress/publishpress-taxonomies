<?php

defined('ABSPATH') || exit;

/** Keep Auto Terms' global attribute assignments in WooCommerce's product model. */
class TaxoPress_WooCommerce
{
    /** @var array Product objects currently being saved by WooCommerce. */
    private static $saving_products = [];

    /** @var array Attribute assignments awaiting the outer WooCommerce save. */
    private static $pending_attributes = [];

    /** @var bool Prevent product persistence from triggering another Auto Terms scan. */
    private static $syncing = false;

    /** @var int Product whose persistence must not trigger another scan. */
    private static $syncing_product_id = 0;

    /** Register optional WooCommerce lifecycle hooks. */
    public static function register()
    {
        add_action('woocommerce_before_product_object_save', [__CLASS__, 'before_product_save']);
        add_action('woocommerce_after_product_object_save', [__CLASS__, 'after_product_save']);
    }

    /**
     * @param int|null $post_id Optional post to check for a recursive save.
     * @return bool Whether this product's attribute is being persisted.
     */
    public static function is_syncing($post_id = null)
    {
        return self::$syncing && ($post_id === null || (int) $post_id === self::$syncing_product_id);
    }

    /** @param object $product WooCommerce product being saved. */
    public static function before_product_save($product)
    {
        self::$saving_products[spl_object_id($product)] = $product;
    }

    /** @param object $product WooCommerce product after its data store has saved. */
    public static function after_product_save($product)
    {
        unset(self::$saving_products[spl_object_id($product)]);
        $post_id = $product->get_id();
        if (empty(self::$pending_attributes[$post_id]) || self::$syncing) {
            return;
        }

        // A product save may overwrite term relationships assigned during save_post.
        $pending = self::$pending_attributes[$post_id];
        unset(self::$pending_attributes[$post_id]);
        foreach ($pending as $taxonomy => $term_ids) {
            $result = self::persist_attribute($product, $taxonomy, $term_ids, true);
            if (is_wp_error($result)) {
                do_action('taxopress_woocommerce_attribute_sync_error', $result, $post_id, $taxonomy);
            }
        }
    }

    /**
     * Register an assigned global attribute, including previously orphaned term assignments.
     *
     * @param int    $post_id Product ID.
     * @param string $taxonomy Target taxonomy.
     * @param bool   $force Refresh WooCommerce's lookup data after a term assignment.
     * @return bool|WP_Error True when synchronized or queued, false when not applicable.
     */
    public static function sync_attribute($post_id, $taxonomy, $force = false)
    {
        if (
            self::$syncing
            || !function_exists('wc_get_product')
            || !function_exists('wc_attribute_taxonomy_id_by_name')
            || !class_exists('WC_Product_Attribute')
            || get_post_type($post_id) !== 'product'
            || strpos($taxonomy, 'pa_') !== 0
            || !taxonomy_exists($taxonomy)
            || !wc_attribute_taxonomy_id_by_name($taxonomy)
        ) {
            return false;
        }

        $term_ids = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
        if (is_wp_error($term_ids)) {
            return $term_ids;
        }
        if (empty($term_ids)) {
            return false;
        }
        $term_ids = array_map('intval', $term_ids);

        foreach (self::$saving_products as $saving_product) {
            // A newly inserted product has no ID on its WooCommerce object until save_post returns.
            if (!$saving_product->get_id() || (int) $saving_product->get_id() === (int) $post_id) {
                self::$pending_attributes[$post_id][$taxonomy] = $term_ids;
                return true;
            }
        }

        $product = wc_get_product($post_id);
        if (!$product) {
            return false;
        }

        return self::persist_attribute($product, $taxonomy, $term_ids, $force);
    }

    /**
     * Save through WooCommerce so its caches and attribute lookup tables are updated.
     *
     * @param object $product WooCommerce product.
     * @param string $taxonomy Global attribute taxonomy.
     * @param array  $term_ids Final assigned term IDs, after append/replace processing.
     * @param bool   $force Refresh lookup data even when the product reads the same terms.
     * @return bool|WP_Error
     */
    private static function persist_attribute($product, $taxonomy, $term_ids, $force = false)
    {
        $attributes = $product->get_attributes();
        $attribute = isset($attributes[$taxonomy]) ? $attributes[$taxonomy] : null;
        if ($attribute instanceof WC_Product_Attribute) {
            $current_ids = array_map('intval', $attribute->get_options());
            sort($current_ids);
            $sorted_ids = $term_ids;
            sort($sorted_ids);
            if (!$force && $attribute->is_taxonomy() && $current_ids === $sorted_ids) {
                return true;
            }
            // Clone so unrelated attributes and the merchant's flags remain untouched.
            $attribute = clone $attribute;
        } else {
            $attribute = new WC_Product_Attribute();
            $position = 0;
            foreach ($attributes as $existing_attribute) {
                if ($existing_attribute instanceof WC_Product_Attribute) {
                    $position = max($position, $existing_attribute->get_position() + 1);
                }
            }
            $attribute->set_position($position);
            $attribute->set_visible(true);
            $attribute->set_variation(false);
        }

        $attribute->set_id(wc_attribute_taxonomy_id_by_name($taxonomy));
        $attribute->set_name($taxonomy);
        $attribute->set_options($term_ids);
        $attributes[$taxonomy] = $attribute;

        self::$syncing = true;
        self::$syncing_product_id = (int) $product->get_id();
        try {
            $product->set_attributes($attributes);
            $product->save();
        } catch (Exception $exception) {
            return new WP_Error('taxopress_woocommerce_attribute_sync', $exception->getMessage());
        } finally {
            self::$syncing = false;
            self::$syncing_product_id = 0;
        }

        return true;
    }
}

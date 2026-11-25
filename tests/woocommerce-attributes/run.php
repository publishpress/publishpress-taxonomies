<?php
/** Standalone regression checks for the optional WooCommerce attribute bridge. */
define('ABSPATH', __DIR__);
require dirname(__DIR__, 2) . '/inc/class.client.woocommerce.php';
require dirname(__DIR__, 2) . '/inc/class.client.autoterms.php';

$hooks = [];
$products = [];
$terms = [];
$product_reads = 0;
$checks = 0;
function add_action($name, $callback) { $GLOBALS['hooks'][$name][] = $callback; }
function do_action($name, ...$args) { foreach ($GLOBALS['hooks'][$name] ?? [] as $callback) { $callback(...$args); } }
function get_post_type($id) { return $id === 99 ? 'post' : ($id === 98 ? 'product_variation' : 'product'); }
function taxonomy_exists($name) { return in_array($name, ['pa_color', 'pa_size', 'pa_fake', 'product_cat'], true); }
function wp_get_object_terms($id, $taxonomy, $args) { return $GLOBALS['terms'][$id][$taxonomy] ?? []; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function absint($value) { return abs((int) $value); }
function wc_string_to_bool($value) { return is_bool($value) ? $value : in_array($value, ['yes', 'true', '1', 1], true); }
function check($condition, $label) {
    if (!$condition) { throw new RuntimeException($label); }
    ++$GLOBALS['checks'];
    echo "PASS: $label\n";
}
class WP_Error { public function __construct(public $code, public $message) {} }

TaxoPress_WooCommerce::register();
if (in_array('--without-woocommerce', $argv, true)) {
    check(TaxoPress_WooCommerce::sync_attribute(1, 'pa_color') === false, 'WooCommerce absent: no writes or errors');
    exit(0);
}

// Defined conditionally so the separate WooCommerce-absent run has no WC functions.
if (!function_exists('wc_get_product')) {
    function wc_get_product($id) { ++$GLOBALS['product_reads']; return $GLOBALS['products'][$id] ?? false; }
    function wc_attribute_taxonomy_id_by_name($name) { return ['pa_color' => 1, 'pa_size' => 2][$name] ?? 0; }
}
foreach ($argv as $argument) {
    if (strpos($argument, '--attribute-class=') === 0) {
        require substr($argument, strlen('--attribute-class='));
    }
}
if (!class_exists('WC_Product_Attribute')) {
class WC_Product_Attribute
{
    private $id = 0;
    private $name = '';
    private $options = [];
    private $position = 0;
    private $visible = false;
    private $variation = false;
    public function set_id($value) { $this->id = $value; }
    public function set_name($value) { $this->name = $value; }
    public function set_options($value) { $this->options = $value; }
    public function set_position($value) { $this->position = $value; }
    public function set_visible($value) { $this->visible = $value; }
    public function set_variation($value) { $this->variation = $value; }
    public function get_options() { return $this->options; }
    public function get_position() { return $this->position; }
    public function get_visible() { return $this->visible; }
    public function get_variation() { return $this->variation; }
    public function is_taxonomy() { return $this->id > 0; }
}
}
class ProductFixture
{
    public $attributes = [];
    public $saves = 0;
    public $throws = false;
    public function __construct(public $id) {}
    public function get_id() { return $this->id; }
    public function get_attributes() { return $this->attributes; }
    public function set_attributes($attributes) { $this->attributes = $attributes; }
    public function save() {
        check(TaxoPress_WooCommerce::is_syncing(), 'Attribute save guards recursive Auto Terms scans');
        check(!TaxoPress_WooCommerce::is_syncing(99), 'Recursion guard does not suppress unrelated post scans');
        check(SimpleTags_Client_Autoterms::save_post($this->id, (object) ['post_type' => 'product']) === false, 'Real Auto Terms save handler skips integration writes');
        if ($this->throws) { throw new RuntimeException('Save failed'); }
        ++$this->saves;
        do_action('woocommerce_before_product_object_save', $this);
        foreach ($this->attributes as $taxonomy => $attribute) {
            if ($attribute->is_taxonomy()) { $GLOBALS['terms'][$this->id][$taxonomy] = $attribute->get_options(); }
        }
        do_action('woocommerce_after_product_object_save', $this);
    }
}

foreach ([[99, 'pa_color'], [98, 'pa_color'], [1, 'product_cat'], [1, 'pa_fake'], [1, 'pa_missing']] as [$id, $taxonomy]) {
    check(TaxoPress_WooCommerce::sync_attribute($id, $taxonomy) === false, "Unrelated target ignored: $id/$taxonomy");
}
check($product_reads === 0, 'Ordinary targets do not load or save WooCommerce products');

$products[1] = new ProductFixture(1);
$terms[1]['pa_color'] = [11, 12];
$custom = new WC_Product_Attribute();
$custom->set_name('Material');
$custom->set_options(['Cotton']);
$custom->set_position(7);
$products[1]->attributes['material'] = $custom;
check(TaxoPress_WooCommerce::sync_attribute(1, 'pa_color') === true, 'Register previously unconfigured global attribute');
$color = $products[1]->attributes['pa_color'];
check($color->is_taxonomy() && $color->get_options() === [11, 12], 'Registered attribute uses assigned term IDs');
check($color->get_visible() && !$color->get_variation(), 'New attribute is visible and is not used for variations');
check($color->get_position() === 8 && $products[1]->attributes['material'] === $custom, 'Preserve unrelated custom attribute and append position');
$saves = $products[1]->saves;
TaxoPress_WooCommerce::sync_attribute(1, 'pa_color');
check($products[1]->saves === $saves, 'Repeated repair is a no-op');
TaxoPress_WooCommerce::sync_attribute(1, 'pa_color', true);
check($products[1]->saves === $saves + 1, 'New assignments refresh WooCommerce persistence even for equal loaded options');

$color = $products[1]->attributes['pa_color'];
$color->set_visible(false);
$color->set_variation(true);
$color->set_position(4);
$terms[1]['pa_color'] = [12];
TaxoPress_WooCommerce::sync_attribute(1, 'pa_color');
$color = $products[1]->attributes['pa_color'];
check($color->get_options() === [12] && !$color->get_visible() && $color->get_variation() && $color->get_position() === 4, 'Replacement preserves hidden/variation flags and position');

$products[2] = new ProductFixture(2);
$terms[2]['pa_size'] = [21];
do_action('woocommerce_before_product_object_save', $products[2]);
TaxoPress_WooCommerce::sync_attribute(2, 'pa_size', true);
check($products[2]->saves === 0, 'Defer assignments during an outer WooCommerce save');
// Model WooCommerce overwriting relationships from its original attribute object.
$terms[2]['pa_size'] = [];
do_action('woocommerce_after_product_object_save', $products[2]);
check($terms[2]['pa_size'] === [21] && isset($products[2]->attributes['pa_size']), 'Restore queued assignment after WooCommerce persistence');
check(!TaxoPress_WooCommerce::is_syncing(), 'No recursive save remains active');

$new_product = new ProductFixture(0);
do_action('woocommerce_before_product_object_save', $new_product);
$terms[3]['pa_color'] = [13];
TaxoPress_WooCommerce::sync_attribute(3, 'pa_color');
$new_product->id = 3;
do_action('woocommerce_after_product_object_save', $new_product);
check($new_product->attributes['pa_color']->get_options() === [13], 'New product receives queued attribute after its ID is assigned');

$products[4] = new ProductFixture(4);
check(TaxoPress_WooCommerce::sync_attribute(4, 'pa_color') === false && $products[4]->saves === 0, 'No terms: no empty attribute created');
$terms[4]['pa_color'] = [14];
$products[4]->throws = true;
check(is_wp_error(TaxoPress_WooCommerce::sync_attribute(4, 'pa_color')), 'Surface WooCommerce save errors');
check(!TaxoPress_WooCommerce::is_syncing(), 'Save failure restores recursion guard');
echo "$checks checks passed.\n";

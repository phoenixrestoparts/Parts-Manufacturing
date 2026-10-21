<?php
if (!defined('ABSPATH')) {
    exit;
}

$products = get_posts(array(
    'post_type' => 'product',
    'post_status' => array('publish', 'draft', 'private'),
    'posts_per_page' => -1,
    'orderby' => 'title',
    'order' => 'ASC',
));
?>
<div class="wrap vwpm-wrap">
    <h1>Production Calculator</h1>

    <div class="vwpm-card">
        <form id="vwpm-production-form">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="vwpm_product_id">Product</label></th>
                    <td>
                        <select id="vwpm_product_id" name="product_id" class="regular-text">
                            <option value="">Select product</option>
                            <?php foreach ($products as $product) : ?>
                                <?php $sku = get_post_meta($product->ID, '_sku', true); ?>
                                <option value="<?php echo esc_attr((string) $product->ID); ?>"><?php echo esc_html($sku ? $sku . ' - ' . $product->post_title : $product->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="vwpm_quantity">Quantity</label></th>
                    <td><input id="vwpm_quantity" name="quantity" type="number" step="0.01" value="1" class="small-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="vwpm_vat_enabled">Include VAT</label></th>
                    <td><input type="checkbox" id="vwpm_vat_enabled" name="vat_enabled" checked /></td>
                </tr>
            </table>
            <p><button type="submit" class="button button-primary" id="vwpm-calculate-production">Calculate Requirements</button></p>
        </form>
    </div>

    <div id="vwpm-calculator-results" class="vwpm-calculator-results"></div>
</div>

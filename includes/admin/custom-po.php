<?php
if (!defined('ABSPATH')) {
    exit;
}

$suppliers = get_posts(array(
    'post_type' => 'vwpm_supplier',
    'posts_per_page' => -1,
    'post_status' => 'any',
));

if (empty($suppliers)) {
    global $wpdb;
    $supplier_rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}vwpm_suppliers ORDER BY name ASC");
}
?>
<div class="wrap vwpm-wrap">
    <h1>Create Custom PO</h1>

    <div class="vwpm-card">
        <p>Use this page to build a custom purchase order outside the normal product calculator workflow.</p>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="vwpm_custom_po_supplier">Supplier</label></th>
                <td>
                    <select id="vwpm_custom_po_supplier" class="regular-text">
                        <option value="">Select supplier</option>
                        <?php if (!empty($supplier_rows)) : foreach ($supplier_rows as $supplier) : ?>
                            <option value="<?php echo esc_attr((string) $supplier->id); ?>"><?php echo esc_html($supplier->name); ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="vwpm_custom_po_reference">Reference</label></th>
                <td><input id="vwpm_custom_po_reference" type="text" class="regular-text" placeholder="PO reference" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="vwpm_custom_po_notes">Notes</label></th>
                <td><textarea id="vwpm_custom_po_notes" class="large-text" rows="5" placeholder="Item details and notes"></textarea></td>
            </tr>
        </table>
        <p><button type="button" class="button button-primary">Create PO</button></p>
    </div>
</div>

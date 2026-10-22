<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$suppliers = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}vwpm_suppliers ORDER BY name ASC");

$notice_key = isset($_GET['vwpm_po_notice']) ? sanitize_key(wp_unslash($_GET['vwpm_po_notice'])) : '';
$created_po = isset($_GET['vwpm_po_created']) ? sanitize_text_field(wp_unslash($_GET['vwpm_po_created'])) : '';
$notice_detail = '';
if ($notice_key === 'error') {
    $notice_detail = (string) get_transient('vwpm_notice_detail_' . get_current_user_id());
    delete_transient('vwpm_notice_detail_' . get_current_user_id());
}
$notices = array(
    'security' => 'Security check failed. Please reload the page and try again.',
    'supplier' => 'Please select a supplier.',
    'items'    => 'Add at least one line with an item name.',
    'error'    => 'The purchase order could not be saved.',
);
?>
<div class="wrap vwpm-wrap">
    <h1>Create Custom PO</h1>

    <?php if ($created_po !== '') : ?>
        <div class="notice notice-success">
            <p>
                Purchase order <strong><?php echo esc_html($created_po); ?></strong> created.
                <a href="<?php echo esc_url(admin_url('admin.php?vwpm_print_po=1')); ?>" target="_blank" rel="noopener">Print / Save as PDF</a>
                |
                <a href="<?php echo esc_url(admin_url('admin.php?page=vwpm-purchase-orders')); ?>">View Purchase Orders</a>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($notice_key !== '' && isset($notices[$notice_key])) : ?>
        <div class="notice notice-error">
            <p>
                <?php echo esc_html($notices[$notice_key]); ?>
                <?php if ($notice_detail !== '') : ?>
                    <br><code><?php echo esc_html($notice_detail); ?></code>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="vwpm-card">
        <p>Use this page to build a custom purchase order outside the normal product calculator workflow.</p>
        <?php if (empty($suppliers)) : ?>
            <p><strong>No suppliers yet.</strong> Add one on the Suppliers page first.</p>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('vwpm_create_custom_po', 'vwpm_custom_po_nonce'); ?>
            <input type="hidden" name="vwpm_custom_po_action" value="create">

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="vwpm_custom_po_supplier">Supplier</label></th>
                    <td>
                        <select id="vwpm_custom_po_supplier" name="vwpm_custom_po_supplier" class="regular-text" required>
                            <option value="">Select supplier</option>
                            <?php foreach ($suppliers as $supplier) : ?>
                                <option value="<?php echo esc_attr((string) $supplier->id); ?>"><?php echo esc_html($supplier->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="vwpm_custom_po_reference">Reference</label></th>
                    <td><input id="vwpm_custom_po_reference" name="vwpm_custom_po_reference" type="text" class="regular-text" placeholder="PO reference"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="vwpm_custom_po_notes">Notes</label></th>
                    <td><textarea id="vwpm_custom_po_notes" name="vwpm_custom_po_notes" class="large-text" rows="3" placeholder="Notes"></textarea></td>
                </tr>
            </table>

            <h2>Items</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th style="width: 32%;">Item</th>
                        <th style="width: 16%;">Part Number</th>
                        <th style="width: 16%;">Supplier Ref</th>
                        <th style="width: 10%;">Qty</th>
                        <th style="width: 14%;">Unit Price (ex VAT)</th>
                        <th style="width: 12%;">Action</th>
                    </tr>
                </thead>
                <tbody id="vwpm-po-lines">
                    <tr>
                        <td><input type="text" name="vwpm_po_items[0][name]" style="width: 100%;"></td>
                        <td><input type="text" name="vwpm_po_items[0][part_number]" style="width: 100%;"></td>
                        <td><input type="text" name="vwpm_po_items[0][supplier_ref]" style="width: 100%;"></td>
                        <td><input type="number" step="0.01" min="0" name="vwpm_po_items[0][qty]" value="1" style="width: 100%;"></td>
                        <td><input type="number" step="0.01" min="0" name="vwpm_po_items[0][unit_price]" value="0.00" style="width: 100%;"></td>
                        <td><button type="button" class="button vwpm-po-remove-line">Remove</button></td>
                    </tr>
                </tbody>
            </table>
            <p><button type="button" class="button" id="vwpm-po-add-line">Add Line</button></p>

            <p><button type="submit" class="button button-primary">Create PO</button></p>
        </form>
    </div>
</div>

<script type="text/html" id="vwpm-po-line-template">
    <tr>
        <td><input type="text" name="vwpm_po_items[INDEX][name]" style="width: 100%;"></td>
        <td><input type="text" name="vwpm_po_items[INDEX][part_number]" style="width: 100%;"></td>
        <td><input type="text" name="vwpm_po_items[INDEX][supplier_ref]" style="width: 100%;"></td>
        <td><input type="number" step="0.01" min="0" name="vwpm_po_items[INDEX][qty]" value="1" style="width: 100%;"></td>
        <td><input type="number" step="0.01" min="0" name="vwpm_po_items[INDEX][unit_price]" value="0.00" style="width: 100%;"></td>
        <td><button type="button" class="button vwpm-po-remove-line">Remove</button></td>
    </tr>
</script>
<script type="text/javascript">
jQuery(function ($) {
    var nextIndex = 1;
    $('#vwpm-po-add-line').on('click', function (e) {
        e.preventDefault();
        $('#vwpm-po-lines').append($('#vwpm-po-line-template').html().replace(/INDEX/g, nextIndex++));
    });
    $(document).on('click', '.vwpm-po-remove-line', function (e) {
        e.preventDefault();
        if ($('#vwpm-po-lines tr').length > 1) {
            $(this).closest('tr').remove();
        } else {
            $(this).closest('tr').find('input').not('[type=number]').val('');
        }
    });
});
</script>


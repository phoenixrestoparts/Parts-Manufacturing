<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'vwpm_pos';
$list_url = admin_url('admin.php?page=vwpm-purchase-orders');

$po_id   = isset($_GET['po_id']) ? absint($_GET['po_id']) : 0;
$msg_key = isset($_GET['vwpm_po_msg']) ? sanitize_key(wp_unslash($_GET['vwpm_po_msg'])) : '';
$messages = array(
    'saved'      => array('success', 'Purchase order saved.'),
    'sent'       => array('success', 'Purchase order marked as sent. It is now locked.'),
    'locked'     => array('error', 'This purchase order has been sent and can no longer be edited.'),
    'nosupplier' => array('error', 'Select a supplier before marking the purchase order as sent. Your other changes were not saved.'),
    'noitems'    => array('error', 'A purchase order needs at least one line with a name and a quantity above 0. Nothing was saved.'),
    'supplier'   => array('error', 'That supplier could not be found.'),
    'security'   => array('error', 'Security check failed. Please reload the page and try again.'),
    'notfound'   => array('error', 'Purchase order not found.'),
    'error'      => array('error', 'The purchase order could not be saved.'),
);
$msg_detail = '';
if ($msg_key === 'error') {
    $msg_detail = (string) get_transient('vwpm_notice_detail_' . get_current_user_id());
    delete_transient('vwpm_notice_detail_' . get_current_user_id());
}

$po = $po_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $po_id)) : null;
?>
<div class="wrap vwpm-wrap">
    <h1>Purchase Orders</h1>

    <?php if ($msg_key !== '' && isset($messages[$msg_key])) : ?>
        <div class="notice notice-<?php echo esc_attr($messages[$msg_key][0]); ?>">
            <p>
                <?php echo esc_html($messages[$msg_key][1]); ?>
                <?php if ($msg_detail !== '') : ?>
                    <br><code><?php echo esc_html($msg_detail); ?></code>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

<?php if ($po_id && !$po) : ?>

    <div class="vwpm-card">
        <p>Purchase order not found.</p>
        <p><a class="button" href="<?php echo esc_url($list_url); ?>">Back to Purchase Orders</a></p>
    </div>

<?php elseif ($po) : ?>
    <?php
    $items = json_decode((string) $po->items, true);
    if (!is_array($items)) {
        $items = array();
    }
    $locked    = ((int) $po->is_locked === 1 || $po->status === 'sent');
    $print_url = admin_url('admin.php?vwpm_print_po_id=' . (int) $po->id);
    $suppliers = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}vwpm_suppliers ORDER BY name ASC");
    ?>
    <div class="vwpm-card">
        <h2><?php echo esc_html($po->po_number); ?> &mdash; <?php echo $locked ? 'Sent (locked)' : 'Draft (editable)'; ?></h2>
        <p>
            <a class="button" href="<?php echo esc_url($list_url); ?>">&larr; Back to Purchase Orders</a>
            <a class="button" href="<?php echo esc_url($print_url); ?>" target="_blank" rel="noopener">Print / Save as PDF</a>
        </p>

    <?php if ($locked) : ?>
        <p><strong>Supplier:</strong> <?php echo esc_html($po->supplier_name ?: 'Unassigned'); ?></p>
        <table class="widefat striped">
            <thead>
                <tr><th>Item</th><th>Part Number</th><th>Supplier Ref</th><th>Qty</th><th>Unit Price</th><th>Line Total</th></tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item) :
                    $qty   = isset($item['qty']) ? (float) $item['qty'] : 0;
                    $price = isset($item['unit_price']) ? (float) $item['unit_price'] : 0;
                    ?>
                    <tr>
                        <td><?php echo esc_html(isset($item['name']) ? $item['name'] : ''); ?></td>
                        <td><?php echo esc_html(isset($item['part_number']) ? $item['part_number'] : ''); ?></td>
                        <td><?php echo esc_html(isset($item['supplier_ref']) ? $item['supplier_ref'] : ''); ?></td>
                        <td><?php echo esc_html(rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.')); ?></td>
                        <td><?php echo esc_html('£' . number_format($price, 2)); ?></td>
                        <td><?php echo esc_html('£' . number_format($qty * $price, 2)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="5" style="text-align:right;"><strong>Total (ex VAT)</strong></td><td><strong><?php echo esc_html('£' . number_format((float) $po->total_cost, 2)); ?></strong></td></tr>
            </tfoot>
        </table>

    <?php else : ?>
        <form method="post" id="vwpm-po-edit-form">
            <?php wp_nonce_field('vwpm_update_po_' . (int) $po->id, 'vwpm_po_update_nonce'); ?>
            <input type="hidden" name="vwpm_po_id" value="<?php echo esc_attr((string) $po->id); ?>">

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="vwpm_po_supplier">Supplier</label></th>
                    <td>
                        <select id="vwpm_po_supplier" name="vwpm_po_supplier" class="regular-text">
                            <option value="">No supplier assigned</option>
                            <?php foreach ($suppliers as $supplier) : ?>
                                <option value="<?php echo esc_attr((string) $supplier->id); ?>" <?php selected((int) $po->supplier_id, (int) $supplier->id); ?>><?php echo esc_html($supplier->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <table class="widefat">
                <thead>
                    <tr>
                        <th style="width: 30%;">Item</th>
                        <th style="width: 15%;">Part Number</th>
                        <th style="width: 15%;">Supplier Ref</th>
                        <th style="width: 10%;">Qty</th>
                        <th style="width: 14%;">Unit Price (ex VAT)</th>
                        <th style="width: 16%;">Action</th>
                    </tr>
                </thead>
                <tbody id="vwpm-po-lines">
                    <?php foreach ($items as $index => $item) : ?>
                        <tr>
                            <td><input type="text" name="vwpm_po_items[<?php echo (int) $index; ?>][name]" value="<?php echo esc_attr(isset($item['name']) ? $item['name'] : ''); ?>" style="width: 100%;"></td>
                            <td><input type="text" name="vwpm_po_items[<?php echo (int) $index; ?>][part_number]" value="<?php echo esc_attr(isset($item['part_number']) ? $item['part_number'] : ''); ?>" style="width: 100%;"></td>
                            <td><input type="text" name="vwpm_po_items[<?php echo (int) $index; ?>][supplier_ref]" value="<?php echo esc_attr(isset($item['supplier_ref']) ? $item['supplier_ref'] : ''); ?>" style="width: 100%;"></td>
                            <td><input type="number" step="any" min="0" class="vwpm-po-qty" name="vwpm_po_items[<?php echo (int) $index; ?>][qty]" value="<?php echo esc_attr((string) (isset($item['qty']) ? $item['qty'] : 0)); ?>" style="width: 100%;"></td>
                            <td><input type="number" step="0.01" min="0" class="vwpm-po-price" name="vwpm_po_items[<?php echo (int) $index; ?>][unit_price]" value="<?php echo esc_attr(number_format(isset($item['unit_price']) ? (float) $item['unit_price'] : 0, 2, '.', '')); ?>" style="width: 100%;"></td>
                            <td><button type="button" class="button vwpm-po-remove-line">Remove</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align:right;"><strong>Total (ex VAT)</strong></td>
                        <td colspan="2"><strong id="vwpm-po-live-total"><?php echo esc_html('£' . number_format((float) $po->total_cost, 2)); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
            <p class="description">Set a quantity to 0 or click Remove to delete a line. The total is recalculated when you save.</p>
            <p><button type="button" class="button" id="vwpm-po-add-line">Add Line</button></p>

            <p>
                <button type="submit" name="vwpm_po_action" value="save" class="button button-primary">Save changes</button>
                <button type="submit" name="vwpm_po_action" value="send" class="button" onclick="return confirm('Mark this purchase order as sent? It will be locked and can no longer be edited.');">Save &amp; mark as sent</button>
            </p>
        </form>

        <script type="text/html" id="vwpm-po-line-template">
            <tr>
                <td><input type="text" name="vwpm_po_items[INDEX][name]" style="width: 100%;"></td>
                <td><input type="text" name="vwpm_po_items[INDEX][part_number]" style="width: 100%;"></td>
                <td><input type="text" name="vwpm_po_items[INDEX][supplier_ref]" style="width: 100%;"></td>
                <td><input type="number" step="any" min="0" class="vwpm-po-qty" name="vwpm_po_items[INDEX][qty]" value="1" style="width: 100%;"></td>
                <td><input type="number" step="0.01" min="0" class="vwpm-po-price" name="vwpm_po_items[INDEX][unit_price]" value="0.00" style="width: 100%;"></td>
                <td><button type="button" class="button vwpm-po-remove-line">Remove</button></td>
            </tr>
        </script>
        <script type="text/javascript">
        jQuery(function ($) {
            var nextIndex = <?php echo (int) count($items) + 1000; ?>;

            function recalc() {
                var total = 0;
                $('#vwpm-po-lines tr').each(function () {
                    var q = parseFloat($(this).find('.vwpm-po-qty').val());
                    var p = parseFloat($(this).find('.vwpm-po-price').val());
                    total += (isNaN(q) ? 0 : q) * (isNaN(p) ? 0 : p);
                });
                $('#vwpm-po-live-total').text('£' + total.toFixed(2));
            }

            $('#vwpm-po-add-line').on('click', function (e) {
                e.preventDefault();
                $('#vwpm-po-lines').append($('#vwpm-po-line-template').html().replace(/INDEX/g, nextIndex++));
                recalc();
            });
            $(document).on('click', '.vwpm-po-remove-line', function (e) {
                e.preventDefault();
                $(this).closest('tr').remove();
                recalc();
            });
            $(document).on('input', '.vwpm-po-qty, .vwpm-po-price', recalc);
        });
        </script>
    <?php endif; ?>
    </div>

<?php else : ?>
    <?php $purchase_orders = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100"); ?>
    <div class="vwpm-card">
        <p>Draft purchase orders can be edited until they are marked as sent. Create them from the Production Calculator or the Create Custom PO page.</p>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>PO Number</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th>Total (ex VAT)</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($purchase_orders)) : ?>
                    <?php foreach ($purchase_orders as $row) :
                        $row_locked = ((int) $row->is_locked === 1 || $row->status === 'sent');
                        ?>
                        <tr>
                            <td><?php echo esc_html($row->po_number); ?></td>
                            <td><?php echo esc_html($row->supplier_name ?: 'Unassigned'); ?></td>
                            <td><?php echo $row_locked ? 'Sent' : 'Draft'; ?></td>
                            <td><?php echo esc_html('£' . number_format((float) $row->total_cost, 2)); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($row->created_at))); ?></td>
                            <td>
                                <a class="button button-small" href="<?php echo esc_url(add_query_arg('po_id', (int) $row->id, $list_url)); ?>"><?php echo $row_locked ? 'View' : 'Edit'; ?></a>
                                <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?vwpm_print_po_id=' . (int) $row->id)); ?>" target="_blank" rel="noopener">Print</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6">No purchase orders found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>


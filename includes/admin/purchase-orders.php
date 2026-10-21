<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'vwpm_pos';
$purchase_orders = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 20");
?>
<div class="wrap vwpm-wrap">
    <h1>Purchase Orders</h1>

    <div class="vwpm-card">
        <p>Review saved purchase orders and their latest status before printing or finalising them.</p>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>PO Number</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($purchase_orders)) : ?>
                    <?php foreach ($purchase_orders as $po) : ?>
                        <tr>
                            <td><?php echo esc_html($po->po_number); ?></td>
                            <td><?php echo esc_html($po->supplier_name ?: 'Unassigned'); ?></td>
                            <td><?php echo esc_html(ucfirst($po->status)); ?></td>
                            <td><?php echo esc_html('£' . number_format((float) $po->total_cost, 2)); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($po->created_at))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5">No purchase orders found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

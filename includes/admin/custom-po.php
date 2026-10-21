<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}vwpm_pos ORDER BY created_at DESC LIMIT 50");
?>
<div class="wrap vwpm-wrap">
    <h1>Purchase Orders</h1>

    <div class="vwpm-card">
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>PO Number</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($results)) : ?>
                    <?php foreach ($results as $row) : ?>
                        <tr>
                            <td><?php echo esc_html($row->po_number); ?></td>
                            <td><?php echo esc_html($row->supplier_name ?: 'N/A'); ?></td>
                            <td><?php echo esc_html($row->status); ?></td>
                            <td>£<?php echo esc_html(number_format((float) $row->total_cost, 2)); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime((string) $row->created_at))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="5">No purchase orders found yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

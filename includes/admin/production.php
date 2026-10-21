<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'vwpm_suppliers';
$suppliers = $wpdb->get_results("SELECT * FROM {$table} ORDER BY name ASC");
?>
<div class="wrap vwpm-wrap">
    <h1>Suppliers</h1>

    <div class="vwpm-card">
        <form method="post">
            <?php wp_nonce_field('vwpm_supplier_save', 'vwpm_supplier_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="vwpm_supplier_name">Supplier name</label></th>
                    <td><input id="vwpm_supplier_name" name="vwpm_supplier_name" type="text" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="vwpm_supplier_email">Email</label></th>
                    <td><input id="vwpm_supplier_email" name="vwpm_supplier_email" type="email" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="vwpm_supplier_contact">Contact details</label></th>
                    <td><textarea id="vwpm_supplier_contact" name="vwpm_supplier_contact" rows="5" class="large-text"></textarea></td>
                </tr>
            </table>
            <p><button type="submit" class="button button-primary">Save Supplier</button></p>
        </form>
    </div>

    <?php if (!empty($_POST['vwpm_supplier_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vwpm_supplier_nonce'])), 'vwpm_supplier_save') && current_user_can('manage_options')) :
        $name = sanitize_text_field(wp_unslash($_POST['vwpm_supplier_name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['vwpm_supplier_email'] ?? ''));
        $contact = sanitize_textarea_field(wp_unslash($_POST['vwpm_supplier_contact'] ?? ''));
        if ($name) {
            $wpdb->insert(
                $table,
                array(
                    'name' => $name,
                    'email' => $email,
                    'contact_details' => $contact,
                ),
                array('%s', '%s', '%s')
            );
            echo '<div class="notice notice-success"><p>Supplier saved.</p></div>';
        }
    endif; ?>

    <div class="vwpm-card">
        <h2>Current Suppliers</h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Contact Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($suppliers)) : ?>
                    <?php foreach ($suppliers as $supplier) : ?>
                        <tr>
                            <td><?php echo esc_html($supplier->name); ?></td>
                            <td><?php echo esc_html($supplier->email ?: '-'); ?></td>
                            <td><?php echo esc_html($supplier->contact_details ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="3">No suppliers found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

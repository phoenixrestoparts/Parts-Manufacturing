<?php
/**
 * Form handlers for admin pages (suppliers and custom purchase orders).
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_init', 'vwpm_handle_supplier_save');
add_action('admin_init', 'vwpm_handle_custom_po_create');

function vwpm_handle_supplier_save() {
    if (empty($_POST['vwpm_supplier_action']) || $_POST['vwpm_supplier_action'] !== 'save') {
        return;
    }

    $redirect = admin_url('admin.php?page=vwpm-suppliers');

    $nonce = isset($_POST['vwpm_supplier_nonce']) ? sanitize_text_field(wp_unslash($_POST['vwpm_supplier_nonce'])) : '';
    if (!current_user_can('manage_options') || !wp_verify_nonce($nonce, 'vwpm_save_supplier')) {
        wp_safe_redirect(add_query_arg('vwpm_notice', 'security', $redirect));
        exit;
    }

    $name    = isset($_POST['vwpm_supplier_name']) ? sanitize_text_field(wp_unslash($_POST['vwpm_supplier_name'])) : '';
    $email   = isset($_POST['vwpm_supplier_email']) ? sanitize_email(wp_unslash($_POST['vwpm_supplier_email'])) : '';
    $contact = isset($_POST['vwpm_supplier_contact']) ? sanitize_textarea_field(wp_unslash($_POST['vwpm_supplier_contact'])) : '';

    if ($name === '') {
        wp_safe_redirect(add_query_arg('vwpm_notice', 'name', $redirect));
        exit;
    }

    global $wpdb;
    $inserted = $wpdb->insert(
        $wpdb->prefix . 'vwpm_suppliers',
        array(
            'name'            => $name,
            'email'           => $email,
            'contact_details' => $contact,
        ),
        array('%s', '%s', '%s')
    );

    if (false === $inserted) {
        set_transient('vwpm_notice_detail_' . get_current_user_id(), (string) $wpdb->last_error, 60);
        wp_safe_redirect(add_query_arg('vwpm_notice', 'error', $redirect));
        exit;
    }

    wp_safe_redirect(add_query_arg('vwpm_notice', 'saved', $redirect));
    exit;
}

function vwpm_handle_custom_po_create() {
    if (empty($_POST['vwpm_custom_po_action']) || $_POST['vwpm_custom_po_action'] !== 'create') {
        return;
    }

    $redirect = admin_url('admin.php?page=vwpm-custom-po');

    $nonce = isset($_POST['vwpm_custom_po_nonce']) ? sanitize_text_field(wp_unslash($_POST['vwpm_custom_po_nonce'])) : '';
    if (!current_user_can('manage_options') || !wp_verify_nonce($nonce, 'vwpm_create_custom_po')) {
        wp_safe_redirect(add_query_arg('vwpm_po_notice', 'security', $redirect));
        exit;
    }

    global $wpdb;
    $suppliers_table = $wpdb->prefix . 'vwpm_suppliers';

    $supplier_id = isset($_POST['vwpm_custom_po_supplier']) ? absint($_POST['vwpm_custom_po_supplier']) : 0;
    $supplier    = $supplier_id ? $wpdb->get_row($wpdb->prepare("SELECT id, name, email FROM {$suppliers_table} WHERE id = %d", $supplier_id)) : null;
    if (!$supplier) {
        wp_safe_redirect(add_query_arg('vwpm_po_notice', 'supplier', $redirect));
        exit;
    }

    $raw_items = (isset($_POST['vwpm_po_items']) && is_array($_POST['vwpm_po_items'])) ? wp_unslash($_POST['vwpm_po_items']) : array();
    $items = array();
    $total_cost = 0;
    foreach ($raw_items as $row) {
        if (!is_array($row)) {
            continue;
        }
        $item_name = isset($row['name']) ? sanitize_text_field($row['name']) : '';
        if ($item_name === '') {
            continue;
        }
        $qty   = isset($row['qty']) ? floatval($row['qty']) : 0;
        $price = isset($row['unit_price']) ? floatval($row['unit_price']) : 0;
        $items[] = array(
            'name'         => $item_name,
            'part_number'  => isset($row['part_number']) ? sanitize_text_field($row['part_number']) : '',
            'supplier_ref' => isset($row['supplier_ref']) ? sanitize_text_field($row['supplier_ref']) : '',
            'qty'          => $qty,
            'unit_price'   => $price,
        );
        $total_cost += $qty * $price;
    }

    if (empty($items)) {
        wp_safe_redirect(add_query_arg('vwpm_po_notice', 'items', $redirect));
        exit;
    }

    $reference = isset($_POST['vwpm_custom_po_reference']) ? sanitize_text_field(wp_unslash($_POST['vwpm_custom_po_reference'])) : '';
    $notes     = isset($_POST['vwpm_custom_po_notes']) ? sanitize_textarea_field(wp_unslash($_POST['vwpm_custom_po_notes'])) : '';

    $po_number = 'PO-' . date('Ymd') . '-' . strtoupper(wp_generate_password(5, false, false));
    $summary   = array(
        array(
            'title'    => ($reference !== '') ? $reference : 'Custom PO',
            'quantity' => 1,
            'notes'    => $notes,
        ),
    );

    $inserted = $wpdb->insert(
        $wpdb->prefix . 'vwpm_pos',
        array(
            'po_number'       => $po_number,
            'user_id'         => get_current_user_id(),
            'supplier_id'     => (int) $supplier->id,
            'supplier_name'   => $supplier->name,
            'supplier_email'  => (string) $supplier->email,
            'items'           => wp_json_encode($items),
            'tools'           => wp_json_encode(array()),
            'product_summary' => wp_json_encode($summary),
            'total_cost'      => $total_cost,
            'status'          => 'prepared',
            'created_at'      => current_time('mysql'),
            'updated_at'      => current_time('mysql'),
        ),
        array('%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s')
    );

    if (false === $inserted) {
        set_transient('vwpm_notice_detail_' . get_current_user_id(), (string) $wpdb->last_error, 60);
        wp_safe_redirect(add_query_arg('vwpm_po_notice', 'error', $redirect));
        exit;
    }

    // Lets the existing print handler (?vwpm_print_po=1) render this PO straight away.
    set_transient(
        'vwpm_po_' . get_current_user_id(),
        array(
            'po_number'       => $po_number,
            'supplier_id'     => (int) $supplier->id,
            'supplier_name'   => $supplier->name,
            'supplier_email'  => (string) $supplier->email,
            'items'           => $items,
            'tools'           => array(),
            'product_summary' => $summary,
            'product_name'    => $reference,
            'type'            => 'custom',
            'status'          => 'prepared',
            'total_cost'      => $total_cost,
        ),
        HOUR_IN_SECONDS * 12
    );

    wp_safe_redirect(add_query_arg('vwpm_po_created', rawurlencode($po_number), $redirect));
    exit;
}

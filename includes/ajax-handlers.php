<?php
/**
 * AJAX handlers.
 */

if (!defined('ABSPATH')) {
    exit;
}

function vwpm_require_ajax_nonce($nonce_action = 'vwpm_nonce') {
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => 'You do not have permission to do this.'));
    }
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, $nonce_action)) {
        wp_send_json_error(array('message' => 'Security check failed. Please refresh and try again.'));
    }
}

function vwpm_get_supplier_table() {
    global $wpdb;

    return $wpdb->prefix . 'vwpm_suppliers';
}

function vwpm_get_product_options_html($type = 'manufactured') {
    $products = get_posts(array(
        'post_type' => 'product',
        'post_status' => array('publish', 'draft', 'private'),
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ));

    $html = '<option value="">Select product</option>';
    foreach ($products as $product) {
        $sku = get_post_meta($product->ID, '_sku', true);
        $label = $sku ? $sku . ' - ' . $product->post_title : $product->post_title;
        $html .= '<option value="' . esc_attr($product->ID) . '">' . esc_html($label) . '</option>';
    }

    return $html;
}

function vwpm_get_component_data($component_id) {
    $component_id = absint($component_id);
    if (!$component_id) {
        return array();
    }

    $post = get_post($component_id);
    if (!$post || 'vwpm_component' !== $post->post_type) {
        return array();
    }

    return array(
        'id' => $component_id,
        'name' => $post->post_title,
        'number' => get_post_meta($component_id, '_vwpm_component_number', true),
        'supplier_id' => get_post_meta($component_id, '_vwpm_supplier_id', true),
        'supplier_ref' => get_post_meta($component_id, '_vwpm_component_supplier_ref', true),
        'price' => get_post_meta($component_id, '_vwpm_price', true),
        'location' => get_post_meta($component_id, '_vwpm_component_location', true),
    );
}

function vwpm_get_tool_data($tool_id) {
    $tool_id = absint($tool_id);
    if (!$tool_id) {
        return array();
    }

    $post = get_post($tool_id);
    if (!$post || 'vwpm_tool' !== $post->post_type) {
        return array();
    }

    return array(
        'id' => $tool_id,
        'name' => $post->post_title,
        'number' => get_post_meta($tool_id, '_vwpm_tool_number', true),
        'location' => get_post_meta($tool_id, '_vwpm_location', true),
        'notes' => get_post_meta($tool_id, '_vwpm_notes', true),
    );
}

function vwpm_get_supplier_name($supplier_id) {
    $supplier_id = absint($supplier_id);
    if (!$supplier_id) {
        return '';
    }

    global $wpdb;
    $table = vwpm_get_supplier_table();

    return (string) $wpdb->get_var($wpdb->prepare("SELECT name FROM {$table} WHERE id = %d", $supplier_id));
}

function vwpm_get_product_bom_items($product_id) {
    $bom = get_post_meta((int) $product_id, '_vwpm_bom', true);

    return is_array($bom) ? $bom : array();
}

function vwpm_get_product_tools($product_id) {
    $tools = get_post_meta((int) $product_id, '_vwpm_tools', true);

    return is_array($tools) ? $tools : array();
}

function vwpm_get_fallback_supplier() {
    global $wpdb;
    $table = vwpm_get_supplier_table();
    $supplier = $wpdb->get_row("SELECT * FROM {$table} ORDER BY name ASC LIMIT 1", ARRAY_A);

    if (!empty($supplier['id'])) {
        return $supplier;
    }

    return array(
        'id' => 0,
        'name' => 'General Supplier',
        'email' => '',
    );
}

function vwpm_calculate_product_requirements_html($products, $vat_enabled = true) {
    $products = is_array($products) ? $products : array();
    $supplier_groups = array();
    $tool_summary = array();

    foreach ($products as $product_input) {
        $product_id = absint(!empty($product_input['product_id']) ? $product_input['product_id'] : 0);
        $quantity = floatval(!empty($product_input['quantity']) ? $product_input['quantity'] : 1);
        if (!$product_id) {
            continue;
        }

        $bom = vwpm_get_product_bom_items($product_id);
        foreach ($bom as $row) {
            $component_id = absint(!empty($row['component_id']) ? $row['component_id'] : 0);
            if (!$component_id) {
                continue;
            }

            $component = vwpm_get_component_data($component_id);
            if (empty($component['id'])) {
                continue;
            }

            $supplier_id = absint(!empty($component['supplier_id']) ? $component['supplier_id'] : 0);
            if (!$supplier_id) {
                $fallback = vwpm_get_fallback_supplier();
                $supplier_id = absint($fallback['id']);
            }

            $unit_price = floatval(!empty($component['price']) ? $component['price'] : 0);
            $qty_per_unit = floatval(!empty($row['quantity']) ? $row['quantity'] : 1);
            $total_qty = $qty_per_unit * $quantity;
            $line_total = $total_qty * $unit_price;

            if (!isset($supplier_groups[$supplier_id])) {
                $supplier_groups[$supplier_id] = array(
                    'supplier_id' => $supplier_id,
                    'supplier_name' => $supplier_id ? vwpm_get_supplier_name($supplier_id) : vwpm_get_fallback_supplier()['name'],
                    'items' => array(),
                    'subtotal' => 0,
                );
            }

            $supplier_groups[$supplier_id]['items'][] = array(
                'component_id' => $component_id,
                'component_name' => $component['name'],
                'component_number' => $component['number'],
                'supplier_ref' => $component['supplier_ref'],
                'qty' => $total_qty,
                'unit_price' => $unit_price,
                'line_total' => $line_total,
                'qty_per_unit' => $qty_per_unit,
            );

            $supplier_groups[$supplier_id]['subtotal'] += $line_total;
        }

        foreach (vwpm_get_product_tools($product_id) as $tool_id) {
            $tool = vwpm_get_tool_data($tool_id);
            if (empty($tool)) {
                continue;
            }

            $key = strtolower((string) ($tool['name'] ?? '') . '|' . (string) ($tool['number'] ?? '') . '|' . (string) ($tool['location'] ?? ''));
            if (!isset($tool_summary[$key])) {
                $tool_summary[$key] = array(
                    'name' => $tool['name'] ?? '',
                    'number' => $tool['number'] ?? '',
                    'location' => $tool['location'] ?? '',
                );
            }
        }
    }

    $html = '';
    if (!empty($supplier_groups)) {
        foreach ($supplier_groups as $supplier) {
            $subtotal = (float) $supplier['subtotal'];
            $vat = $vat_enabled ? $subtotal * 0.2 : 0;
            $grand_total = $subtotal + $vat;

            $html .= '<div class="vwpm-supplier-block">';
            $html .= '<h3>' . esc_html($supplier['supplier_name']) . '</h3>';
            $html .= '<table class="vwpm-results-table">';
            $html .= '<thead><tr><th>Item</th><th>Part Number</th><th>Supplier Ref</th><th>Qty</th><th>Unit Price</th><th>Line Total</th></tr></thead>';
            $html .= '<tbody>';

            foreach ($supplier['items'] as $item) {
                $html .= '<tr>';
                $html .= '<td>' . esc_html($item['component_name']) . '</td>';
                $html .= '<td>' . esc_html($item['component_number']) . '</td>';
                $html .= '<td>' . esc_html($item['supplier_ref'] ?: '-') . '</td>';
                $html .= '<td>' . esc_html((string) $item['qty']) . '</td>';
                $html .= '<td>£' . esc_html(number_format((float) $item['unit_price'], 2)) . '</td>';
                $html .= '<td>£' . esc_html(number_format((float) $item['line_total'], 2)) . '</td>';
                $html .= '</tr>';
            }

            $html .= '<tr class="vwpm-subtotal-row"><td colspan="5" style="text-align:right;">Subtotal</td><td>£' . esc_html(number_format($subtotal, 2)) . '</td></tr>';
            $html .= '<tr class="vwpm-vat-row"><td colspan="5" style="text-align:right;">VAT</td><td>£' . esc_html(number_format($vat, 2)) . '</td></tr>';
            $html .= '<tr class="vwpm-grand-total-row"><td colspan="5" style="text-align:right; font-weight:bold;">Grand Total</td><td style="font-weight:bold;">£' . esc_html(number_format($grand_total, 2)) . '</td></tr>';
            $html .= '</tbody></table>';
            $html .= '</div>';
        }
    }

    if (!empty($tool_summary)) {
        $html .= '<div class="vwpm-tool-summary">';
        $html .= '<h3>Tools Required</h3>';
        $html .= '<table class="vwpm-results-table"><thead><tr><th>Tool Name</th><th>Tool Number</th><th>Location</th></tr></thead><tbody>';
        foreach ($tool_summary as $tool) {
            $html .= '<tr><td>' . esc_html($tool['name']) . '</td><td>' . esc_html($tool['number']) . '</td><td>' . esc_html($tool['location']) . '</td></tr>';
        }
        $html .= '</tbody></table>';
        $html .= '</div>';
    }

    if (empty($supplier_groups) && empty($tool_summary)) {
        $html .= '<div class="notice notice-warning"><p>No BOM or tools were found for the selected product.</p></div>';
    }

    return $html;
}

add_action('wp_ajax_vwpm_get_products_by_type', function () {
    vwpm_require_ajax_nonce();
    wp_send_json_success(array('options' => vwpm_get_product_options_html(isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : 'manufactured')));
});

add_action('wp_ajax_vwpm_calculate_production', function () {
    vwpm_require_ajax_nonce();

    $products = array();
    if (!empty($_POST['products']) && is_array($_POST['products'])) {
        $products = array_map(function ($product) {
            return array(
                'product_id' => absint(!empty($product['product_id']) ? $product['product_id'] : 0),
                'quantity' => floatval(!empty($product['quantity']) ? $product['quantity'] : 1),
            );
        }, wp_unslash($_POST['products']));
    }

    if (!empty($_POST['product_id'])) {
        $products[] = array(
            'product_id' => absint($_POST['product_id']),
            'quantity' => floatval(!empty($_POST['quantity']) ? $_POST['quantity'] : 1),
        );
    }

    $products = array_values(array_filter($products, function ($product) {
        return !empty($product['product_id']);
    }));

    if (empty($products)) {
        wp_send_json_error(array('message' => 'No valid product supplied.'));
    }

    wp_send_json_success(array(
        'html' => vwpm_calculate_product_requirements_html($products, !empty($_POST['vat_enabled'])),
    ));
});

add_action('wp_ajax_vwpm_search_suppliers', function () {
    vwpm_require_ajax_nonce();

    $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
    if (strlen($query) < 2) {
        wp_send_json_success(array());
    }

    global $wpdb;
    $table = vwpm_get_supplier_table();
    $like = '%' . $wpdb->esc_like($query) . '%';

    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, name, email FROM {$table} WHERE name LIKE %s OR email LIKE %s ORDER BY name ASC LIMIT 20",
            $like,
            $like
        ),
        ARRAY_A
    );

    wp_send_json_success($results ?: array());
});

add_action('wp_ajax_vwpm_save_po_selection', function () {
    vwpm_require_ajax_nonce();

    $supplier_id = absint($_POST['supplier_id'] ?? 0);
    $items = isset($_POST['items']) && is_array($_POST['items']) ? array_map('wp_unslash', $_POST['items']) : array();
    $tools = isset($_POST['tools']) && is_array($_POST['tools']) ? array_map('wp_unslash', $_POST['tools']) : array();
    $product_summary = isset($_POST['products']) && is_array($_POST['products']) ? array_map('wp_unslash', $_POST['products']) : array();

    $data = array(
        'supplier_id' => $supplier_id,
        'supplier_name' => vwpm_get_supplier_name($supplier_id),
        'supplier_email' => '',
        'items' => $items,
        'tools' => $tools,
        'product_summary' => $product_summary,
        'type' => isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : 'manufactured',
        'status' => 'prepared',
        'total_cost' => 0,
        'created_at' => current_time('mysql'),
    );

    if ($supplier_id) {
        global $wpdb;
        $table = vwpm_get_supplier_table();
        $data['supplier_email'] = (string) $wpdb->get_var($wpdb->prepare("SELECT email FROM {$table} WHERE id = %d", $supplier_id));
    }

    set_transient('vwpm_po_' . get_current_user_id(), $data, HOUR_IN_SECONDS * 12);

    wp_send_json_success(array('message' => 'PO selection saved.'));
});

add_action('wp_ajax_vwpm_create_po_from_transient', function () {
    vwpm_require_ajax_nonce();

    $po_data = get_transient('vwpm_po_' . get_current_user_id());
    if (!is_array($po_data) || empty($po_data)) {
        wp_send_json_error(array('message' => 'No purchase order data found. Please generate one again.'));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'vwpm_pos';

    $po_number = 'PO-' . date('Ymd') . '-' . wp_generate_uuid4();
    $inserted = $wpdb->insert(
        $table,
        array(
            'po_number' => $po_number,
            'user_id' => get_current_user_id(),
            'supplier_id' => absint($po_data['supplier_id'] ?? 0),
            'supplier_name' => sanitize_text_field($po_data['supplier_name'] ?? ''),
            'supplier_email' => sanitize_email($po_data['supplier_email'] ?? ''),
            'items' => wp_json_encode($po_data['items'] ?? array()),
            'tools' => wp_json_encode($po_data['tools'] ?? array()),
            'product_summary' => wp_json_encode($po_data['product_summary'] ?? array()),
            'total_cost' => floatval($po_data['total_cost'] ?? 0),
            'status' => 'prepared',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ),
        array('%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s')
    );

    if (false === $inserted) {
        wp_send_json_error(array('message' => 'Unable to save the purchase order.'));
    }

    delete_transient('vwpm_po_' . get_current_user_id());

    wp_send_json_success(array('po_number' => $po_number));
});


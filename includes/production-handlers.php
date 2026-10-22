<?php
/**
 * Production calculator AJAX handlers and purchase order helpers.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_vwpm_prod_search_products', 'vwpm_ajax_prod_search_products');
add_action('wp_ajax_vwpm_prod_calculate', 'vwpm_ajax_prod_calculate');
add_action('wp_ajax_vwpm_prod_export', 'vwpm_ajax_prod_export');

function vwpm_new_po_number() {
    return 'PO-' . date('Ymd') . '-' . strtoupper(wp_generate_password(5, false, false));
}

/**
 * Returns array( supplier_id => array( 'name' => ..., 'email' => ... ) ).
 */
function vwpm_get_suppliers_map() {
    global $wpdb;

    $rows = $wpdb->get_results("SELECT id, name, email FROM {$wpdb->prefix}vwpm_suppliers ORDER BY name ASC");
    $map = array();
    if (is_array($rows)) {
        foreach ($rows as $row) {
            $map[(int) $row->id] = array(
                'name'  => (string) $row->name,
                'email' => (string) $row->email,
            );
        }
    }

    return $map;
}

function vwpm_read_json_payload() {
    $raw = isset($_POST['payload']) ? wp_unslash($_POST['payload']) : '';
    $data = json_decode((string) $raw, true);

    return is_array($data) ? $data : array();
}

/**
 * Cleans a list of PO lines. Lines without a name, or with a quantity of 0 or less, are dropped.
 * Returns array( $items, $total_ex_vat ).
 */
function vwpm_parse_po_items($raw) {
    $items = array();
    $total = 0;

    if (!is_array($raw)) {
        return array($items, $total);
    }

    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }

        $name = isset($row['name']) ? sanitize_text_field($row['name']) : '';
        $qty  = isset($row['qty']) ? floatval($row['qty']) : 0;
        if ($name === '' || $qty <= 0) {
            continue;
        }

        $price = isset($row['unit_price']) ? max(0, floatval($row['unit_price'])) : 0;

        $items[] = array(
            'name'         => $name,
            'part_number'  => isset($row['part_number']) ? sanitize_text_field($row['part_number']) : '',
            'supplier_ref' => isset($row['supplier_ref']) ? sanitize_text_field($row['supplier_ref']) : '',
            'qty'          => $qty,
            'unit_price'   => $price,
        );
        $total += $qty * $price;
    }

    return array($items, round($total, 2));
}

/**
 * Loads a saved PO in the shape the print template expects.
 */
function vwpm_get_po_print_data($po_id) {
    global $wpdb;

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}vwpm_pos WHERE id = %d", absint($po_id)));
    if (!$row) {
        return false;
    }

    $items   = json_decode((string) $row->items, true);
    $tools   = json_decode((string) $row->tools, true);
    $summary = json_decode((string) $row->product_summary, true);

    return array(
        'po_number'       => $row->po_number,
        'supplier_id'     => (int) $row->supplier_id,
        'supplier_name'   => $row->supplier_name,
        'supplier_email'  => $row->supplier_email,
        'items'           => is_array($items) ? $items : array(),
        'tools'           => is_array($tools) ? $tools : array(),
        'product_summary' => is_array($summary) ? $summary : array(),
        'type'            => 'production',
        'total_cost'      => (float) $row->total_cost,
        'created_at'      => $row->created_at,
    );
}

function vwpm_ajax_prod_search_products() {
    vwpm_require_ajax_nonce();

    $term = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
    if (strlen($term) < 2) {
        wp_send_json_success(array());
    }

    $statuses = array('publish', 'draft', 'private');

    $by_title = get_posts(array(
        'post_type'      => 'product',
        'post_status'    => $statuses,
        's'              => $term,
        'posts_per_page' => 20,
        'fields'         => 'ids',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));

    $by_sku = get_posts(array(
        'post_type'      => 'product',
        'post_status'    => $statuses,
        'posts_per_page' => 20,
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'     => '_sku',
                'value'   => $term,
                'compare' => 'LIKE',
            ),
        ),
    ));

    $ids = array_slice(array_values(array_unique(array_merge($by_sku, $by_title))), 0, 20);

    $results = array();
    foreach ($ids as $id) {
        $results[] = array(
            'id'    => (int) $id,
            'title' => get_the_title($id),
            'sku'   => (string) get_post_meta($id, '_sku', true),
        );
    }

    wp_send_json_success($results);
}

function vwpm_ajax_prod_calculate() {
    vwpm_require_ajax_nonce();

    $payload   = vwpm_read_json_payload();
    $inputs    = (isset($payload['products']) && is_array($payload['products'])) ? $payload['products'] : array();
    $suppliers = vwpm_get_suppliers_map();

    $lines    = array();
    $products = array();
    $tool_ids = array();
    $warnings = array();

    foreach ($inputs as $input) {
        $product_id = isset($input['product_id']) ? absint($input['product_id']) : 0;
        $quantity   = isset($input['quantity']) ? floatval($input['quantity']) : 0;
        if (!$product_id || $quantity <= 0 || get_post_type($product_id) !== 'product') {
            continue;
        }

        $title = get_the_title($product_id);
        $sku   = (string) get_post_meta($product_id, '_sku', true);
        $products[] = array(
            'product_id' => $product_id,
            'title'      => $title,
            'quantity'   => $quantity,
        );

        $has_bom = false;
        $bom = get_post_meta($product_id, '_vwpm_bom', true);
        if (is_array($bom)) {
            foreach ($bom as $row) {
                $component_id = isset($row['component_id']) ? absint($row['component_id']) : 0;
                if (!$component_id) {
                    continue;
                }

                $component = get_post($component_id);
                if (!$component || $component->post_type !== 'vwpm_component') {
                    continue;
                }

                $has_bom = true;
                $per_unit = isset($row['quantity']) ? floatval($row['quantity']) : 1;

                $supplier_id = absint(get_post_meta($component_id, '_vwpm_supplier_id', true));
                if ($supplier_id && !isset($suppliers[$supplier_id])) {
                    $supplier_id = 0;
                }

                $key = 'c' . $component_id;
                if (!isset($lines[$key])) {
                    $lines[$key] = array(
                        'component_id' => $component_id,
                        'supplier_id'  => $supplier_id,
                        'name'         => $component->post_title,
                        'part_number'  => (string) get_post_meta($component_id, '_vwpm_component_number', true),
                        'supplier_ref' => (string) get_post_meta($component_id, '_vwpm_component_supplier_ref', true),
                        'qty'          => 0,
                        'unit_price'   => floatval(get_post_meta($component_id, '_vwpm_price', true)),
                    );
                }
                $lines[$key]['qty'] = round($lines[$key]['qty'] + ($per_unit * $quantity), 4);
            }
        }

        if (!$has_bom) {
            $product_supplier = absint(get_post_meta($product_id, '_vwpm_product_supplier_id', true));
            if ($product_supplier && isset($suppliers[$product_supplier])) {
                // Ready-made item bought complete from the product supplier.
                $key = 'p' . $product_id;
                if (!isset($lines[$key])) {
                    $lines[$key] = array(
                        'component_id' => 0,
                        'supplier_id'  => $product_supplier,
                        'name'         => $title,
                        'part_number'  => $sku,
                        'supplier_ref' => '',
                        'qty'          => 0,
                        'unit_price'   => 0,
                    );
                }
                $lines[$key]['qty'] = round($lines[$key]['qty'] + $quantity, 4);
            } else {
                $warnings[] = $title . ' has no bill of materials and no product supplier, so nothing was added for it.';
            }
        }

        $product_tools = get_post_meta($product_id, '_vwpm_tools', true);
        if (is_array($product_tools)) {
            foreach ($product_tools as $tool_id) {
                $tool_id = absint($tool_id);
                if ($tool_id) {
                    $tool_ids[$tool_id] = $tool_id;
                }
            }
        }
    }

    $lines_out = array_values($lines);
    usort($lines_out, function ($a, $b) use ($suppliers) {
        $a_rank = $a['supplier_id'] ? 0 : 1;
        $b_rank = $b['supplier_id'] ? 0 : 1;
        if ($a_rank !== $b_rank) {
            return $a_rank - $b_rank;
        }
        $a_name = $a['supplier_id'] ? $suppliers[$a['supplier_id']]['name'] : '';
        $b_name = $b['supplier_id'] ? $suppliers[$b['supplier_id']]['name'] : '';
        $cmp = strcasecmp($a_name, $b_name);
        if ($cmp !== 0) {
            return $cmp;
        }

        return strcasecmp($a['part_number'] . ' ' . $a['name'], $b['part_number'] . ' ' . $b['name']);
    });

    $tools_out = array();
    foreach ($tool_ids as $tool_id) {
        $tool = get_post($tool_id);
        if (!$tool || $tool->post_type !== 'vwpm_tool') {
            continue;
        }
        $tools_out[] = array(
            'id'       => $tool_id,
            'name'     => $tool->post_title,
            'number'   => (string) get_post_meta($tool_id, '_vwpm_tool_number', true),
            'location' => (string) get_post_meta($tool_id, '_vwpm_location', true),
        );
    }

    if (empty($products)) {
        wp_send_json_error(array('message' => 'No valid products were supplied.'));
    }

    wp_send_json_success(array(
        'lines'    => $lines_out,
        'products' => $products,
        'tools'    => $tools_out,
        'warnings' => $warnings,
    ));
}

function vwpm_ajax_prod_export() {
    vwpm_require_ajax_nonce();

    global $wpdb;

    $payload   = vwpm_read_json_payload();
    $raw_lines = (isset($payload['lines']) && is_array($payload['lines'])) ? $payload['lines'] : array();
    $suppliers = vwpm_get_suppliers_map();

    $summary = array();
    if (isset($payload['products']) && is_array($payload['products'])) {
        foreach ($payload['products'] as $product) {
            if (!is_array($product)) {
                continue;
            }
            $summary[] = array(
                'product_id' => isset($product['product_id']) ? absint($product['product_id']) : 0,
                'title'      => isset($product['title']) ? sanitize_text_field($product['title']) : '',
                'quantity'   => isset($product['quantity']) ? floatval($product['quantity']) : 0,
            );
        }
    }

    $tools = array();
    if (isset($payload['tools']) && is_array($payload['tools'])) {
        foreach ($payload['tools'] as $tool) {
            if (!is_array($tool)) {
                continue;
            }
            $tools[] = array(
                'name'     => isset($tool['name']) ? sanitize_text_field($tool['name']) : '',
                'number'   => isset($tool['number']) ? sanitize_text_field($tool['number']) : '',
                'location' => isset($tool['location']) ? sanitize_text_field($tool['location']) : '',
            );
        }
    }

    // Group lines by supplier. One PO is created per supplier.
    $groups = array();
    foreach ($raw_lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $supplier_id = isset($line['supplier_id']) ? absint($line['supplier_id']) : 0;
        if ($supplier_id && !isset($suppliers[$supplier_id])) {
            $supplier_id = 0;
        }
        $groups[$supplier_id][] = $line;
    }

    $created = array();
    $errors  = array();

    foreach ($groups as $supplier_id => $group_lines) {
        list($items, $total) = vwpm_parse_po_items($group_lines);
        if (empty($items)) {
            continue;
        }

        $po_number = vwpm_new_po_number();
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'vwpm_pos',
            array(
                'po_number'       => $po_number,
                'user_id'         => get_current_user_id(),
                'supplier_id'     => (int) $supplier_id,
                'supplier_name'   => $supplier_id ? $suppliers[$supplier_id]['name'] : '',
                'supplier_email'  => $supplier_id ? $suppliers[$supplier_id]['email'] : '',
                'items'           => wp_json_encode($items),
                'tools'           => wp_json_encode($tools),
                'product_summary' => wp_json_encode($summary),
                'total_cost'      => $total,
                'status'          => 'prepared',
                'is_locked'       => 0,
                'created_at'      => current_time('mysql'),
                'updated_at'      => current_time('mysql'),
            ),
            array('%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%s', '%s')
        );

        if (false === $inserted) {
            $errors[] = ($supplier_id ? $suppliers[$supplier_id]['name'] : 'No supplier') . ': ' . $wpdb->last_error;
            continue;
        }

        $created[] = array(
            'id'            => (int) $wpdb->insert_id,
            'po_number'     => $po_number,
            'supplier_name' => $supplier_id ? $suppliers[$supplier_id]['name'] : 'No supplier assigned',
            'total'         => $total,
        );
    }

    if (empty($created)) {
        $message = empty($errors) ? 'There were no valid lines to export.' : 'Could not save the purchase orders. ' . implode(' | ', $errors);
        wp_send_json_error(array('message' => $message));
    }

    wp_send_json_success(array(
        'created' => $created,
        'errors'  => $errors,
    ));
}

<?php
/**
 * Plugin Name: VW Parts Manufacturing Manager
 * Description: Manage components, tools, BOMs, suppliers, and purchase orders for VW parts manufacturing
 * Version: 1.1.1
 * Author: Custom Development
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('VWPM_PLUGIN_DIR')) {
    define('VWPM_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('VWPM_PLUGIN_URL')) {
    define('VWPM_PLUGIN_URL', plugin_dir_url(__FILE__));
}

require_once VWPM_PLUGIN_DIR . 'includes/ajax-handlers.php';

final class VW_Parts_Manager {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('add_meta_boxes_product', array($this, 'add_product_meta_boxes'));
        add_action('save_post_product', array($this, 'save_product_meta'));
        add_action('add_meta_boxes_vwpm_tool', array($this, 'add_tool_meta_boxes'));
        add_action('save_post_vwpm_tool', array($this, 'save_tool_meta'));
        add_action('add_meta_boxes_vwpm_component', array($this, 'add_component_meta_boxes'));
        add_action('save_post_vwpm_component', array($this, 'save_component_meta'));
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $suppliers_table = $wpdb->prefix . 'vwpm_suppliers';
        $sql_suppliers = "CREATE TABLE IF NOT EXISTS {$suppliers_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            email varchar(255) DEFAULT '',
            contact_details text DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";
        dbDelta($sql_suppliers);

        $pos_table = $wpdb->prefix . 'vwpm_pos';
        $sql_pos = "CREATE TABLE IF NOT EXISTS {$pos_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            po_number varchar(80) NOT NULL,
            user_id bigint(20) NOT NULL DEFAULT 0,
            supplier_id bigint(20) NOT NULL DEFAULT 0,
            supplier_name varchar(255) NOT NULL DEFAULT '',
            supplier_email varchar(255) NOT NULL DEFAULT '',
            items longtext DEFAULT NULL,
            tools longtext DEFAULT NULL,
            product_summary longtext DEFAULT NULL,
            total_cost decimal(12,2) NOT NULL DEFAULT 0.00,
            status varchar(50) NOT NULL DEFAULT 'prepared',
            is_locked tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY po_number_unique (po_number)
        ) {$charset_collate};";
        dbDelta($sql_pos);

        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public function init() {
        $this->register_post_types();
    }

    private function register_post_types() {
        register_post_type(
            'vwpm_tool',
            array(
                'labels' => array(
                    'name' => 'Tools',
                    'singular_name' => 'Tool',
                    'menu_name' => 'Tools',
                    'add_new_item' => 'Add New Tool',
                    'edit_item' => 'Edit Tool',
                    'new_item' => 'New Tool',
                    'view_item' => 'View Tool',
                    'search_items' => 'Search Tools',
                ),
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => false,
                'supports' => array('title'),
                'has_archive' => false,
                'capability_type' => 'post',
            )
        );

        register_post_type(
            'vwpm_component',
            array(
                'labels' => array(
                    'name' => 'Components',
                    'singular_name' => 'Component',
                    'menu_name' => 'Components',
                    'add_new_item' => 'Add New Component',
                    'edit_item' => 'Edit Component',
                    'new_item' => 'New Component',
                    'view_item' => 'View Component',
                    'search_items' => 'Search Components',
                ),
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => false,
                'supports' => array('title'),
                'has_archive' => false,
                'capability_type' => 'post',
            )
        );
    }

    public function add_admin_menus() {
        add_menu_page(
            'VW Parts Manager',
            'VW Parts',
            'manage_options',
            'vwpm-dashboard',
            array($this, 'render_dashboard_page'),
            'dashicons-hammer',
            30
        );

        add_submenu_page('vwpm-dashboard', 'Dashboard', 'Dashboard', 'manage_options', 'vwpm-dashboard', array($this, 'render_dashboard_page'));
        add_submenu_page('vwpm-dashboard', 'Suppliers', 'Suppliers', 'manage_options', 'vwpm-suppliers', array($this, 'render_suppliers_page'));
        add_submenu_page('vwpm-dashboard', 'Tools', 'Tools', 'manage_options', 'edit.php?post_type=vwpm_tool');
        add_submenu_page('vwpm-dashboard', 'Components', 'Components', 'manage_options', 'edit.php?post_type=vwpm_component');
        add_submenu_page('vwpm-dashboard', 'Production Calculator', 'Production Calculator', 'manage_options', 'vwpm-production', array($this, 'render_production_page'));
        add_submenu_page('vwpm-dashboard', 'Create Custom PO', 'Create Custom PO', 'manage_options', 'vwpm-custom-po', array($this, 'render_custom_po_page'));
        add_submenu_page('vwpm-dashboard', 'Import/Export', 'Import/Export', 'manage_options', 'vwpm-import-export', array($this, 'render_import_export_page'));
        add_submenu_page('vwpm-dashboard', 'Purchase Orders', 'Purchase Orders', 'manage_options', 'vwpm-purchase-orders', array($this, 'render_purchase_orders_page'));
    }

    public function enqueue_admin_assets($hook_suffix) {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        $is_vwpm = false !== strpos((string) $screen->id, 'vwpm') || false !== strpos((string) $screen->id, 'vw-parts-manager');
        $is_product = ('product' === $screen->id || 'product' === $screen->post_type);
        $is_custom_post_type = in_array($screen->id, array('vwpm_component', 'vwpm_tool'), true);

        if (!$is_vwpm && !$is_product && !$is_custom_post_type) {
            return;
        }

        wp_enqueue_style('vwpm-admin', VWPM_PLUGIN_URL . 'assets/css/admin.css', array(), '1.1.1');
        wp_enqueue_script('vwpm-admin', VWPM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), '1.1.1', true);
        wp_localize_script(
            'vwpm-admin',
            'vwpmAdmin',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vwpm_nonce'),
            )
        );
    }

    public function add_product_meta_boxes() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_meta_box('vwpm_product_bom', 'VW Parts BOM', array($this, 'render_product_bom_meta_box'), 'product', 'normal', 'high');
        add_meta_box('vwpm_product_tools', 'VW Parts Tools', array($this, 'render_product_tools_meta_box'), 'product', 'normal', 'high');
        add_meta_box('vwpm_product_supplier', 'Product Supplier', array($this, 'render_product_supplier_meta_box'), 'product', 'normal', 'high');
    }

    public function render_product_bom_meta_box($post) {
        wp_nonce_field('vwpm_product_meta', 'vwpm_product_nonce');

        $bom = get_post_meta($post->ID, '_vwpm_bom', true);
        if (!is_array($bom)) {
            $bom = array();
        }

        $components = get_posts(array(
            'post_type' => 'vwpm_component',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ));
        ?>
        <p class="description">Add component lines for this product.</p>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Component</th>
                    <th>Qty</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="vwpm-bom-rows">
                <?php if (!empty($bom)) : foreach ($bom as $index => $row) : ?>
                    <tr>
                        <td>
                            <select name="vwpm_bom[<?php echo esc_attr((string) $index); ?>][component_id]" class="vwpm-component-select widefat">
                                <option value="">Select component</option>
                                <?php foreach ($components as $component) : ?>
                                    <option value="<?php echo esc_attr((string) $component->ID); ?>" <?php selected((int) ($row['component_id'] ?? 0), (int) $component->ID); ?>>
                                        <?php echo esc_html($component->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" step="0.01" name="vwpm_bom[<?php echo esc_attr((string) $index); ?>][quantity]" value="<?php echo esc_attr((string) ($row['quantity'] ?? 1)); ?>" class="small-text" /></td>
                        <td><button type="button" class="button vwpm-remove-row">Remove</button></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <p><button type="button" class="button button-primary" id="vwpm-add-bom-row">Add Component</button></p>
        <script type="text/html" id="vwpm-bom-row-template">
            <tr>
                <td>
                    <select name="vwpm_bom[INDEX][component_id]" class="vwpm-component-select widefat">
                        <option value="">Select component</option>
                        <?php foreach ($components as $component) : ?>
                            <option value="<?php echo esc_attr((string) $component->ID); ?>"><?php echo esc_html($component->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" step="0.01" name="vwpm_bom[INDEX][quantity]" value="1" class="small-text" /></td>
                <td><button type="button" class="button vwpm-remove-row">Remove</button></td>
            </tr>
        </script>
        <?php
    }

    public function render_product_tools_meta_box($post) {
        wp_nonce_field('vwpm_product_meta', 'vwpm_product_nonce');

        $selected_tools = get_post_meta($post->ID, '_vwpm_tools', true);
        if (!is_array($selected_tools)) {
            $selected_tools = array();
        }

        $tools = get_posts(array(
            'post_type' => 'vwpm_tool',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ));
        ?>
        <p class="description">Choose the tools required to produce or assemble this product.</p>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Tool</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="vwpm-tools-rows">
                <?php foreach ($selected_tools as $index => $tool_id) : ?>
                    <tr>
                        <td>
                            <select name="vwpm_tools[<?php echo esc_attr((string) $index); ?>]" class="vwpm-tool-select widefat">
                                <option value="">Select tool</option>
                                <?php foreach ($tools as $tool) : ?>
                                    <option value="<?php echo esc_attr((string) $tool->ID); ?>" <?php selected((int) $tool_id, (int) $tool->ID); ?>>
                                        <?php echo esc_html($tool->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><button type="button" class="button vwpm-remove-tool-row">Remove</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><button type="button" class="button button-primary" id="vwpm-add-tool-row">Add Tool</button></p>
        <script type="text/html" id="vwpm-tool-row-template">
            <tr>
                <td>
                    <select name="vwpm_tools[INDEX]" class="vwpm-tool-select widefat">
                        <option value="">Select tool</option>
                        <?php foreach ($tools as $tool) : ?>
                            <option value="<?php echo esc_attr((string) $tool->ID); ?>"><?php echo esc_html($tool->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><button type="button" class="button vwpm-remove-tool-row">Remove</button></td>
            </tr>
        </script>
        <?php
    }

    public function render_product_supplier_meta_box($post) {
        wp_nonce_field('vwpm_product_meta', 'vwpm_product_nonce');

        $supplier_id = get_post_meta($post->ID, '_vwpm_product_supplier_id', true);
        ?>
        <div class="vwpm-supplier-box">
            <label for="vwpm_supplier_search"><strong>Search & Add Supplier</strong></label>
            <input type="text" id="vwpm_supplier_search" class="regular-text" placeholder="Type supplier name..." style="width: 100%; max-width: 420px; margin-top: 8px;" />
            <div id="vwpm_supplier_results" class="vwpm-search-results"></div>
            <div id="vwpm-supplier-item" style="margin-top: 12px;">
                <?php if (!empty($supplier_id)) :
                    global $wpdb;
                    $supplier = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}vwpm_suppliers WHERE id = %d", (int) $supplier_id));
                    if ($supplier) : ?>
                        <div class="vwpm-supplier-selected-item" data-id="<?php echo esc_attr((string) $supplier->id); ?>">
                            <span class="vwpm-supplier-name" style="flex: 1; font-weight: 600;"><?php echo esc_html($supplier->name); ?></span>
                            <button type="button" class="button button-small vwpm-remove-supplier">Remove</button>
                        </div>
                        <input type="hidden" name="vwpm_product_supplier_id" value="<?php echo esc_attr((string) $supplier->id); ?>" />
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function save_product_meta($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($_POST['vwpm_product_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vwpm_product_nonce'])), 'vwpm_product_meta')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['vwpm_bom']) && is_array($_POST['vwpm_bom'])) {
            $bom = array();
            foreach (wp_unslash($_POST['vwpm_bom']) as $row) {
                if (!empty($row['component_id'])) {
                    $bom[] = array(
                        'component_id' => absint($row['component_id']),
                        'quantity' => floatval($row['quantity'] ?? 1),
                    );
                }
            }
            update_post_meta($post_id, '_vwpm_bom', $bom);
        }

        if (isset($_POST['vwpm_tools']) && is_array($_POST['vwpm_tools'])) {
            $tools = array_values(array_filter(array_map('absint', wp_unslash($_POST['vwpm_tools']))));
            update_post_meta($post_id, '_vwpm_tools', $tools);
        }

        if (isset($_POST['vwpm_product_supplier_id'])) {
            update_post_meta($post_id, '_vwpm_product_supplier_id', absint($_POST['vwpm_product_supplier_id']));
        }
    }

    public function add_tool_meta_boxes() {
        add_meta_box('vwpm_tool_details', 'Tool Details', array($this, 'render_tool_meta_box'), 'vwpm_tool', 'normal', 'high');
    }

    public function render_tool_meta_box($post) {
        wp_nonce_field('vwpm_tool_meta', 'vwpm_tool_nonce');
        $tool_number = get_post_meta($post->ID, '_vwpm_tool_number', true);
        $location = get_post_meta($post->ID, '_vwpm_location', true);
        $notes = get_post_meta($post->ID, '_vwpm_notes', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="vwpm_tool_number">Tool Number</label></th>
                <td><input type="text" id="vwpm_tool_number" name="vwpm_tool_number" value="<?php echo esc_attr((string) $tool_number); ?>" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="vwpm_location">Location</label></th>
                <td><input type="text" id="vwpm_location" name="vwpm_location" value="<?php echo esc_attr((string) $location); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="vwpm_notes">Notes</label></th>
                <td><textarea id="vwpm_notes" name="vwpm_notes" rows="4" class="large-text"><?php echo esc_textarea((string) $notes); ?></textarea></td>
            </tr>
        </table>
        <?php
    }

    public function save_tool_meta($post_id) {
        if (!isset($_POST['vwpm_tool_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vwpm_tool_nonce'])), 'vwpm_tool_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['vwpm_tool_number'])) {
            update_post_meta($post_id, '_vwpm_tool_number', sanitize_text_field(wp_unslash($_POST['vwpm_tool_number'])));
        }
        if (isset($_POST['vwpm_location'])) {
            update_post_meta($post_id, '_vwpm_location', sanitize_text_field(wp_unslash($_POST['vwpm_location'])));
        }
        if (isset($_POST['vwpm_notes'])) {
            update_post_meta($post_id, '_vwpm_notes', sanitize_textarea_field(wp_unslash($_POST['vwpm_notes'])));
        }
    }

    public function add_component_meta_boxes() {
        add_meta_box('vwpm_component_details', 'Component Details', array($this, 'render_component_meta_box'), 'vwpm_component', 'normal', 'high');
    }

    public function render_component_meta_box($post) {
        wp_nonce_field('vwpm_component_meta', 'vwpm_component_nonce');

        $component_number = get_post_meta($post->ID, '_vwpm_component_number', true);
        $supplier_id = get_post_meta($post->ID, '_vwpm_supplier_id', true);
        $price = get_post_meta($post->ID, '_vwpm_price', true);
        $notes = get_post_meta($post->ID, '_vwpm_notes', true);
        $location = get_post_meta($post->ID, '_vwpm_component_location', true);
        $supplier_ref = get_post_meta($post->ID, '_vwpm_component_supplier_ref', true);

        $suppliers = $this->get_suppliers();
        ?>
        <table class="form-table">
            <tr>
                <th><label for="vwpm_component_number">Component Number</label></th>
                <td><input type="text" id="vwpm_component_number" name="vwpm_component_number" value="<?php echo esc_attr((string) $component_number); ?>" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="vwpm_supplier_id">Supplier</label></th>
                <td>
                    <select id="vwpm_supplier_id" name="vwpm_supplier_id" class="regular-text">
                        <option value="">Select Supplier</option>
                        <?php foreach ($suppliers as $supplier) : ?>
                            <option value="<?php echo esc_attr((string) $supplier->id); ?>" <?php selected((int) $supplier_id, (int) $supplier->id); ?>>
                                <?php echo esc_html($supplier->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="vwpm_price">Price</label></th>
                <td><input type="number" step="0.01" id="vwpm_price" name="vwpm_price" value="<?php echo esc_attr((string) $price); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="vwpm_component_location">Location</label></th>
                <td><input type="text" id="vwpm_component_location" name="vwpm_component_location" value="<?php echo esc_attr((string) $location); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="vwpm_component_supplier_ref">Supplier Ref</label></th>
                <td><input type="text" id="vwpm_component_supplier_ref" name="vwpm_component_supplier_ref" value="<?php echo esc_attr((string) $supplier_ref); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="vwpm_notes">Notes</label></th>
                <td><textarea id="vwpm_notes" name="vwpm_notes" rows="4" class="large-text"><?php echo esc_textarea((string) $notes); ?></textarea></td>
            </tr>
        </table>
        <?php
    }

    public function save_component_meta($post_id) {
        if (!isset($_POST['vwpm_component_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vwpm_component_nonce'])), 'vwpm_component_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['vwpm_component_number'])) {
            update_post_meta($post_id, '_vwpm_component_number', sanitize_text_field(wp_unslash($_POST['vwpm_component_number'])));
        }
        if (isset($_POST['vwpm_supplier_id'])) {
            update_post_meta($post_id, '_vwpm_supplier_id', absint($_POST['vwpm_supplier_id']));
        }
        if (isset($_POST['vwpm_price'])) {
            update_post_meta($post_id, '_vwpm_price', floatval($_POST['vwpm_price']));
        }
        if (isset($_POST['vwpm_notes'])) {
            update_post_meta($post_id, '_vwpm_notes', sanitize_textarea_field(wp_unslash($_POST['vwpm_notes'])));
        }
        if (isset($_POST['vwpm_component_location'])) {
            update_post_meta($post_id, '_vwpm_component_location', sanitize_text_field(wp_unslash($_POST['vwpm_component_location'])));
        }
        if (isset($_POST['vwpm_component_supplier_ref'])) {
            update_post_meta($post_id, '_vwpm_component_supplier_ref', sanitize_text_field(wp_unslash($_POST['vwpm_component_supplier_ref'])));
        }
    }

    private function get_suppliers() {
        global $wpdb;

        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}vwpm_suppliers ORDER BY name ASC");
    }

    public function render_dashboard_page() {
        require VWPM_PLUGIN_DIR . 'includes/admin/dashboard.php';
    }

    public function render_suppliers_page() {
        require VWPM_PLUGIN_DIR . 'includes/admin/suppliers.php';
    }

    public function render_production_page() {
        require VWPM_PLUGIN_DIR . 'includes/admin/production.php';
    }

    public function render_custom_po_page() {
        require VWPM_PLUGIN_DIR . 'includes/admin/custom-po.php';
    }

    public function render_import_export_page() {
        require VWPM_PLUGIN_DIR . 'includes/admin/import-export.php';
    }

    public function render_purchase_orders_page() {
        require VWPM_PLUGIN_DIR . 'includes/admin/purchase-orders.php';
    }
}

register_activation_hook(__FILE__, array('VW_Parts_Manager', 'activate'));
register_deactivation_hook(__FILE__, array('VW_Parts_Manager', 'deactivate'));
VW_Parts_Manager::instance();

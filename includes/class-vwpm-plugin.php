<?php
/**
 * Core plugin class.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class VWPM_Plugin {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('add_meta_boxes_product', array($this, 'register_product_meta_boxes'));
        add_action('save_post_product', array($this, 'save_product_meta'));
        add_filter('manage_edit-vwpm_component_columns', array($this, 'component_columns'));
        add_action('manage_vwpm_component_posts_custom_column', array($this, 'render_component_columns'), 10, 2);
        add_filter('manage_edit-vwpm_tool_columns', array($this, 'tool_columns'));
        add_action('manage_vwpm_tool_posts_custom_column', array($this, 'render_tool_columns'), 10, 2);
    }

    public static function activate() {
        self::create_tables();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public function init() {
        $this->register_post_types();
    }

    private function register_post_types() {
        $tool_labels = array(
            'name' => 'Tools',
            'singular_name' => 'Tool',
            'menu_name' => 'Tools',
            'add_new_item' => 'Add New Tool',
            'edit_item' => 'Edit Tool',
            'new_item' => 'New Tool',
            'view_item' => 'View Tool',
            'search_items' => 'Search Tools',
        );

        register_post_type(
            'vwpm_tool',
            array(
                'labels' => $tool_labels,
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => false,
                'supports' => array('title'),
                'has_archive' => false,
                'capability_type' => 'post',
            )
        );

        $component_labels = array(
            'name' => 'Components',
            'singular_name' => 'Component',
            'menu_name' => 'Components',
            'add_new_item' => 'Add New Component',
            'edit_item' => 'Edit Component',
            'new_item' => 'New Component',
            'view_item' => 'View Component',
            'search_items' => 'Search Components',
        );

        register_post_type(
            'vwpm_component',
            array(
                'labels' => $component_labels,
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => false,
                'supports' => array('title'),
                'has_archive' => false,
                'capability_type' => 'post',
            )
        );
    }

    private static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $suppliers = $wpdb->prefix . 'vwpm_suppliers';
        $sql_suppliers = "CREATE TABLE IF NOT EXISTS {$suppliers} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            email varchar(255) DEFAULT '',
            contact_details text DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        dbDelta($sql_suppliers);

        $pos = $wpdb->prefix . 'vwpm_pos';
        $sql_pos = "CREATE TABLE IF NOT EXISTS {$pos} (
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
            PRIMARY KEY  (id),
            UNIQUE KEY po_number_unique (po_number)
        ) {$charset_collate};";

        dbDelta($sql_pos);
    }

    public function add_admin_menu() {
        add_menu_page(
            'VW Parts Manager',
            'VW Parts',
            'manage_options',
            'vwpm-dashboard',
            array($this, 'render_dashboard_page'),
            'dashicons-hammer',
            30
        );

        add_submenu_page(
            'vwpm-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'vwpm-dashboard',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'vwpm-dashboard',
            'Suppliers',
            'Suppliers',
            'manage_options',
            'vwpm-suppliers',
            array($this, 'render_suppliers_page')
        );

        add_submenu_page(
            'vwpm-dashboard',
            'Tools',
            'Tools',
            'manage_options',
            'edit.php?post_type=vwpm_tool'
        );

        add_submenu_page(
            'vwpm-dashboard',
            'Components',
            'Components',
            'manage_options',
            'edit.php?post_type=vwpm_component'
        );

        add_submenu_page(
            'vwpm-dashboard',
            'Production Calculator',
            'Production Calculator',
            'manage_options',
            'vwpm-production',
            array($this, 'render_production_page')
        );

        add_submenu_page(
            'vwpm-dashboard',
            'Purchase Orders',
            'Purchase Orders',
            'manage_options',
            'vwpm-purchase-orders',
            array($this, 'render_purchase_orders_page')
        );
    }

    public function enqueue_admin_assets($hook_suffix) {
        $allowed = array(
            'toplevel_page_vwpm-dashboard',
            'vw-parts-manager_page_vwpm-dashboard',
            'vw-parts-manager_page_vwpm-production',
            'vw-parts-manager_page_vwpm-suppliers',
            'product_page_vwpm-production',
            'edit.php',
            'post.php',
        );

        if (!in_array($hook_suffix, $allowed, true) && false === strpos((string) $hook_suffix, 'vwpm-')) {
            if ('vwpm_tool' !== get_post_type() && 'vwpm_component' !== get_post_type()) {
                return;
            }
        }

        wp_enqueue_style('vwpm-admin', VWPM_PLUGIN_URL . 'assets/css/admin.css', array(), '1.0.0');
        wp_enqueue_script('vwpm-admin', VWPM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), '1.0.0', true);

        wp_localize_script(
            'vwpm-admin',
            'vwpmAdmin',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vwpm_nonce'),
            )
        );
    }

    public function register_product_meta_boxes() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_meta_box(
            'vwpm_product_bom',
            'VW Parts BOM',
            array($this, 'render_product_bom_meta_box'),
            'product',
            'normal',
            'high'
        );

        add_meta_box(
            'vwpm_product_tools',
            'VW Parts Tools',
            array($this, 'render_product_tools_meta_box'),
            'product',
            'normal',
            'high'
        );

        add_meta_box(
            'vwpm_product_supplier',
            'Product Supplier',
            array($this, 'render_product_supplier_meta_box'),
            'product',
            'normal',
            'high'
        );
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
                <?php if (!empty($bom)) : ?>
                    <?php foreach ($bom as $index => $row) : ?>
                        <tr>
                            <td>
                                <select name="vwpm_bom[<?php echo esc_attr($index); ?>][component_id]" class="vwpm-component-select widefat">
                                    <option value="">Select component</option>
                                    <?php foreach ($components as $component) : ?>
                                        <option value="<?php echo esc_attr($component->ID); ?>" <?php selected((int) ($row['component_id'] ?? 0), (int) $component->ID); ?>>
                                            <?php echo esc_html($component->post_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" step="0.01" name="vwpm_bom[<?php echo esc_attr($index); ?>][quantity]" value="<?php echo esc_attr((string) ($row['quantity'] ?? 1)); ?>" class="small-text" /></td>
                            <td><button type="button" class="button vwpm-remove-row">Remove</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <p><button type="button" class="button button-primary" id="vwpm-add-bom-row">Add Component</button></p>
        <script type="text/html" id="vwpm-bom-row-template">
            <tr>
                <td>
                    <select name="vwpm_bom[INDEX][component_id]" class="vwpm-component-select widefat">
                        <option value="">Select component</option>
                        <?php foreach ($components as $component) : ?>
                            <option value="<?php echo esc_attr($component->ID); ?>"><?php echo esc_html($component->post_title); ?></option>
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
                            <select name="vwpm_tools[<?php echo esc_attr($index); ?>]" class="vwpm-tool-select widefat">
                                <option value="">Select tool</option>
                                <?php foreach ($tools as $tool) : ?>
                                    <option value="<?php echo esc_attr($tool->ID); ?>" <?php selected((int) $tool_id, (int) $tool->ID); ?>>
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
                            <option value="<?php echo esc_attr($tool->ID); ?>"><?php echo esc_html($tool->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><button type="button" class="button vwpm-remove-tool-row">Remove</button></td>
            </tr>
        </script>
        <?php
    }

    public function render_product_supplier_meta_box($post) {
        $supplier_id = get_post_meta($post->ID, '_vwpm_product_supplier_id', true);
        ?>
        <p class="description">Choose a supplier for ready-made products.</p>
        <select name="vwpm_product_supplier_id" class="widefat">
            <option value="">No supplier</option>
            <?php foreach ($this->get_suppliers() as $supplier) : ?>
                <option value="<?php echo esc_attr((int) $supplier->id); ?>" <?php selected((int) $supplier_id, (int) $supplier->id); ?>>
                    <?php echo esc_html($supplier->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
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
            foreach (wp_unslash($_POST['vwpm_bom']) as $item) {
                $component_id = !empty($item['component_id']) ? absint($item['component_id']) : 0;
                $quantity = !empty($item['quantity']) ? floatval($item['quantity']) : 0;
                if ($component_id && $quantity > 0) {
                    $bom[] = array(
                        'component_id' => $component_id,
                        'quantity' => $quantity,
                    );
                }
            }
            update_post_meta($post_id, '_vwpm_bom', $bom);
        }

        if (isset($_POST['vwpm_tools']) && is_array($_POST['vwpm_tools'])) {
            $tools = array();
            foreach (array_filter(array_map('absint', wp_unslash($_POST['vwpm_tools']))) as $tool_id) {
                $tools[] = $tool_id;
            }
            update_post_meta($post_id, '_vwpm_tools', $tools);
        }

        if (isset($_POST['vwpm_product_supplier_id'])) {
            update_post_meta($post_id, '_vwpm_product_supplier_id', absint($_POST['vwpm_product_supplier_id']));
        }
    }

    public function get_suppliers() {
        global $wpdb;
        $table = $wpdb->prefix . 'vwpm_suppliers';

        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY name ASC");
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

    public function render_purchase_orders_page() {
        require VWPM_PLUGIN_DIR . 'includes/admin/purchase-orders.php';
    }

    public function component_columns($columns) {
        $new = array();
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ('title' === $key) {
                $new['component_number'] = 'Component Number';
                $new['component_supplier'] = 'Supplier';
            }
        }

        return $new;
    }

    public function render_component_columns($column, $post_id) {
        if ('component_number' === $column) {
            echo esc_html(get_post_meta($post_id, '_vwpm_component_number', true));
        }

        if ('component_supplier' === $column) {
            $supplier_id = (int) get_post_meta($post_id, '_vwpm_supplier_id', true);
            if ($supplier_id) {
                global $wpdb;
                $name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}vwpm_suppliers WHERE id = %d", $supplier_id));
                echo esc_html($name ?: '-');
            } else {
                echo '-';
            }
        }
    }

    public function tool_columns($columns) {
        $new = array();
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ('title' === $key) {
                $new['tool_number'] = 'Tool Number';
                $new['tool_location'] = 'Location';
            }
        }

        return $new;
    }

    public function render_tool_columns($column, $post_id) {
        if ('tool_number' === $column) {
            echo esc_html(get_post_meta($post_id, '_vwpm_tool_number', true));
        }

        if ('tool_location' === $column) {
            echo esc_html(get_post_meta($post_id, '_vwpm_location', true));
        }
    }
}

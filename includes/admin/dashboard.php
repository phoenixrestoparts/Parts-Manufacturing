<?php
/**
 * Dashboard admin page.
 */

if (!defined('ABSPATH')) {
    exit;
}

$component_count = wp_count_posts('vwpm_component')->publish;
$tool_count = wp_count_posts('vwpm_tool')->publish;
$supplier_count = count(
    (new WP_Query(array(
        'post_type' => 'vwpm_component',
        'post_status' => 'any',
        'posts_per_page' => 1,
    )))->posts
);
?>
<div class="wrap vwpm-wrap">
    <h1>VW Parts Manager</h1>

    <div class="vwpm-stats-grid">
        <div class="vwpm-stat-card">
            <span class="vwpm-stat-label">Suppliers</span>
            <span class="vwpm-stat-number"><?php echo esc_html((int) count((new class extends WP_Query {
                public function __construct() {
                    global $wpdb;
                    $this->found_posts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}vwpm_suppliers");
                }
            })->found_posts); ?></span>
        </div>
        <div class="vwpm-stat-card">
            <span class="vwpm-stat-label">Components</span>
            <span class="vwpm-stat-number"><?php echo esc_html((int) $component_count); ?></span>
        </div>
        <div class="vwpm-stat-card">
            <span class="vwpm-stat-label">Tools</span>
            <span class="vwpm-stat-number"><?php echo esc_html((int) $tool_count); ?></span>
        </div>
    </div>

    <div class="vwpm-card">
        <h2>Overview</h2>
        <p>Build BOMs, assign required tools, track suppliers, and calculate production requirements for your manufacturing workflow.</p>
    </div>
</div>

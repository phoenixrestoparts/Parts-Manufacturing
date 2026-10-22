<?php
/**
 * Dashboard admin page.
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$component_counts = wp_count_posts('vwpm_component');
$tool_counts      = wp_count_posts('vwpm_tool');

$component_count = isset($component_counts->publish) ? (int) $component_counts->publish : 0;
$tool_count      = isset($tool_counts->publish) ? (int) $tool_counts->publish : 0;
$supplier_count  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}vwpm_suppliers");
?>
<div class="wrap vwpm-wrap">
    <h1>VW Parts Manager</h1>

    <div class="vwpm-stats-grid">
        <div class="vwpm-stat-card">
            <span class="vwpm-stat-label">Suppliers</span>
            <span class="vwpm-stat-number"><?php echo esc_html($supplier_count); ?></span>
        </div>
        <div class="vwpm-stat-card">
            <span class="vwpm-stat-label">Components</span>
            <span class="vwpm-stat-number"><?php echo esc_html($component_count); ?></span>
        </div>
        <div class="vwpm-stat-card">
            <span class="vwpm-stat-label">Tools</span>
            <span class="vwpm-stat-number"><?php echo esc_html($tool_count); ?></span>
        </div>
    </div>

    <div class="vwpm-card">
        <h2>Overview</h2>
        <p>Build BOMs, assign required tools, track suppliers, and calculate production requirements for your manufacturing workflow.</p>
    </div>
</div>


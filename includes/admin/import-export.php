<?php
if (!defined('ABSPATH')) {
    exit;
}

$has_export = true;
?>
<div class="wrap vwpm-wrap">
    <h1>Import / Export</h1>

    <div class="vwpm-card">
        <p>Import supplier data or export manufacturing records for backup, review, or spreadsheet processing.</p>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="vwpm_import_file">Import file</label></th>
                <td>
                    <input type="file" id="vwpm_import_file" name="vwpm_import_file" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="vwpm_import_type">Import type</label></th>
                <td>
                    <select id="vwpm_import_type" class="regular-text">
                        <option value="suppliers">Suppliers</option>
                        <option value="components">Components</option>
                        <option value="tools">Tools</option>
                        <option value="purchase_orders">Purchase orders</option>
                    </select>
                </td>
            </tr>
        </table>
        <p>
            <button type="button" class="button button-primary">Import Data</button>
            <button type="button" class="button">Export Current Data</button>
        </p>
    </div>
</div>

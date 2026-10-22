<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$suppliers = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}vwpm_suppliers ORDER BY name ASC");
$supplier_list = array();
foreach ($suppliers as $supplier) {
    $supplier_list[] = array('id' => (int) $supplier->id, 'name' => $supplier->name);
}

$vwpm_prod_config = array(
    'ajaxUrl'   => admin_url('admin-ajax.php'),
    'nonce'     => wp_create_nonce('vwpm_nonce'),
    'suppliers' => $supplier_list,
    'poPage'    => admin_url('admin.php?page=vwpm-purchase-orders'),
);
?>
<style>
    .vwpm-prod-results { position: absolute; z-index: 9999; background: #fff; border: 1px solid #ccc; border-radius: 3px; max-height: 260px; overflow-y: auto; width: 100%; display: none; box-shadow: 0 2px 8px rgba(0,0,0,.15); }
    .vwpm-prod-results.active { display: block; }
    .vwpm-prod-result { padding: 10px 12px; border-bottom: 1px solid #eee; cursor: pointer; }
    .vwpm-prod-result:hover { background: #f5f5f5; }
    .vwpm-prod-result small { display: block; color: #666; }
    .vwpm-prod-group { margin-bottom: 24px; }
    .vwpm-prod-group table input[type=number] { width: 100%; }
    .vwpm-prod-calcnote { display: block; color: #888; font-size: 11px; min-height: 13px; }
    .vwpm-prod-totals td { padding: 4px 12px; }
</style>

<div class="wrap vwpm-wrap" id="vwpm-production-app">
    <h1>Production Calculator</h1>

    <div class="vwpm-card">
        <h2>1. Add products</h2>
        <div style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            <div style="position: relative; flex: 1 1 380px; max-width: 520px;">
                <label for="vwpm-prod-search"><strong>Product (type a name or SKU)</strong></label>
                <input type="text" id="vwpm-prod-search" autocomplete="off" placeholder="Start typing..." style="width: 100%; padding: 6px 8px;">
                <div id="vwpm-prod-results" class="vwpm-prod-results"></div>
            </div>
            <div>
                <label for="vwpm-prod-qty"><strong>Quantity</strong></label><br>
                <input type="number" id="vwpm-prod-qty" min="0" step="any" value="1" style="width: 90px; padding: 6px 8px;">
            </div>
            <div>
                <button type="button" class="button button-primary" id="vwpm-prod-add">Add to list</button>
            </div>
        </div>

        <table class="widefat striped" style="margin-top: 16px; max-width: 760px;">
            <thead>
                <tr><th>Product</th><th style="width: 130px;">Quantity</th><th style="width: 100px;"></th></tr>
            </thead>
            <tbody id="vwpm-prod-list">
                <tr><td colspan="3">No products added yet.</td></tr>
            </tbody>
        </table>

        <p>
            <button type="button" class="button button-primary" id="vwpm-prod-calc" disabled>Calculate</button>
            <button type="button" class="button" id="vwpm-prod-reset">Start over</button>
        </p>
    </div>

    <div id="vwpm-calc-section" style="display: none;">
        <div class="vwpm-card">
            <h2>2. What you need, by supplier</h2>
            <p class="description">Change a quantity to add extra or to allow for stock you already have. Use Remove to drop a line completely.</p>
            <div id="vwpm-prod-warnings"></div>
            <div id="vwpm-prod-groups"></div>
            <div id="vwpm-prod-tools"></div>
        </div>

        <div class="vwpm-card">
            <h2>Add a custom line</h2>
            <table class="widefat" style="max-width: 1000px;">
                <thead>
                    <tr><th>Supplier</th><th>Item</th><th>Part Number</th><th>Supplier Ref</th><th style="width: 80px;">Qty</th><th style="width: 100px;">Unit Price</th><th></th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <select id="vwpm-cl-supplier" style="width: 100%;">
                                <?php foreach ($suppliers as $supplier) : ?>
                                    <option value="<?php echo esc_attr((string) $supplier->id); ?>"><?php echo esc_html($supplier->name); ?></option>
                                <?php endforeach; ?>
                                <option value="0">No supplier assigned</option>
                            </select>
                        </td>
                        <td><input type="text" id="vwpm-cl-name" style="width: 100%;"></td>
                        <td><input type="text" id="vwpm-cl-part" style="width: 100%;"></td>
                        <td><input type="text" id="vwpm-cl-ref" style="width: 100%;"></td>
                        <td><input type="number" id="vwpm-cl-qty" min="0" step="any" value="1" style="width: 100%;"></td>
                        <td><input type="number" id="vwpm-cl-price" min="0" step="0.01" value="0.00" style="width: 100%;"></td>
                        <td><button type="button" class="button" id="vwpm-cl-add">Add line</button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="vwpm-card">
            <h2>3. Totals and export</h2>
            <table class="vwpm-prod-totals">
                <tr><td>Subtotal (ex VAT)</td><td id="vwpm-t-sub">£0.00</td></tr>
                <tr><td><label><input type="checkbox" id="vwpm-prod-vat" checked> Include VAT (20%)</label></td><td id="vwpm-t-vat">£0.00</td></tr>
                <tr><td><strong>Total</strong></td><td><strong id="vwpm-t-total">£0.00</strong></td></tr>
            </table>
            <p class="description">Purchase orders are saved with the ex-VAT total. One purchase order is created per supplier, and each stays editable until you mark it as sent.</p>
            <p><button type="button" class="button button-primary" id="vwpm-prod-export">Export to Purchase Order(s)</button></p>
            <div id="vwpm-export-result"></div>
        </div>
    </div>
</div>

<script type="text/javascript">
var vwpmProd = <?php echo wp_json_encode($vwpm_prod_config); ?>;
</script>
<script type="text/javascript">
jQuery(function ($) {
    var cfg = window.vwpmProd || {};
    var suppliers = cfg.suppliers || [];
    var products = [];
    var lines = [];
    var tools = [];
    var selected = null;
    var uidCounter = 0;
    var exported = false;
    var searchTimer = null;

    function esc(value) { return $('<div>').text(value == null ? '' : String(value)).html(); }
    function escAttr(value) { return esc(value).replace(/"/g, '&quot;'); }
    function num(value) { var n = parseFloat(value); return isNaN(n) ? 0 : n; }
    function money(value) { return '£' + value.toFixed(2); }
    function qtyText(value) { return String(Math.round(value * 10000) / 10000); }
    function ajaxMessage(resp, fallback) {
        return (resp && resp.data && resp.data.message) ? resp.data.message : fallback;
    }
    function supplierName(id) {
        for (var i = 0; i < suppliers.length; i++) {
            if (suppliers[i].id === id) { return suppliers[i].name; }
        }
        return 'No supplier assigned';
    }
    function findLine(uid) {
        for (var i = 0; i < lines.length; i++) {
            if (lines[i].uid === uid) { return lines[i]; }
        }
        return null;
    }
    function calcNote(line) {
        if (!line.custom && Math.abs(line.qty - line.calc_qty) > 0.00001) {
            return 'calculated: ' + qtyText(line.calc_qty);
        }
        return '';
    }

    /* ---------- Step 1: product list ---------- */

    function renderProducts() {
        var $body = $('#vwpm-prod-list');
        if (!products.length) {
            $body.html('<tr><td colspan="3">No products added yet.</td></tr>');
            $('#vwpm-prod-calc').prop('disabled', true);
            return;
        }
        var html = '';
        $.each(products, function (i, p) {
            html += '<tr data-index="' + i + '">' +
                '<td>' + esc(p.title) + (p.sku ? ' <small>(' + esc(p.sku) + ')</small>' : '') + '</td>' +
                '<td><input type="number" min="0" step="any" class="vwpm-p-qty" style="width: 100%;" value="' + escAttr(qtyText(p.quantity)) + '"></td>' +
                '<td><button type="button" class="button vwpm-p-remove">Remove</button></td>' +
                '</tr>';
        });
        $body.html(html);
        $('#vwpm-prod-calc').prop('disabled', false);
    }

    $('#vwpm-prod-search').on('input', function () {
        selected = null;
        var query = $.trim($(this).val());
        var $results = $('#vwpm-prod-results');
        clearTimeout(searchTimer);
        if (query.length < 2) {
            $results.removeClass('active').empty();
            return;
        }
        searchTimer = setTimeout(function () {
            $results.html('<div class="vwpm-prod-result">Searching...</div>').addClass('active');
            $.post(cfg.ajaxUrl, { action: 'vwpm_prod_search_products', nonce: cfg.nonce, query: query }, function (resp) {
                if (!resp || !resp.success) {
                    $results.html('<div class="vwpm-prod-result">' + esc(ajaxMessage(resp, 'Search failed.')) + '</div>');
                    return;
                }
                if (!resp.data.length) {
                    $results.html('<div class="vwpm-prod-result">No products found.</div>');
                    return;
                }
                var html = '';
                $.each(resp.data, function (i, p) {
                    html += '<div class="vwpm-prod-result" data-id="' + p.id + '" data-title="' + escAttr(p.title) + '" data-sku="' + escAttr(p.sku) + '">' +
                        '<strong>' + esc(p.title) + '</strong>' + (p.sku ? '<small>SKU: ' + esc(p.sku) + '</small>' : '') + '</div>';
                });
                $results.html(html);
            }, 'json').fail(function () {
                $results.html('<div class="vwpm-prod-result">Search failed. Check the browser console.</div>');
            });
        }, 300);
    });

    $('#vwpm-prod-results').on('click', '.vwpm-prod-result[data-id]', function () {
        selected = {
            product_id: parseInt($(this).attr('data-id'), 10),
            title: $(this).attr('data-title'),
            sku: $(this).attr('data-sku')
        };
        $('#vwpm-prod-search').val(selected.title + (selected.sku ? ' (' + selected.sku + ')' : ''));
        $('#vwpm-prod-results').removeClass('active').empty();
        $('#vwpm-prod-qty').focus().select();
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#vwpm-prod-search, #vwpm-prod-results').length) {
            $('#vwpm-prod-results').removeClass('active');
        }
    });

    $('#vwpm-prod-add').on('click', function (e) {
        e.preventDefault();
        if (!selected) {
            alert('Type a product name or SKU, then pick the product from the list.');
            return;
        }
        var qty = num($('#vwpm-prod-qty').val());
        if (qty <= 0) {
            alert('Enter a quantity greater than 0.');
            return;
        }
        var existing = null;
        $.each(products, function (i, p) {
            if (p.product_id === selected.product_id) { existing = p; }
        });
        if (existing) {
            existing.quantity += qty;
        } else {
            products.push({ product_id: selected.product_id, title: selected.title, sku: selected.sku, quantity: qty });
        }
        selected = null;
        $('#vwpm-prod-search').val('').focus();
        $('#vwpm-prod-qty').val(1);
        renderProducts();
    });

    $('#vwpm-prod-qty').on('keydown', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#vwpm-prod-add').trigger('click');
        }
    });

    $('#vwpm-prod-list').on('input', '.vwpm-p-qty', function () {
        var i = parseInt($(this).closest('tr').attr('data-index'), 10);
        if (products[i]) { products[i].quantity = num($(this).val()); }
    });

    $('#vwpm-prod-list').on('click', '.vwpm-p-remove', function () {
        var i = parseInt($(this).closest('tr').attr('data-index'), 10);
        products.splice(i, 1);
        renderProducts();
    });

    /* ---------- Step 2: results ---------- */

    function updateTotals() {
        var sub = 0;
        var bySupplier = {};
        $.each(lines, function (i, l) {
            var t = l.qty * l.unit_price;
            sub += t;
            bySupplier[l.supplier_id] = (bySupplier[l.supplier_id] || 0) + t;
        });
        $('.vwpm-g-sub').each(function () {
            $(this).text(money(bySupplier[parseInt($(this).attr('data-supplier'), 10)] || 0));
        });
        var vat = $('#vwpm-prod-vat').is(':checked') ? sub * 0.2 : 0;
        $('#vwpm-t-sub').text(money(sub));
        $('#vwpm-t-vat').text(money(vat));
        $('#vwpm-t-total').text(money(sub + vat));
    }

    function renderGroups() {
        var order = [];
        $.each(suppliers, function (i, s) { order.push(s.id); });
        order.push(0);

        var html = '';
        $.each(order, function (n, sid) {
            var groupLines = $.grep(lines, function (l) { return l.supplier_id === sid; });
            if (!groupLines.length) { return; }

            html += '<div class="vwpm-prod-group"><h3>' + esc(supplierName(sid)) + '</h3>';
            if (!sid) {
                html += '<p class="description">These items have no supplier. Set a supplier on the component, or assign one on the purchase order after exporting.</p>';
            }
            html += '<table class="widefat striped"><thead><tr>' +
                '<th style="width: 28%;">Item</th><th>Part Number</th><th>Supplier Ref</th>' +
                '<th style="width: 13%;">Qty</th><th style="width: 13%;">Unit Price</th><th>Line Total</th><th></th>' +
                '</tr></thead><tbody>';
            $.each(groupLines, function (i, l) {
                html += '<tr data-uid="' + l.uid + '">' +
                    '<td>' + esc(l.name) + (l.custom ? ' <em>(custom)</em>' : '') + '</td>' +
                    '<td>' + esc(l.part_number) + '</td>' +
                    '<td>' + esc(l.supplier_ref) + '</td>' +
                    '<td><input type="number" min="0" step="any" class="vwpm-l-qty" value="' + escAttr(qtyText(l.qty)) + '"><span class="vwpm-prod-calcnote">' + esc(calcNote(l)) + '</span></td>' +
                    '<td><input type="number" min="0" step="0.01" class="vwpm-l-price" value="' + escAttr(l.unit_price.toFixed(2)) + '"></td>' +
                    '<td class="vwpm-l-total">' + money(l.qty * l.unit_price) + '</td>' +
                    '<td><button type="button" class="button vwpm-l-remove">Remove</button></td>' +
                    '</tr>';
            });
            html += '</tbody><tfoot><tr><td colspan="5" style="text-align: right;"><strong>Subtotal</strong></td>' +
                '<td colspan="2"><strong class="vwpm-g-sub" data-supplier="' + sid + '"></strong></td></tr></tfoot></table></div>';
        });

        if (!html) {
            html = '<p>There are no lines. Add a custom line below, or add products and calculate again.</p>';
        }
        $('#vwpm-prod-groups').html(html);
        updateTotals();
    }

    function renderTools() {
        if (!tools.length) {
            $('#vwpm-prod-tools').empty();
            return;
        }
        var html = '<h3>Tools required</h3><ul style="list-style: disc; margin-left: 20px;">';
        $.each(tools, function (i, t) {
            html += '<li>' + esc((t.number ? t.number + ' - ' : '') + t.name + (t.location ? ' [' + t.location + ']' : '')) + '</li>';
        });
        html += '</ul>';
        $('#vwpm-prod-tools').html(html);
    }

    function renderWarnings(warnings) {
        var html = '';
        $.each(warnings || [], function (i, w) {
            html += '<div class="notice notice-warning inline"><p>' + esc(w) + '</p></div>';
        });
        $('#vwpm-prod-warnings').html(html);
    }

    $('#vwpm-prod-groups').on('input', '.vwpm-l-qty, .vwpm-l-price', function () {
        var $tr = $(this).closest('tr');
        var line = findLine(parseInt($tr.attr('data-uid'), 10));
        if (!line) { return; }
        if ($(this).hasClass('vwpm-l-qty')) {
            line.qty = num($(this).val());
            $tr.find('.vwpm-prod-calcnote').text(calcNote(line));
        } else {
            line.unit_price = num($(this).val());
        }
        $tr.find('.vwpm-l-total').text(money(line.qty * line.unit_price));
        updateTotals();
    });

    $('#vwpm-prod-groups').on('click', '.vwpm-l-remove', function () {
        var uid = parseInt($(this).closest('tr').attr('data-uid'), 10);
        lines = $.grep(lines, function (l) { return l.uid !== uid; });
        renderGroups();
    });

    $('#vwpm-prod-vat').on('change', updateTotals);

    $('#vwpm-prod-calc').on('click', function () {
        var valid = $.grep(products, function (p) { return p.quantity > 0; });
        if (!valid.length) {
            alert('Add at least one product with a quantity above 0.');
            return;
        }
        if (lines.length && !confirm('Calculating again replaces the current lines, including any edits and custom lines. Continue?')) {
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true).text('Calculating...');
        $.post(cfg.ajaxUrl, { action: 'vwpm_prod_calculate', nonce: cfg.nonce, payload: JSON.stringify({ products: valid }) }, function (resp) {
            if (!resp || !resp.success) {
                alert(ajaxMessage(resp, 'The calculation failed.'));
                return;
            }
            lines = [];
            $.each(resp.data.lines, function (i, l) {
                uidCounter++;
                lines.push({
                    uid: uidCounter,
                    supplier_id: parseInt(l.supplier_id, 10) || 0,
                    name: l.name,
                    part_number: l.part_number,
                    supplier_ref: l.supplier_ref,
                    qty: num(l.qty),
                    calc_qty: num(l.qty),
                    unit_price: num(l.unit_price),
                    custom: false
                });
            });
            tools = resp.data.tools || [];
            exported = false;
            $('#vwpm-export-result').empty();
            $('#vwpm-prod-export').prop('disabled', false).text('Export to Purchase Order(s)');
            renderWarnings(resp.data.warnings);
            renderGroups();
            renderTools();
            $('#vwpm-calc-section').show();
        }, 'json').fail(function () {
            alert('The calculation request failed. Check the browser console.');
        }).always(function () {
            $btn.prop('disabled', !products.length).text('Calculate');
        });
    });

    /* ---------- Custom line ---------- */

    $('#vwpm-cl-add').on('click', function (e) {
        e.preventDefault();
        var name = $.trim($('#vwpm-cl-name').val());
        var qty = num($('#vwpm-cl-qty').val());
        if (!name) {
            alert('Enter an item name for the custom line.');
            return;
        }
        if (qty <= 0) {
            alert('Enter a quantity greater than 0.');
            return;
        }
        uidCounter++;
        lines.push({
            uid: uidCounter,
            supplier_id: parseInt($('#vwpm-cl-supplier').val(), 10) || 0,
            name: name,
            part_number: $.trim($('#vwpm-cl-part').val()),
            supplier_ref: $.trim($('#vwpm-cl-ref').val()),
            qty: qty,
            calc_qty: qty,
            unit_price: num($('#vwpm-cl-price').val()),
            custom: true
        });
        $('#vwpm-cl-name, #vwpm-cl-part, #vwpm-cl-ref').val('');
        $('#vwpm-cl-qty').val(1);
        $('#vwpm-cl-price').val('0.00');
        renderGroups();
    });

    /* ---------- Export ---------- */

    $('#vwpm-prod-export').on('click', function () {
        if (exported) { return; }
        var exportLines = [];
        $.each(lines, function (i, l) {
            if (l.name && l.qty > 0) {
                exportLines.push({
                    supplier_id: l.supplier_id,
                    name: l.name,
                    part_number: l.part_number,
                    supplier_ref: l.supplier_ref,
                    qty: l.qty,
                    unit_price: l.unit_price
                });
            }
        });
        if (!exportLines.length) {
            alert('There are no lines to export.');
            return;
        }
        if (!confirm('Create purchase order(s) from these lines? One is created per supplier, and each stays editable until you mark it as sent.')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Exporting...');
        $.post(cfg.ajaxUrl, {
            action: 'vwpm_prod_export',
            nonce: cfg.nonce,
            payload: JSON.stringify({ lines: exportLines, products: products, tools: tools })
        }, function (resp) {
            if (!resp || !resp.success) {
                $('#vwpm-export-result').html('<div class="notice notice-error inline"><p>' + esc(ajaxMessage(resp, 'The export failed.')) + '</p></div>');
                $btn.prop('disabled', false).text('Export to Purchase Order(s)');
                return;
            }
            var html = '<div class="notice notice-success inline"><p><strong>Purchase orders created:</strong></p><ul style="list-style: disc; margin-left: 20px;">';
            $.each(resp.data.created, function (i, c) {
                html += '<li><a href="' + escAttr(cfg.poPage + '&po_id=' + c.id) + '">' + esc(c.po_number) + '</a> &mdash; ' + esc(c.supplier_name) + ' (' + money(num(c.total)) + ' ex VAT)</li>';
            });
            html += '</ul>';
            if (resp.data.errors && resp.data.errors.length) {
                html += '<p>Some could not be saved: ' + esc(resp.data.errors.join(' | ')) + '</p>';
            }
            html += '<p><a class="button" href="' + escAttr(cfg.poPage) + '">Go to Purchase Orders</a></p></div>';
            $('#vwpm-export-result').html(html);
            exported = true;
            $btn.text('Exported');
        }, 'json').fail(function () {
            $('#vwpm-export-result').html('<div class="notice notice-error inline"><p>The export request failed. Check the browser console.</p></div>');
            $btn.prop('disabled', false).text('Export to Purchase Order(s)');
        });
    });

    /* ---------- Reset ---------- */

    $('#vwpm-prod-reset').on('click', function () {
        if ((products.length || lines.length) && !confirm('Clear everything and start over?')) {
            return;
        }
        products = [];
        lines = [];
        tools = [];
        selected = null;
        exported = false;
        $('#vwpm-prod-search').val('');
        $('#vwpm-prod-qty').val(1);
        $('#vwpm-export-result, #vwpm-prod-warnings, #vwpm-prod-groups, #vwpm-prod-tools').empty();
        $('#vwpm-calc-section').hide();
        renderProducts();
    });

    renderProducts();
});
</script>


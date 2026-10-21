jQuery(function ($) {
    if (typeof vwpmAdmin === 'undefined') {
        return;
    }

    function initSelects() {
        $('.vwpm-component-select').not('.select2-initialized').each(function () {
            $(this).addClass('select2-initialized');
            if (typeof $.fn.select2 === 'function') {
                $(this).select2({
                    width: '100%',
                    placeholder: 'Select component'
                });
            }
        });

        $('.vwpm-tool-select').not('.select2-initialized').each(function () {
            $(this).addClass('select2-initialized');
            if (typeof $.fn.select2 === 'function') {
                $(this).select2({
                    width: '100%',
                    placeholder: 'Select tool'
                });
            }
        });
    }

    $('#vwpm-production-form').on('submit', function (event) {
        event.preventDefault();

        const productId = $('#vwpm_product_id').val();
        const quantity = $('#vwpm_quantity').val();
        const includeVat = $('#vwpm_vat_enabled').is(':checked');

        $.ajax({
            url: vwpmAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'vwpm_calculate_production',
                nonce: vwpmAdmin.nonce,
                product_id: productId,
                quantity: quantity,
                vat_enabled: includeVat ? 1 : 0
            },
            success: function (response) {
                const results = $('#vwpm-calculator-results');

                if (!response || !response.success || !response.data || !response.data.html) {
                    results.html('<div class="notice notice-error"><p>' + (response && response.data && response.data.message ? response.data.message : 'Unable to calculate production requirements.') + '</p></div>');
                    return;
                }

                results.html(response.data.html);
            },
            error: function () {
                $('#vwpm-calculator-results').html('<div class="notice notice-error"><p>Unable to calculate production requirements.</p></div>');
            }
        });
    });

    $('#vwpm-add-bom-row').on('click', function (e) {
        e.preventDefault();

        var $rows = $('#vwpm-bom-rows');
        var template = $('#vwpm-bom-row-template').html();
        var count = $rows.find('tr').length;

        $rows.append(template.replace(/INDEX/g, count));
        initSelects();
    });

    $(document).on('click', '.vwpm-remove-row', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    $('#vwpm-add-tool-row').on('click', function (e) {
        e.preventDefault();

        var $rows = $('#vwpm-tools-rows');
        var template = $('#vwpm-tool-row-template').html();
        var count = $rows.find('tr').length;

        $rows.append(template.replace(/INDEX/g, count));
        initSelects();
    });

    $(document).on('click', '.vwpm-remove-tool-row', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    $('#vwpm_supplier_search').on('keyup', function () {
        var query = $(this).val().trim();
        var $results = $('#vwpm_supplier_results');

        if (query.length < 2) {
            $results.removeClass('active').empty();
            return;
        }

        $.ajax({
            url: vwpmAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'vwpm_search_suppliers',
                nonce: vwpmAdmin.nonce,
                query: query
            },
            success: function (response) {
                if (!response || !response.success) {
                    $results.html('<div class="vwpm-search-result-item">No suppliers found.</div>').addClass('active');
                    return;
                }

                var html = '';
                var suppliers = response.data || [];

                if (!suppliers.length) {
                    html = '<div class="vwpm-search-result-item">No suppliers found.</div>';
                } else {
                    suppliers.forEach(function (supplier) {
                        html += '<div class="vwpm-search-result-item" data-id="' + supplier.id + '" data-name="' + (supplier.name || '').replace(/"/g, '&quot;') + '">';
                        html += '<strong>' + (supplier.name || '') + '</strong>';
                        if (supplier.email) {
                            html += '<small>' + supplier.email + '</small>';
                        }
                        html += '</div>';
                    });
                }

                $results.html(html).addClass('active');

                $results.find('.vwpm-search-result-item').on('click', function () {
                    var id = $(this).data('id');
                    var name = $(this).data('name');

                    if (!id || !name) {
                        return;
                    }

                    $('#vwpm-supplier-item').html(
                        '<div class="vwpm-supplier-selected-item" data-id="' + id + '"><span style="flex: 1; font-weight: 600;">' + name + '</span><button type="button" class="button button-small vwpm-remove-supplier">Remove</button></div>' +
                        '<input type="hidden" name="vwpm_product_supplier_id" value="' + id + '" />'
                    );
                    $('#vwpm_supplier_search').val('');
                    $results.removeClass('active').empty();
                });
            }
        });
    });

    $(document).on('click', '.vwpm-remove-supplier', function (e) {
        e.preventDefault();
        $('#vwpm-supplier-item').empty();
        $('input[name="vwpm_product_supplier_id"]').remove();
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#vwpm_supplier_search, #vwpm_supplier_results').length) {
            $('#vwpm_supplier_results').removeClass('active').empty();
        }
    });

    $('#vwpm-custom-po_supplier, #vwpm_product_id, #vwpm_import_type').each(function () {
        if (!$(this).hasClass('select2-hidden-accessible')) {
            $(this).addClass('vwpm-select');
        }
    });

    $('#vwpm-custom-po_notes, #vwpm_supplier_contact').on('focus', function () {
        $(this).attr('rows', 6);
    });

    initSelects();
});

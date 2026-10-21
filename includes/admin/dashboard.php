jQuery(function($){
    function initSelects(){
        $('.vwpm-component-select').not('.select2-initialized').each(function(){
            $(this).addClass('select2-initialized');
            if (typeof $.fn.select2 === 'function') {
                $(this).select2({ width: '100%', placeholder: 'Select component' });
            }
        });

        $('.vwpm-tool-select').not('.select2-initialized').each(function(){
            $(this).addClass('select2-initialized');
            if (typeof $.fn.select2 === 'function') {
                $(this).select2({ width: '100%', placeholder: 'Select tool' });
            }
        });
    }

    $('#vwpm-add-bom-row').on('click', function(e){
        e.preventDefault();
        var $rows = $('#vwpm-bom-rows');
        var template = $('#vwpm-bom-row-template').html();
        var count = $rows.find('tr').length;
        $rows.append(template.replace(/INDEX/g, count));
        initSelects();
    });

    $(document).on('click', '.vwpm-remove-row', function(e){
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    $('#vwpm-add-tool-row').on('click', function(e){
        e.preventDefault();
        var $rows = $('#vwpm-tools-rows');
        var template = $('#vwpm-tool-row-template').html();
        var count = $rows.find('tr').length;
        $rows.append(template.replace(/INDEX/g, count));
        initSelects();
    });

    $(document).on('click', '.vwpm-remove-tool-row', function(e){
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    $('#vwpm_supplier_search').on('keyup', function(){
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
            success: function(response){
                if (!response || !response.success) {
                    $results.html('<div class="vwpm-search-result-item">No suppliers found.</div>').addClass('active');
                    return;
                }

                var html = '';
                if (!response.data || response.data.length === 0) {
                    html = '<div class="vwpm-search-result-item">No suppliers found.</div>';
                } else {
                    response.data.forEach(function(supplier){
                        html += '<div class="vwpm-search-result-item" data-id="' + supplier.id + '" data-name="' + supplier.name + '">';
                        html += '<strong>' + supplier.name + '</strong>';
                        if (supplier.email) {
                            html += '<small>' + supplier.email + '</small>';
                        }
                        html += '</div>';
                    });
                }
                $results.html(html).addClass('active');
                $results.find('.vwpm-search-result-item').on('click', function(){
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

    $(document).on('click', '.vwpm-remove-supplier', function(e){
        e.preventDefault();
        $('#vwpm-supplier-item').empty();
        $('input[name="vwpm_product_supplier_id"]').remove();
    });

    $(document).on('click', function(e){
        if (!$(e.target).closest('#vwpm_supplier_search, #vwpm_supplier_results').length) {
            $('#vwpm_supplier_results').removeClass('active').empty();
        }
    });

    initSelects();
});


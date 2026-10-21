jQuery(document).ready(function ($) {
    $('#vwpm-production-form').on('submit', function (event) {
        event.preventDefault();

        const productId = $('#vwpm_product_id').val();
        const quantity = $('#vwpm_quantity').val();
        const includeVat = $('#vwpm_vat_enabled').is(':checked');

        $('#vwpm-calculator-results').html(
            '<div class="vwpm-card"><p><strong>Production calculator</strong></p><p>Product ID: ' +
            (productId || 'Not selected') + '</p><p>Quantity: ' +
            (quantity || '0') + '</p><p>VAT included: ' +
            (includeVat ? 'Yes' : 'No') + '</p></div>'
        );
    });

    $('#vwpm-custom-po_supplier, #vwpm_product_id, #vwpm_import_type').each(function () {
        if (!$(this).hasClass('select2-hidden-accessible')) {
            $(this).addClass('vwpm-select');
        }
    });

    $('#vwpm-custom-po_notes, #vwpm_supplier_contact').on('focus', function () {
        $(this).attr('rows', 6);
    });
});

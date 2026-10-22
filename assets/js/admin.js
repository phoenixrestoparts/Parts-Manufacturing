(function ($) {
    'use strict';

    $(function () {
        var addBomRow = function () {
            var template = $('#vwpm-bom-row-template');
            if (!template.length) {
                return;
            }

            var rowHtml = template.html();
            var key = Date.now() + Math.random().toString(16).slice(2);
            rowHtml = rowHtml.replace(/INDEX/g, key);
            $('#vwpm-bom-rows').append(rowHtml);

            if ($.fn.select2) {
                $('#vwpm-bom-rows .vwpm-component-select').last().select2({ width: '100%' });
            }
        };

        var addToolRow = function () {
            var template = $('#vwpm-tool-row-template');
            if (!template.length) {
                return;
            }

            var rowHtml = template.html();
            var key = Date.now() + Math.random().toString(16).slice(2);
            rowHtml = rowHtml.replace(/INDEX/g, key);
            $('#vwpm-tools-rows').append(rowHtml);

            if ($.fn.select2) {
                $('#vwpm-tools-rows .vwpm-tool-select').last().select2({ width: '100%' });
            }
        };

        $(document).on('click', '#vwpm-add-bom-row', function (event) {
            event.preventDefault();
            addBomRow();
        });

        $(document).on('click', '#vwpm-add-tool-row', function (event) {
            event.preventDefault();
            addToolRow();
        });

        $(document).on('click', '.vwpm-remove-row', function () {
            $(this).closest('tr').remove();
        });

        $(document).on('click', '.vwpm-remove-tool-row', function () {
            $(this).closest('tr').remove();
        });

        if ($.fn.select2) {
            $('.vwpm-component-select, .vwpm-tool-select').each(function () {
                if (!$(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2({ width: '100%' });
                }
            });
        }
    });
})(jQuery);

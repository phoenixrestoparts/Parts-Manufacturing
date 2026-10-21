(function ($) {
    'use strict';

    $(function () {
        window.vwpmAdmin = window.vwpmAdmin || {};

        // Keep this script lightweight. The main VWPM admin interactions are initialized
        // in the inline PHP script emitted by the plugin bootstrap to avoid duplicate bindings.
        // The file must remain valid JavaScript so the admin screen can load without
        // breaking on syntax errors.
        window.vwpmAdmin.ready = true;
    });
})(jQuery);

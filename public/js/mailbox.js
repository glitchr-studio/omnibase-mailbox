/* The mailbox's two behaviours: "select all" in the list, confirm() before deleting. */
(function () {
    'use strict';

    var all = document.querySelector('[data-mailbox-check-all]');
    if (all) {
        all.addEventListener('change', function () {
            document.querySelectorAll('input[name="ids[]"]').forEach(function (box) { box.checked = all.checked; });
        });
    }

    document.querySelectorAll('button[data-confirm]').forEach(function (b) {
        b.addEventListener('click', function (e) {
            var form = b.closest('form');
            if (form && form.hasAttribute('data-mailbox-batch') && !form.querySelector('input[name="ids[]"]:checked')) {
                e.preventDefault();
                return;
            }
            if (!window.confirm(b.getAttribute('data-confirm'))) e.preventDefault();
        });
    });
})();

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

    // With mailbox.poll: omnibase's `poll` controller says a message arrived
    // (poll:change); the conversation's page is fetched again and its list of
    // messages replaced, without losing what is being typed.
    function live(thread) {
        if (thread.dataset.mailboxBound) return;
        thread.dataset.mailboxBound = '1';
        thread.addEventListener('poll:change', function () {
            fetch(window.location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (response) { return response.ok ? response.text() : null; })
                .then(function (html) {
                    if (!html) return;
                    var fresh = new DOMParser().parseFromString(html, 'text/html').querySelector('.mailbox-messages');
                    var list = thread.querySelector('.mailbox-messages');
                    if (fresh && list) {
                        list.innerHTML = fresh.innerHTML;
                        var last = list.lastElementChild;
                        if (last) last.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    }
                    thread.classList.remove('is-ringing');
                })
                .catch(function () {});
        });
    }
    document.querySelectorAll('[data-mailbox-live]').forEach(live);
    window.addEventListener('transparent:load', function () { document.querySelectorAll('[data-mailbox-live]').forEach(live); });
})();

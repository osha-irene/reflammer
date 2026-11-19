(function () {
    var requestId = 0;

    function getContentRegion() {
        return document.querySelector('.rw-results, .rw-article');
    }

    function syncListState(url) {
        var results = document.querySelector('.rw-results');
        if (!results) return;

        var form = results.querySelector('.rw-search');
        var category = url.searchParams.get('category') || '0';
        if (form) {
            form.elements.category.value = category;
            form.elements.field.value = url.searchParams.get('field') || 'title';
            form.elements.q.value = url.searchParams.get('q') || '';
        }

        var all = document.querySelector('.rw-categories .rw-all');
        if (all) all.classList.toggle('is-active', category === '0');
        document.querySelectorAll('.rw-categories .rw-category-label, .rw-categories .rw-sub').forEach(function (link) {
            var linkUrl = new URL(link.href, location.href);
            link.classList.toggle('is-active', (linkUrl.searchParams.get('category') || '0') === category);
        });

        var activeSub = document.querySelector('.rw-categories .rw-sub.is-active');
        if (activeSub) {
            var panel = activeSub.closest('.rw-category-children');
            if (panel) {
                panel.hidden = false;
                var toggle = document.querySelector('.rw-category-main[data-submenu="' + panel.id + '"]');
                if (toggle) toggle.setAttribute('aria-expanded', 'true');
            }
        }
    }

    function replaceContent(href, addHistory) {
        var target = new URL(href, location.href);
        if (target.origin !== location.origin) return;

        var id = ++requestId;
        var current = getContentRegion();
        if (current) current.setAttribute('aria-busy', 'true');

        fetch(target.href, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        }).then(function (response) {
            if (!response.ok) throw new Error('Request failed');
            return response.text();
        }).then(function (html) {
            if (id !== requestId) return;

            var doc = new DOMParser().parseFromString(html, 'text/html');
            var isView = target.searchParams.get('action') === 'view';
            var selector = isView ? '.rw-article' : '.rw-results';
            var incoming = doc.querySelector(selector);
            current = getContentRegion();
            if (!incoming || !current) throw new Error('Content region not found');

            current.replaceWith(incoming);
            if (!isView) syncListState(target);
            if (addHistory) history.pushState({}, '', target.href);
        }).catch(function () {
            if (id !== requestId) return;
            location.assign(target.href);
        });
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('.rw-categories a, .rw-pages a, .rw-tag-filter a, .rw-row, .rw-actions a');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        var target = new URL(link.href, location.href);
        var action = target.searchParams.get('action');
        if (target.origin !== location.origin || (action && action !== 'view')) return;
        if (link.classList.contains('rw-row') && action !== 'view') return;

        if (link.closest('.rw-categories') || link.closest('.rw-actions')) {
            var current = new URL(location.href);
            if (current.searchParams.get('q')) target.searchParams.set('q', current.searchParams.get('q'));
            if (current.searchParams.get('field')) target.searchParams.set('field', current.searchParams.get('field'));
            if (action !== 'view') {
                target.searchParams.delete('tag');
                target.searchParams.delete('page');
            }
        }

        event.preventDefault();
        replaceContent(target.href, true);
    });

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('.rw-search');
        if (!form) return;
        event.preventDefault();

        var target = new URL(location.href);
        target.search = '';
        target.searchParams.set('category', form.elements.category.value || '0');
        target.searchParams.set('field', form.elements.field.value);
        if (form.elements.q.value.trim()) target.searchParams.set('q', form.elements.q.value.trim());
        replaceContent(target.href, true);
    });

    window.addEventListener('popstate', function () {
        replaceContent(location.href, false);
    });
})();

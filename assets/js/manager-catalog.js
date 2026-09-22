(function () {
    'use strict';

    const modal = document.getElementById('packageModal');
    const form = document.getElementById('packageModalForm');
    const list = document.getElementById('packageCatalogList');
    const count = document.getElementById('catalogCount');
    if (!modal || !form || !list) return;

    const title = document.getElementById('packageModalTitle');
    const providerId = document.getElementById('packageProviderId');
    const service = document.getElementById('packageService');
    const providerName = document.getElementById('packageProviderName');
    const packageName = document.getElementById('packageName');
    const price = document.getElementById('packagePrice');
    const discount = document.getElementById('packageDiscount');
    const location = document.getElementById('packageLocation');
    const contact = document.getElementById('packageContact');
    const image = document.getElementById('packageImage');
    const imageHelp = document.getElementById('packageImageHelp');
    const rating = document.getElementById('packageRating');
    const reviewCount = document.getElementById('packageReviewCount');
    const status = document.getElementById('packageStatus');
    const details = document.getElementById('packageDetails');
    const currentImage = document.getElementById('packageCurrentImage');
    const submitBtn = document.getElementById('packageSubmitBtn');
    const errorBox = document.getElementById('packageModalError');

    const baseDashboard = form.getAttribute('action') || 'dashboard.php';
    let activeTab = document.querySelector('.catalog-tab.is-active')?.classList.contains('catalog-tab')
        ? (document.querySelector('.catalog-tab.is-active')?.textContent || '').toLowerCase().includes('my packages') ? 'my' : 'all'
        : 'all';

    function openModal(data) {
        errorBox.hidden = true;
        errorBox.textContent = '';
        form.reset();
        providerId.value = '0';
        currentImage.hidden = true;
        currentImage.innerHTML = '';
        imageHelp.textContent = 'JPG, PNG or WebP · max 10 MB';

        const editing = !!data;
        title.textContent = editing ? 'Edit package' : 'Add a package';
        submitBtn.innerHTML = editing
            ? '<i class="fa-solid fa-floppy-disk"></i> Save Changes'
            : '<i class="fa-solid fa-plus"></i> Add Package';

        if (editing) {
            providerId.value = data.provider_id || '0';
            service.value = data.service_id || '';
            providerName.value = data.provider_name || '';
            packageName.value = data.package_name || '';
            price.value = data.price ?? '';
            discount.value = data.discount_percent ?? 0;
            location.value = data.location || '';
            contact.value = data.contact_number || '';
            rating.value = data.rating ?? 0;
            reviewCount.value = data.review_count ?? 0;
            status.value = data.status || 'Active';
            details.value = data.package_details || '';
            if (data.image) {
                currentImage.hidden = false;
                currentImage.innerHTML = '<img src="' + escapeAttr(data.image_url || '') + '" alt="Current package image"><span>Current package image will remain if no new image is selected.</span>';
                imageHelp.textContent = 'JPG, PNG or WebP · max 10 MB · current image will remain if no new image is selected';
            }
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('manager-modal-open');
        setTimeout(() => service.focus(), 30);
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('manager-modal-open');
        errorBox.hidden = true;
        errorBox.textContent = '';
    }

    function escapeAttr(value) {
        return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function snapshotUrl(tab) {
        const url = new URL(window.location.href);
        url.searchParams.set('ajax', '1');
        url.searchParams.set('action', 'catalog_snapshot');
        url.searchParams.set('catalog_tab', tab);
        url.hash = '';
        return url.toString();
    }

    async function refreshCatalog(tab, updateUrl) {
        list.classList.add('is-loading');
        try {
            const response = await fetch(snapshotUrl(tab), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json();
            if (!data.ok) throw new Error(data.message || 'Could not load packages.');
            list.innerHTML = data.html || '';
            count.textContent = data.count ?? 0;
            activeTab = data.catalog_tab || tab;
            document.querySelectorAll('.catalog-tab').forEach(btn => {
                const isMy = (btn.textContent || '').toLowerCase().includes('my packages');
                btn.classList.toggle('is-active', isMy ? activeTab === 'my' : activeTab === 'all');
                const badge = btn.querySelector('b');
                if (badge) badge.textContent = isMy ? (data.my_count ?? badge.textContent) : (data.all_count ?? badge.textContent);
            });
            const myStat = document.querySelector('.manager-stats-five article:first-child strong');
            if (myStat && data.my_count !== undefined) myStat.textContent = data.my_count;
            const heading = document.querySelector('#catalog-list .manager-panel-heading h2');
            if (heading) heading.textContent = activeTab === 'my' ? 'My Packages' : 'All Packages';
            if (updateUrl) {
                const url = new URL(window.location.href);
                url.searchParams.set('catalog_tab', activeTab);
                url.hash = 'catalog';
                history.replaceState({}, '', url.toString());
            }
        } catch (error) {
            list.innerHTML = '<div class="manager-empty"><i class="fa-solid fa-triangle-exclamation"></i><h3>Could not load packages</h3><p>' + escapeHtml(error.message) + '</p></div>';
        } finally {
            list.classList.remove('is-loading');
        }
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value);
        return div.innerHTML;
    }

    document.addEventListener('click', function (event) {
        const addButton = event.target.closest('.js-open-package-modal');
        if (addButton) {
            event.preventDefault();
            openModal(null);
            return;
        }

        const closeButton = event.target.closest('.js-close-package-modal');
        if (closeButton) {
            event.preventDefault();
            closeModal();
            return;
        }

        const editButton = event.target.closest('.js-edit-package');
        if (editButton) {
            event.preventDefault();
            try {
                const data = JSON.parse(editButton.getAttribute('data-package') || '{}');
                openModal(data);
            } catch (e) {
                errorBox.hidden = false;
                errorBox.textContent = 'This package could not be opened for editing.';
            }
            return;
        }

        const tabButton = event.target.closest('.catalog-tab');
        if (tabButton) {
            event.preventDefault();
            const isMy = (tabButton.textContent || '').toLowerCase().includes('my packages');
            refreshCatalog(isMy ? 'my' : 'all', true);
            return;
        }
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        errorBox.hidden = true;
        errorBox.textContent = '';
        submitBtn.disabled = true;
        submitBtn.classList.add('is-busy');

        try {
            const formData = new FormData(form);
            const response = await fetch(baseDashboard, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            const data = await response.json();
            if (!data.ok) throw new Error(data.message || 'Could not save the package.');

            closeModal();
            await refreshCatalog(activeTab, false);
            window.dispatchEvent(new CustomEvent('manager:catalog-updated', { detail: data }));
        } catch (error) {
            errorBox.hidden = false;
            errorBox.textContent = error.message || 'Could not save the package.';
        } finally {
            submitBtn.disabled = false;
            submitBtn.classList.remove('is-busy');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });

    const bookingJump = document.querySelector('.js-bookings-jump');
    if (bookingJump) {
        bookingJump.addEventListener('click', function (event) {
            const target = document.getElementById('bookings');
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            history.replaceState({}, '', '#bookings');
        });
    }
})();

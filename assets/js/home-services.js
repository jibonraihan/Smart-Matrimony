(function () {
    'use strict';

    const services = Array.isArray(window.SMART_SERVICE_DATA) ? window.SMART_SERVICE_DATA : [];
    const packagesByService = window.SMART_SERVICE_PACKAGES || {};
    const baseUrl = window.SMART_SERVICE_BASE_URL || '/';
    const loggedIn = Boolean(window.SMART_SERVICE_LOGGED_IN);
    const csrf = window.SMART_SERVICE_CART_CSRF || '';

    const modal = document.getElementById('servicePackageModal');
    const content = document.getElementById('servicePackageContent');
    const title = document.getElementById('servicePackageTitle');
    const description = document.getElementById('servicePackageDescription');
    const counter = document.getElementById('servicePackageCounter');
    const prev = document.getElementById('servicePackagePrev');
    const next = document.getElementById('servicePackageNext');
    const loginLink = document.getElementById('serviceLoginLink');

    // Desktop: Services opens on hover through CSS.
    // Small/medium screens: Services is a menu trigger. The dropdown is
    // positioned independently of the Bootstrap collapse so it never
    // changes the header height or gets clipped by the menu container.
    const servicesNav = document.querySelector('.smart-nav-services');
    const servicesToggle = document.querySelector('.smart-nav-services-toggle');

    function positionMobileServicesDropdown() {
        if (!servicesNav || !servicesToggle || window.innerWidth > 991.98) return;
        const dropdown = servicesNav.querySelector('.smart-services-dropdown');
        if (!dropdown) return;

        const rect = servicesToggle.getBoundingClientRect();
        const viewportPadding = window.innerWidth <= 575.98 ? 14 : 20;
        const dropdownWidth = Math.min(315, window.innerWidth - (viewportPadding * 2));
        // Center the dropdown beneath the actual Services control rather
        // than anchoring it to the left edge of the navigation row.
        const centeredLeft = rect.left + (rect.width / 2) - (dropdownWidth / 2);
        const left = Math.max(
            viewportPadding,
            Math.min(centeredLeft, window.innerWidth - dropdownWidth - viewportPadding)
        );

        dropdown.style.setProperty('--smart-services-mobile-left', `${left}px`);
        // Give the menu a small visual gap so it never covers the Services label.
        dropdown.style.setProperty('--smart-services-mobile-top', `${rect.bottom + 14}px`);
    }

    function closeMobileServicesDropdown() {
        if (!servicesNav) return;
        servicesNav.classList.remove('is-open');
        if (servicesToggle) servicesToggle.setAttribute('aria-expanded', 'false');
    }

    if (servicesNav && servicesToggle) {
        servicesToggle.addEventListener('click', (event) => {
            if (window.innerWidth <= 991.98) {
                event.preventDefault();
                event.stopPropagation();
                const isOpen = servicesNav.classList.toggle('is-open');
                servicesToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                if (isOpen) requestAnimationFrame(positionMobileServicesDropdown);
            }
        });

        window.addEventListener('resize', () => {
            if (!servicesNav.classList.contains('is-open')) return;
            if (window.innerWidth <= 991.98) {
                positionMobileServicesDropdown();
            } else {
                closeMobileServicesDropdown();
            }
        });

        document.addEventListener('scroll', () => {
            if (servicesNav.classList.contains('is-open') && window.innerWidth <= 991.98) {
                positionMobileServicesDropdown();
            }
        }, true);

        document.addEventListener('shown.bs.collapse', (event) => {
            if (event.target && event.target.id === 'navbar' && servicesNav.classList.contains('is-open')) {
                positionMobileServicesDropdown();
            }
        });

        document.addEventListener('hidden.bs.collapse', (event) => {
            if (event.target && event.target.id === 'navbar') closeMobileServicesDropdown();
        });
    }

    if (!modal || !content) return;

    let currentServiceId = 0;
    let currentIndex = 0;
    let currentPackages = [];

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const money = (value) => {
        const number = Number(value || 0);
        return '৳' + number.toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    function getService(serviceId) {
        return services.find((service) => Number(service.service_id) === Number(serviceId)) || null;
    }

    function updateNavigation() {
        const multiple = currentPackages.length > 1;
        prev.hidden = !multiple;
        next.hidden = !multiple;
        prev.disabled = !multiple;
        next.disabled = !multiple;
        counter.textContent = multiple ? `${currentIndex + 1} of ${currentPackages.length} packages` : (currentPackages.length ? '1 package' : '');
    }

    function renderPackage() {
        if (!currentPackages.length) {
            content.innerHTML = `
                <div class="service-modal-empty">
                    <span><i class="fa-regular fa-folder-open"></i></span>
                    <h3>No packages available yet</h3>
                    <p>This service is available, but no active provider package has been added yet.</p>
                </div>`;
            updateNavigation();
            return;
        }

        const pkg = currentPackages[currentIndex];
        const image = pkg.image
            ? `<img src="${baseUrl}uploads/services/${escapeHtml(pkg.image)}" alt="${escapeHtml(pkg.provider_name)}">`
            : `<div class="service-modal-image-fallback"><i class="fa-solid fa-store"></i></div>`;

        const details = pkg.package_details
            ? escapeHtml(pkg.package_details)
            : 'Package details will be provided by the service provider.';

        const addAction = loggedIn
            ? `<form method="post" action="${baseUrl}cart.php" class="service-modal-cart-form">
                    <input type="hidden" name="csrf" value="${escapeHtml(csrf)}">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="provider_id" value="${Number(pkg.provider_id)}">
                    <input type="hidden" name="return_service_id" value="${Number(currentServiceId)}">
                    <button type="submit" class="service-modal-add-btn"><i class="fa-solid fa-cart-plus"></i> Add to Cart</button>
                </form>`
            : `<button type="button" class="service-modal-add-btn service-guest-cart-btn"><i class="fa-solid fa-cart-plus"></i> Add to Cart</button>`;

        content.innerHTML = `
            <article class="service-modal-package-card">
                <div class="service-modal-package-image">${image}
                    <span><i class="fa-solid fa-star"></i> ${Number(pkg.rating || 0).toFixed(1)} <small>(${Number(pkg.review_count || 0)})</small></span>
                </div>
                <div class="service-modal-package-body">
                    <div class="service-modal-package-heading">
                        <div>
                            <span class="service-modal-kicker">PACKAGE</span>
                            <h3>${escapeHtml(pkg.package_name || pkg.provider_name)}</h3>
                            <p><i class="fa-solid fa-building-user"></i> ${escapeHtml(pkg.provider_name)}</p>
                        </div>
                        <strong>${money(pkg.price)}</strong>
                    </div>
                    <div class="service-modal-package-facts">
                        ${pkg.location ? `<span><i class="fa-solid fa-location-dot"></i>${escapeHtml(pkg.location)}</span>` : ''}
                        <span><i class="fa-solid fa-list-check"></i>${details}</span>
                    </div>
                    <div class="service-modal-package-bottom">
                        <div><small>Package Price</small><strong>${money(pkg.price)}</strong></div>
                        ${addAction}
                    </div>
                </div>
            </article>`;

        const guestButton = content.querySelector('.service-guest-cart-btn');
        if (guestButton) {
            guestButton.addEventListener('click', () => showGuestMessage());
        }

        updateNavigation();
    }

    function showGuestMessage() {
        const message = 'Registration or Login to Smart Martimony to Access all available services';
        if (window.Swal) {
            Swal.fire({
                icon: 'info',
                title: 'Login Required',
                text: message,
                confirmButtonText: 'Login / Register',
                showCancelButton: true,
                cancelButtonText: 'Continue Browsing',
                confirmButtonColor: '#0f766e'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = baseUrl + 'login.php';
            });
        } else {
            window.alert(message);
        }
    }

    function openService(serviceId) {
        const service = getService(serviceId);
        if (!service) return;

        currentServiceId = Number(service.service_id);
        currentPackages = Array.isArray(packagesByService[currentServiceId]) ? packagesByService[currentServiceId] : [];
        currentIndex = 0;

        title.textContent = service.service_name || 'Wedding Service';
        description.textContent = service.description || 'Explore available packages and provider details.';
        if (loginLink) loginLink.style.display = loggedIn ? 'none' : 'inline-flex';

        renderPackage();
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('service-modal-open');
    }

    function closeService() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('service-modal-open');
    }

    document.querySelectorAll('.home-service-card[data-service-id]').forEach((button) => {
        button.addEventListener('click', () => openService(Number(button.dataset.serviceId)));
    });

    document.querySelectorAll('[data-home-service]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (!document.querySelector('.home-page')) return;
            event.preventDefault();
            const serviceId = Number(link.dataset.homeService);
            openService(serviceId);
            closeMobileServicesDropdown();
            const servicesSection = document.getElementById('home-services');
            if (servicesSection) servicesSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    document.querySelectorAll('[data-service-modal-close]').forEach((element) => {
        element.addEventListener('click', closeService);
    });

    prev.addEventListener('click', () => {
        if (!currentPackages.length) return;
        currentIndex = (currentIndex - 1 + currentPackages.length) % currentPackages.length;
        renderPackage();
    });

    next.addEventListener('click', () => {
        if (!currentPackages.length) return;
        currentIndex = (currentIndex + 1) % currentPackages.length;
        renderPackage();
    });

    document.addEventListener('keydown', (event) => {
        if (!modal.classList.contains('is-open')) return;
        if (event.key === 'Escape') closeService();
        if (event.key === 'ArrowLeft') prev.click();
        if (event.key === 'ArrowRight') next.click();
    });

    document.addEventListener('click', (event) => {
        if (!servicesNav || !servicesNav.classList.contains('is-open')) return;
        if (window.innerWidth > 991.98) return;
        if (!servicesNav.contains(event.target)) closeMobileServicesDropdown();
    });

    // If a visitor arrives from a Services dropdown link, open that package viewer automatically.
    const queryService = new URLSearchParams(window.location.search).get('service');
    if (queryService && getService(Number(queryService))) {
        setTimeout(() => openService(Number(queryService)), 120);
    }
}());

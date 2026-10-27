(function () {
    'use strict';

    var selector = [
        '.featured-package-image',
        '.service-modal-package-image',
        '.package-card .package-thumb',
        '.budget-package-image',
        '.provider-image'
    ].join(',');

    var imageSelector = [
        '.featured-package-image img',
        '.service-modal-package-image img',
        '.package-card .package-thumb img',
        '.budget-package-image img',
        '.provider-image img'
    ].join(',');

    if (!document.getElementById('sm-package-image-preview-styles')) {
        var style = document.createElement('style');
        style.id = 'sm-package-image-preview-styles';
        style.textContent = `
            .featured-package-image img,
            .service-modal-package-image img,
            .package-card .package-thumb img,
            .budget-package-image img,
            .provider-image img {
                object-fit: cover !important;
                object-position: center !important;
                cursor: zoom-in;
            }
            .featured-package-image,
            .service-modal-package-image,
            .package-card .package-thumb,
            .budget-package-image,
            .provider-image {
                position: relative;
            }
            .sm-package-preview-trigger {
                position: absolute;
                z-index: 5;
                top: 8px;
                right: 8px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 31px;
                height: 31px;
                padding: 0;
                border: 1px solid rgba(255,255,255,.8);
                border-radius: 50%;
                background: rgba(16, 36, 43, .68);
                color: #fff;
                box-shadow: 0 2px 9px rgba(0,0,0,.18);
                cursor: zoom-in;
                -webkit-tap-highlight-color: transparent;
            }
            .featured-package-image .sm-package-preview-trigger,
            .service-modal-package-image .sm-package-preview-trigger {
                top: auto;
                bottom: 8px;
            }
            .sm-package-preview-trigger:hover,
            .sm-package-preview-trigger:focus-visible {
                background: rgba(15,118,110,.96);
                outline: 2px solid rgba(255,255,255,.9);
                outline-offset: 1px;
            }
            .sm-package-image-lightbox {
                position: fixed;
                z-index: 2147483000;
                inset: 0;
                display: none;
                align-items: center;
                justify-content: center;
                box-sizing: border-box;
                padding: 24px;
                background: rgba(8, 15, 22, .88);
                cursor: zoom-out;
            }
            .sm-package-image-lightbox.is-open { display: flex; }
            .sm-package-image-lightbox img {
                display: block;
                width: auto;
                height: auto;
                max-width: 94vw;
                max-height: 90vh;
                object-fit: contain;
                border-radius: 3px;
                box-shadow: 0 18px 65px rgba(0,0,0,.4);
                cursor: default;
            }
            .sm-package-image-lightbox-close {
                position: absolute;
                top: max(12px, env(safe-area-inset-top));
                right: max(14px, env(safe-area-inset-right));
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 42px;
                height: 42px;
                border: 1px solid rgba(255,255,255,.42);
                border-radius: 50%;
                background: rgba(255,255,255,.12);
                color: #fff;
                font-size: 28px;
                line-height: 1;
                cursor: pointer;
            }
            .sm-package-image-lightbox-close:hover { background: rgba(255,255,255,.24); }
            @media (max-width: 600px) {
                .sm-package-preview-trigger { width: 29px; height: 29px; top: 6px; right: 6px; }
                .sm-package-image-lightbox { padding: 12px; }
                .sm-package-image-lightbox img { max-width: 96vw; max-height: 88vh; }
            }
        `;
        document.head.appendChild(style);
    }

    var lightbox = document.getElementById('smPackageImageLightbox');
    if (!lightbox) {
        lightbox = document.createElement('div');
        lightbox.id = 'smPackageImageLightbox';
        lightbox.className = 'sm-package-image-lightbox';
        lightbox.setAttribute('role', 'dialog');
        lightbox.setAttribute('aria-modal', 'true');
        lightbox.setAttribute('aria-label', 'Package image preview');
        lightbox.innerHTML = '<button type="button" class="sm-package-image-lightbox-close" aria-label="Close image preview">&times;</button><img alt="">';
        document.body.appendChild(lightbox);
    }

    var previewImage = lightbox.querySelector('img');
    var closeButton = lightbox.querySelector('.sm-package-image-lightbox-close');
    var previousFocus = null;

    function openPreview(img) {
        if (!img || !img.src) return;
        previousFocus = document.activeElement;
        previewImage.src = img.currentSrc || img.src;
        previewImage.alt = img.alt || 'Package image';
        lightbox.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        closeButton.focus();
    }

    function closePreview() {
        if (!lightbox.classList.contains('is-open')) return;
        lightbox.classList.remove('is-open');
        previewImage.removeAttribute('src');
        document.body.style.overflow = '';
        if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
    }

    function addTriggers(scope) {
        var root = scope || document;
        if (root.matches && root.matches(selector)) addTriggerToBox(root);
        if (root.querySelectorAll) root.querySelectorAll(selector).forEach(addTriggerToBox);
    }

    function addTriggerToBox(box) {
        if (!box || box.querySelector('.sm-package-preview-trigger')) return;
        var img = box.querySelector('img');
        if (!img) return;
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'sm-package-preview-trigger';
        button.setAttribute('aria-label', 'View full-size package image');
        button.setAttribute('title', 'View full-size image');
        button.innerHTML = '<i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i>';
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            openPreview(img);
        });
        box.appendChild(button);
        img.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            openPreview(img);
        });
    }

    closeButton.addEventListener('click', closePreview);
    lightbox.addEventListener('click', function (event) {
        if (event.target === lightbox) closePreview();
    });
    previewImage.addEventListener('click', function (event) {
        event.stopPropagation();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closePreview();
    });

    addTriggers(document);
    if (window.MutationObserver) {
        var observer = new MutationObserver(function (records) {
            records.forEach(function (record) {
                record.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    addTriggers(node);

                    // Budget Finder replaces the image inside an existing .budget-package-image
                    // after changing a package. The added node is the IMG, not its container, so
                    // scan its closest supported container and attach a fresh preview button.
                    var images = [];
                    if (node.matches && node.matches('img')) images.push(node);
                    if (node.querySelectorAll) images = images.concat(Array.prototype.slice.call(node.querySelectorAll('img')));
                    images.forEach(function (img) {
                        var box = img.closest ? img.closest(selector) : null;
                        if (!box) return;
                        var existing = box.querySelector('.sm-package-preview-trigger');
                        if (existing) existing.remove();
                        addTriggerToBox(box);
                    });
                });
            });
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
    }
})();
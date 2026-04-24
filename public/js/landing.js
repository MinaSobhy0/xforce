/* ============================================
   XLinic Landing — Client scripts
   ============================================ */

(function () {
    'use strict';

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- Scroll reveal ---------- */
    function initReveal() {
        if (prefersReducedMotion) {
            document.querySelectorAll('.reveal').forEach(el => el.classList.add('is-visible'));
            return;
        }

        if (!('IntersectionObserver' in window)) {
            document.querySelectorAll('.reveal').forEach(el => el.classList.add('is-visible'));
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
    }

    /* ---------- Counter animation ---------- */
    function animateCounter(el, target, duration = 1400) {
        if (prefersReducedMotion) {
            el.textContent = target.toLocaleString(document.documentElement.lang || 'en');
            return;
        }

        const locale = document.documentElement.lang || 'en';
        const start = performance.now();
        const format = (n) => Number(n).toLocaleString(locale);

        function frame(now) {
            const elapsed = now - start;
            const progress = Math.min(elapsed / duration, 1);
            // easeOutQuart
            const eased = 1 - Math.pow(1 - progress, 4);
            const value = Math.round(target * eased);
            el.textContent = format(value);

            if (progress < 1) {
                requestAnimationFrame(frame);
            } else {
                el.textContent = format(target);
            }
        }

        requestAnimationFrame(frame);
    }

    function initCounters() {
        const counters = document.querySelectorAll('.counter');
        if (!counters.length) return;

        if (!('IntersectionObserver' in window)) {
            counters.forEach(el => {
                const target = parseFloat(el.dataset.target || '0');
                animateCounter(el, target);
            });
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const target = parseFloat(el.dataset.target || '0');
                    animateCounter(el, target);
                    observer.unobserve(el);
                }
            });
        }, { threshold: 0.4 });

        counters.forEach(el => observer.observe(el));
    }

    /* ---------- Spline 3D fallback ---------- */
    function initSplineFallback() {
        const fallback = document.getElementById('spline-fallback');
        if (!fallback) return;

        // If web component doesn't register within 8s, show fallback
        setTimeout(() => {
            const viewer = document.querySelector('spline-viewer');
            if (!viewer || !customElements.get('spline-viewer')) {
                if (viewer) viewer.style.display = 'none';
                fallback.classList.remove('hidden');
                fallback.classList.add('flex');
            }
        }, 8000);

        // Handle explicit error
        const viewer = document.querySelector('spline-viewer');
        if (viewer) {
            viewer.addEventListener('error', () => {
                viewer.style.display = 'none';
                fallback.classList.remove('hidden');
                fallback.classList.add('flex');
            });
        }
    }

    /* ---------- Phone input ---------- */
    function initPhoneInput() {
        const phone = document.getElementById('phone');
        if (!phone || typeof window.intlTelInput !== 'function') return;

        const iti = window.intlTelInput(phone, {
            initialCountry: 'eg',
            preferredCountries: ['eg', 'sa', 'ae', 'kw', 'qa'],
            separateDialCode: true,
            utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js',
        });

        // Sync country select when phone country changes
        const countrySelect = document.getElementById('country');
        if (countrySelect) {
            phone.addEventListener('countrychange', () => {
                const data = iti.getSelectedCountryData();
                if (data && data.iso2 && countrySelect.querySelector(`option[value="${data.iso2.toUpperCase()}"]`)) {
                    countrySelect.value = data.iso2.toUpperCase();
                }
            });
        }

        // Rewrite phone value with full international format on submit
        const form = document.getElementById('contact-form');
        if (form) {
            form.addEventListener('submit', () => {
                try {
                    const full = iti.getNumber();
                    if (full) phone.value = full;
                } catch (e) { /* ignore */ }
            });
        }
    }

    /* ---------- Contact form submission ---------- */
    function initContactForm() {
        const form = document.getElementById('contact-form');
        if (!form) return;

        const submitBtn = document.getElementById('submit-btn');
        const defaultText = submitBtn?.querySelector('[data-default-text]');
        const loadingText = submitBtn?.querySelector('[data-loading-text]');

        form.addEventListener('submit', async (e) => {
            // reCAPTCHA v3 token if enabled
            if (window.__recaptchaSiteKey && window.grecaptcha) {
                e.preventDefault();
                try {
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        defaultText?.classList.add('hidden');
                        loadingText?.classList.remove('hidden');
                    }
                    const token = await window.grecaptcha.execute(window.__recaptchaSiteKey, { action: 'contact' });
                    document.getElementById('recaptcha_token').value = token;
                    form.submit();
                } catch (err) {
                    console.error('reCAPTCHA failed', err);
                    form.submit();
                }
            } else {
                // Just show loading state, let form submit normally
                if (submitBtn) {
                    submitBtn.disabled = true;
                    defaultText?.classList.add('hidden');
                    loadingText?.classList.remove('hidden');
                }
            }
        });
    }

    /* ---------- Scroll to hash with offset ---------- */
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(link => {
            link.addEventListener('click', (e) => {
                const href = link.getAttribute('href');
                if (!href || href === '#') return;
                const target = document.querySelector(href);
                if (!target) return;
                e.preventDefault();
                const top = target.getBoundingClientRect().top + window.pageYOffset - 72;
                window.scrollTo({ top, behavior: prefersReducedMotion ? 'auto' : 'smooth' });
            });
        });
    }

    /* ---------- Theme toggle (dark <-> light) ---------- */
    function initThemeToggle() {
        const btn = document.getElementById('theme-toggle');
        if (!btn) return;

        function currentTheme() {
            return document.documentElement.classList.contains('light') ? 'light' : 'dark';
        }

        btn.addEventListener('click', () => {
            const next = currentTheme() === 'dark' ? 'light' : 'dark';
            document.documentElement.classList.toggle('light', next === 'light');
            try { localStorage.setItem('xlinic-theme', next); } catch (e) { /* ignore */ }
            btn.setAttribute('aria-label', next === 'light' ? 'Switch to dark mode' : 'Switch to light mode');
        });

        // Initial aria-label reflects the action the button will perform
        btn.setAttribute('aria-label', currentTheme() === 'light' ? 'Switch to dark mode' : 'Switch to light mode');
    }

    /* ---------- Init ---------- */
    function init() {
        initThemeToggle();
        initReveal();
        initCounters();
        initSplineFallback();
        initPhoneInput();
        initContactForm();
        initSmoothScroll();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

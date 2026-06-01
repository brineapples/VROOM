/* ============================================
   TemplateMo 3D Glassmorphism Dashboard
   https://templatemo.com
   JavaScript
============================================ */

(function() {
    'use strict';

    // ============================================
    // Mobile Menu Toggle
    // ============================================
    function initMobileMenu() {
        const menuToggle = document.querySelector('.mobile-menu-toggle');
        const sidebar = document.getElementById('sidebar');
        
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
            });

            // Close sidebar when clicking outside
            document.addEventListener('click', (e) => {
                if (sidebar.classList.contains('open') && 
                    !sidebar.contains(e.target) && 
                    !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            });
        }
    }

    // ============================================
    // Form Validation (for login/register)
    // ============================================
    function initFormValidation() {
        const forms = document.querySelectorAll('form[data-validate]');
        
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                
                let isValid = true;
                const inputs = form.querySelectorAll('.form-input[required]');
                
                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        isValid = false;
                        input.style.borderColor = '#ff6b6b';
                    } else {
                        input.style.borderColor = '';
                    }
                });

                // Email validation
                const emailInput = form.querySelector('input[type="email"]');
                if (emailInput && emailInput.value) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(emailInput.value)) {
                        isValid = false;
                        emailInput.style.borderColor = '#ff6b6b';
                    }
                }

                if (isValid) {
                    // Form is valid - you can add your submission logic here
                    console.log('Form is valid');
                    // For demo purposes, redirect to dashboard
                    if (form.dataset.redirect) {
                        window.location.href = form.dataset.redirect;
                    }
                }
            });
        });
    }

    // ============================================
    // Password Visibility Toggle
    // ============================================
    function initPasswordToggle() {
        const toggleButtons = document.querySelectorAll('.password-toggle');
        
        toggleButtons.forEach(button => {
            button.addEventListener('click', () => {
                const input = button.parentElement.querySelector('input');
                const icon = button.querySelector('svg');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
                } else {
                    input.type = 'password';
                    icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
                }
            });
        });
    }

    // ============================================
    // Settings Tab Navigation
    // ============================================
    function initSettingsTabs() {
        const tabLinks = document.querySelectorAll('.settings-nav-link[data-tab]');
        
        if (tabLinks.length === 0) return;

        tabLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Get target tab
                const tabId = link.getAttribute('data-tab');
                
                // Remove active class from all nav links
                document.querySelectorAll('.settings-nav-link').forEach(navLink => {
                    navLink.classList.remove('active');
                });
                
                // Add active class to clicked link
                link.classList.add('active');
                
                // Hide all tab contents
                document.querySelectorAll('.settings-tab-content').forEach(tab => {
                    tab.classList.remove('active');
                });
                
                // Show target tab content
                const targetTab = document.getElementById('tab-' + tabId);
                if (targetTab) {
                    targetTab.classList.add('active');
                }
            });
        });

    }

    // ============================================
    // Initialize All Functions
    // ============================================
    function init() {
        initMobileMenu();
        initFormValidation();
        initPasswordToggle();
        initSettingsTabs();
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

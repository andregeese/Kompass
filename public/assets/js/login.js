/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

const loginComponent = () => ({
    isRegister: false,
    isForgotPassword: false,
    loading: false,
    formData: {
        email: '',
        password: '',
        app_key: '',
        paperless_url: '',
        paperless_name: '',
        paperless_token: ''
    },
    switchToForgotPassword() {
        this.isForgotPassword = true;
        this.isRegister = false;
        this.formData.password = '';
        this.formData.app_key = '';
    },
    switchToLogin() {
        this.isForgotPassword = false;
        this.isRegister = false;
        this.formData.password = '';
        this.formData.app_key = '';
    },
    async handleSubmit() {
        this.loading = true;

        // Route dynamisch bestimmen
        let action = 'login';
        if (this.isForgotPassword) {
            action = 'reset-password';
        } else if (this.isRegister) {
            action = 'register';
        }

        const payload = {
            email: this.formData.email,
            password: this.formData.password
        };

        if (this.isForgotPassword) {
            payload.app_key = this.formData.app_key;
        }

        if (this.isRegister) {
            payload.paperless_url   = this.formData.paperless_url;
            payload.paperless_name  = this.formData.paperless_name;
            payload.paperless_token = this.formData.paperless_token;
        }

        try {
            const data = await apiCall(action, payload, 'POST');

            // 1. CSRF-Token aktualisieren (KORREKTUR: liegt jetzt unter details.csrf_token)
            const newToken = data?.details?.csrf_token;
            if (newToken) {
                let meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) {
                    meta.content = newToken;
                } else {
                    meta = document.createElement('meta');
                    meta.name = 'csrf-token';
                    meta.content = newToken;
                    document.head.appendChild(meta);
                }
            }

            // 2. Erfolg verarbeiten
            if (data?.success) {
                if (action === 'login') {
                    // Bei Erfolg direkt weiterleiten
                    window.location.href = 'index.php?view=start';
                } else if (action === 'reset-password') {
                    this.switchToLogin();
                } else {
                    this.switchToLogin();
                }
            } 
            // Hinweis: Wenn data.success === false ist, hat safeFetch/apiCall 
            // das showNotify dank details.ui.notify bereits automatisch ausgelöst!

        } catch (err) {
            console.error("Auth-Error:", err);
        } finally {
            this.loading = false;
        }
    }
});

// Saubere Registrierung für Alpine
if (window.Alpine) {
    Alpine.data('loginComponent', loginComponent);
} else {
    document.addEventListener('alpine:init', () => {
        Alpine.data('loginComponent', loginComponent);
    });
}
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

        // Felder für Passwort-Reset hinzufügen
        if (this.isForgotPassword) {
            payload.app_key = this.formData.app_key;
        }

        // Felder für Registrierung hinzufügen
        if (this.isRegister) {
            payload.paperless_url = this.formData.paperless_url;
            payload.paperless_name = this.formData.paperless_name;
            payload.paperless_token = this.formData.paperless_token;
        }

        try {
            const data = await apiCall(action, payload, 'POST');
            if (data?.success) {
                const newToken = data.csrf_token; 
                
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

                if (action === 'login') {
                    if (typeof showNotify === 'function') {
                        showNotify('Erfolgreich', 'Login erfolgreich! Leite weiter...', 'success');
                    }
                    window.location.href = 'index.php?view=start';
                    this.currentView = 'start';
                } else if (action === 'reset-password') {
                    if (typeof showNotify === 'function') {
                        showNotify('Erfolgreich', 'Passwort wurde erfolgreich geändert. Du kannst dich jetzt einloggen.', 'success');
                    } else {
                        alert('Passwort erfolgreich geändert.');
                    }
                    this.switchToLogin();
                } else {
                    if (typeof showNotify === 'function') {
                        showNotify('Erfolgreich', 'Registrierung abgeschlossen. Du kannst dich jetzt einloggen.', 'success');
                    }
                    this.switchToLogin();
                }
            } else {
                if (typeof showNotify === 'function') {
                    showNotify('Authentifizierung fehlgeschlagen', data?.error || 'Fehler bei der Anfrage.', 'danger');
                } else {
                    alert(data?.error || 'Fehler bei der Anfrage.');
                }
            }
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
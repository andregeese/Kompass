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
        loading: false,
        formData: {
            email: '',
            password: '',
            paperless_url: '',
            paperless_name: '',
            paperless_token: ''
        },
        async handleSubmit() {
            this.loading = true;
            const action = this.isRegister ? 'register' : 'login';
            const payload = {
                email: this.formData.email,
                password: this.formData.password
            };
            // 2. Felder für Register hinzufügen, falls nötig
            if (this.isRegister) {
                payload.paperless_url = this.formData.paperless_url;
                payload.paperless_name = this.formData.paperless_name;
                payload.paperless_token = this.formData.paperless_token;
            }
            try {
                const data = await apiCall(action, payload, 'POST');
                if (data?.success) {
                    // Greife auf data.csrf_token zu, nicht auf res
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
                        // Leite den Browser auf die Mail-Ansicht um
                        window.location.href = 'index.php?view=start';
                        this.currentView = 'start'; // Optional, falls du die Ansicht im Frontend ändern möchtest
                    }else {
                        // Wenn es eine Registrierung war, schalte das Formular um
                        if (typeof showNotify === 'function') {
                            showNotify('Erfolgreich', 'Registrierung abgeschlossen. Du kannst dich jetzt einloggen.', 'success');
                        }
                        this.isRegister = false;
                        this.formData.password = ''; // Passwort aus Sicherheitsgründen leeren
                    }
                } else {
                    // Jetzt passt die Struktur wieder korrekt zum if-Block
                    if (typeof showNotify === 'function') {
                        showNotify('Authentifizierung fehlgeschlagen', data?.error || 'Fehler beim Login.', 'danger');
                    } else {
                        alert(data?.error || 'Fehler beim Login.');
                    }
                }
            } catch (err) {
                console.error("Auth-Error:", err);
            } finally {
                this.loading = false;
            }
        }
});
// Saubere Registrierung für Alpine (ohne Dopplungen)
if (window.Alpine) {
    Alpine.data('detailView', loginComponent);
} else {
    document.addEventListener('alpine:init', () => {
        Alpine.data('detailView', loginComponent);
    });
}
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
            
            const fd = new FormData();
            fd.append('email', this.formData.email);
            fd.append('password', this.formData.password);
            
            if (this.isRegister) {
                fd.append('paperless_url', this.formData.paperless_url);
                fd.append('paperless_name', this.formData.paperless_name);
                fd.append('paperless_token', this.formData.paperless_token);
            }

            try {
                // Nutzt den in index.php definierten globalen Endpunkt
                const data = await safeFetch(`${window.CRM_API_URL}?action=${action}`, {
                    method: 'POST',
                    body: fd
                });
                
                
                if (data.success) {
                    // Erfolgreich! Wir leiten den User auf die Standard-Ansicht (Mails) um
                    window.location.href = './index.php?view=mails';
                } else {
                    if (typeof showNotify === 'function') {
                        showNotify('Authentifizierung fehlgeschlagen', data.error || 'Fehler beim Login.', 'danger');
                    } else {
                        alert(data.error || 'Fehler beim Login.');
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
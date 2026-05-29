const appointmentComponent = () => ({
    loading: true,
    appointments: [],
    async fetchAppointments() {
        try {
            // Ersetze 'deine-api-route' durch deinen Endpunkt, der die ICloudConnector Methode aufruft
            const response = await fetch('/api/appointments');
            this.appointments = await response.json();
        } catch (e) {
            console.error("Fehler beim Laden:", e);
        } finally {
            this.loading = false;
        }
    }
});


// Sicherheits-Registrierung am Ende von mail.js
if (window.Alpine) {
    // Falls Alpine bereits voll geladen ist
    window.Alpine.data('contactsView', contactsViewComponent);
} else {
    // Falls Alpine noch auf den Start wartet
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('mailViecontactsVieww', contactsViewComponent);
    });
}
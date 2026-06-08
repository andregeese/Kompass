document.addEventListener('alpine:init', () => {
    
    // KOMPONENTE: Modal Editor
    Alpine.data('appointmentEditor', () => ({
        isOpen: false,
        formData: { id: null, title: '', ceid: '' },
        contacts: [],
        contactSearch:'',
        filteredContacts: [],

        async performContactSearch() {
            if (this.contactSearch.length < 2) return;
            
            this.loading = true;
            // Backend Suche statt clientseitiger Filterung bei 1000+ Kontakten
            const response = await apiCall('get_emails_by_search', { query: this.contactSearch }, 'GET');
            this.filteredContacts = response.details.contact_emails || [];; 
            this.loading = false;
        },
        
        async loadAppointment(id) {
            const response = await apiCall('get_appointment', { id: id }, 'GET');
            this.formData = response.details; 


            if (response.success && response.details.appointment.length > 0) {
                // WICHTIG: Das erste Element aus dem Array nehmen!
                const apt = response.details.appointment[0];
                
                // Mappe die DB-Felder auf deine FormData-Struktur
                this.formData = {
                    id: apt.id,
                    title: apt.summary, // Dein JSON sagt "summary"
                    ceid: apt.ceid     // Dein JSON sagt "ce_id"
                };
                // Zurücksetzen der Suche
                this.contactSearch = '';
                this.filteredContacts = [];
                this.isOpen = true;
                console.log("Modal geöffnet mit:", this.formData);
            } else {
                console.error("Kein Termin gefunden oder API Fehler");
            }
        },

        async save() {
            const response = await apiCall('save_appointment', this.formData, 'POST');
            if (response.success) {
                this.isOpen = false;
                window.dispatchEvent(new CustomEvent('refresh-calendar'));
            }
        }
    }));

    // KOMPONENTE: Kalender Ansicht
    Alpine.data('appointmentView', () => ({
        loading: true,
        calendar: null,

        async initCalendar() {
            const calendarEl = document.getElementById('calendar');
            this.calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'de',
                events: async (fetchInfo, successCallback, failureCallback) => {
                    const data = await apiCall('get_appointments', {}, 'GET');
                    this.loading = false;
                    // Mappe die Daten aus deinem JSON-Struktur-Screenshot
                    successCallback(data.details.appointments.map(item => ({
                        title: item.title,
                        start: item.start,
                        id: item.id
                    })));
                },
                eventClick: (info) => {
                    window.dispatchEvent(new CustomEvent('open-appointment-modal', { detail: info.event.id }));
                }
            });
            this.calendar.render();

            // Listener für Refresh
            window.addEventListener('refresh-calendar', () => {
                this.calendar.refetchEvents();
            });
        },
        
        async syncAppointments(){
            //await apiCall('full_sync', {}, 'GET');
            this.initCalendar();
        }
    }));
});
const appointmentViewComponent = () => ({
    loading: true,

    async initCalendar() {
        // Warte kurz, damit Alpine das DOM bereitstellt
        await new Promise(resolve => setTimeout(resolve, 50));

        const calendarEl = document.getElementById('calendar');
        if (!calendarEl) return;

        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next',
                center: 'title',
                right: 'today'
            },
            dayMaxEvents: 3, // Zeigt "+ X weitere" an, statt den Kalender zu sprengen
            eventTimeFormat: { // Zeit im Stil "13:00" statt "13 Uhr"
                hour: '2-digit',
                minute: '2-digit',
                meridiem: false
            },
            locale: 'de',
            height: 'auto',
            events: async (fetchInfo, successCallback, failureCallback) => {
                try {
                    const data = await safeFetch(`api.php?action=get_appointments`);
                    console.log("APPOINTMENTS Data:", data);

                    // Mapping: Überprüfe genau deine API-Struktur aus dem Screenshot
                    const events = data.details.appointment.map(item => ({
                        title: item.title,      // Achte hier auf die Namen aus der DB!
                        start: item.start,
                        id: item.id
                    }));
                    
                    this.loading = false;
                    successCallback(events);
                } catch (e) {
                    this.loading = false;
                    failureCallback(e);
                }
            },
            eventClick: (info) => {
                alert('Termin: ' + info.event.title);
            }
        });
        calendar.render();
    }
});

// Registrierung für Alpine
document.addEventListener('alpine:init', () => {
    Alpine.data('appointmentView', appointmentViewComponent);
});
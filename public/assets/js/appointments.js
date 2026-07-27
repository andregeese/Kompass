/**
 * Kompass Hub - Time Travel Appointments Module
 * @copyright 2026
 */

const appointmentViewComponent = () => ({
    appointments: [],
    loading: false,
    currentStartDate: null, // Hält das JS-Date-Objekt für den Start des Fensters
    daysWindow: 7,          // 7 Tage Ansicht
    dateRangeText: '',
    calendarWeekText: '',

    initComponent() {
        
        window.addEventListener('refresh-appointments', () => {
            this.loadAppointmentsForWindow();
        });

        // Beim Start direkt auf HEUTE initialisieren
        this.goToToday();

        this.loadAppointmentsForWindow();
        
    },

    // Setzt das Fenster hart auf den heutigen Tag zurück
    goToToday() {
        this.currentStartDate = new Date();
        this.loadAppointmentsForWindow();
    },

    // Schiebt das Zeitfenster vor (+) oder zurück (-)
    navigateWindow(days) {
        const newDate = new Date(this.currentStartDate);
        newDate.setDate(newDate.getDate() + days);
        this.currentStartDate = newDate;
        this.loadAppointmentsForWindow();
    },

    // Berechnet Labels und feuert den API-Call ab, inkl. Termine über Zeitraum
    async loadAppointmentsForWindow() {
        this.loading = true;
        const endDate = new Date(this.currentStartDate);
        endDate.setDate(endDate.getDate() + (this.daysWindow - 1));

        try {
            const response = await apiCall('get_appointments', { 
                start_date: this.formatIsoDate(this.currentStartDate), 
                end_date: this.formatIsoDate(endDate) 
            }, 'GET');

            // Keine Manipulationen, kein new Date(), kein Händeschütteln. Reiner Datendurchsatz.
            this.appointments = response.success ? response.details.appointments : [];
        } catch (error) {
            console.error("Fehler beim Laden der Termine:", error);
            this.appointments = [];
        } finally {
            this.loading = false;
        }
    },

    // Hilfsfunktion: Formatiert die Anzeige-Texte für Zeitraum und KWs
    updateUiLabels(start, end) {
        const options = { day: '2-digit', month: '2-digit', year: 'numeric' };
        
        const startStr = start.toLocaleDateString('de-DE', options);
        const endStr = end.toLocaleDateString('de-DE', options);
        this.dateRangeText = `${startStr} - ${endStr}`;

        // KW ermitteln
        const kwStart = this.getIsoWeek(start);
        const kwEnd = this.getIsoWeek(end);
        
        // Wenn das Fenster zwei KWs schneidet, zeigen wir beide an (z.B. KW 22 / 23)
        this.calendarWeekText = kwStart === kwEnd ? `Kalenderwoche ${kwStart}` : `KW ${kwStart} – KW ${kwEnd}`;
    },

    // Reines JavaScript zur Ermittlung der echten ISO-Kalenderwoche
    getIsoWeek(date) {
        const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
        const dayNum = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
        const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    },

    // Hilfsfunktion für YYYY-MM-DD Formatierung
    formatIsoDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    },

    async syncAppointments() {
        this.loading = true;
        try {
            await apiCall('full_sync', {}, 'GET');
            await this.loadAppointmentsForWindow();
        } catch (error) {
            console.error("Fehler beim Cloud-Sync:", error);
        } finally {
            this.loading = false;
        }
    },

    handleItemClick(appt) {
        if (appt.ceid) {
            this.$dispatch('navigate', { view: 'contacts', id: appt.ceid });
        } else if (appt.uid) {
            this.$dispatch('navigate', { view: 'mails', id: appt.uid });
        } else {
            this.$dispatch('open-appointment-modal', appt);
        }
    },

    isToday(dateObj) {
        if (!dateObj) return false;
        const today = new Date();
        return dateObj.getDate() === today.getDate() &&
            dateObj.getMonth() === today.getMonth() &&
            dateObj.getFullYear() === today.getFullYear();
    },
    
    // NEU & SMARTER: Erkennt den echten Wochenwechsel zwischen zwei Einträgen
    isNewWeekTransition(appt, index) {
        if (index === 0) return false; 
        
        // Nutzt jetzt das präzise display_date
        const currentWeek = this.getIsoWeek(appt.display_date);
        const prevWeek = this.getIsoWeek(this.appointments[index - 1].display_date);
        
        return (currentWeek !== prevWeek) || (appt.display_date.getFullYear() !== this.appointments[index - 1].display_date.getFullYear());
    },

    // Hilfsfunktion 3: Kombiniert dynamisch alle Tailwind-Klassen
    getItemClasses(appt, index) {
        let classes = "group flex items-center gap-4 p-3 rounded-xl border transition-all cursor-pointer ";

        // Abfrage basiert nun auf dem berechneten display_date Objekt
        if (this.isToday(appt.display_date)) {
            classes += "bg-blue-50/40 dark:bg-blue-950/15 border-blue-100 dark:border-blue-900/40 shadow-sm ";
        } else {
            classes += "border-transparent hover:bg-slate-50 dark:hover:bg-gray-750/50 hover:border-slate-100 dark:hover:border-gray-750 ";
        }

        if (this.isNewWeekTransition(appt, index)) {
            classes += "border-t border-t-slate-200 dark:border-t-gray-750 pt-5 mt-5 rounded-t-none ";
        }

        return classes;
    }
});

const appointmentEditorComponent = () => ({
    isOpen: false,
    loading: false,
    
    emailQuery: '',
    loadingMatch: false,
    sortedMatchResults: [],

    // Formulardaten basierend auf deiner DB-Struktur
    form: {
        id: null,
        summary: '',
        start_time: '',
        end_time: '',
        ceid: null,
        location: ''
    },

    init() {
        // Lauscht auf das Event aus der View-Liste
        window.addEventListener('open-appointment-modal', (e) => {
            this.open(e.detail);
        });
    },

    open(appt = null) {
        this.reset();
        if (appt) {
            // Bearbeiten-Modus: Daten befüllen
            // (Substrings und 'T'-Replacements sorgen dafür, dass datetime-local Inputs das Format fressen)
            this.form = {
                id: appt.id,
                summary: appt.summary || appt.title || '',
                start_time: appt.start_time ? appt.start_time.replace(' ', 'T').substring(0, 16) : '',
                end_time: appt.end_time ? appt.end_time.replace(' ', 'T').substring(0, 16) : '',
                ceid: appt.ceid || null,
                location: appt.location || ''
            };
        } else {
            // Neu-Modus: Leeres Formular
            this.reset();
        }
        this.isOpen = true;
    },

    close() {
        this.isOpen = false;
        this.reset();
    },

    reset() {
        this.form = {
            id: null,
            summary: '',
            start_time: '',
            end_time: '',
            ceid: null,
            location: ''
        };
    },

    async performMatchSearch() {
        const query = this.emailQuery.trim();
        if (query.length < 2) {
            this.sortedMatchResults = [];
            return;
        }

        this.loadingMatch = true;
        try {
            
            const response = await apiCall('get_emails_by_search', { query: query }, 'GET');
            
            if (response.success) {
                // Unterstützt sowohl direkte Arrays als auch verschachtelte details.contacts Strukturen
                this.sortedMatchResults = response.details.contact_emails || response.details || [];
            } else {
                this.sortedMatchResults = [];
            }
        } catch (error) {
            console.error('Fehler bei der Live-Kontaktsuche:', error);
            this.sortedMatchResults = [];
        } finally {
            this.loadingMatch = false;
        }
    },
    
    async toggleContactLink(contact) {
        // Prüfen, ob dieser Kontakt bereits mit dem aktuellen Formular verknüpft ist
        const isLinked = this.form.ceid === contact.ceid;

        // 1. Lokalen Zustand sofort umschalten (UI reagiert blitzschnell)
        this.form.ceid = isLinked ? '' : contact.ceid;

        // 2. WICHTIG: Wenn der Termin brandneu ist (noch keine ID hat),
        // brechen wir hier ab. Die ceid wird dann beim finalen Speichern mit angelegt.
        if (!this.form.id) {
            return; 
        }

        // 3. Wenn der Termin existiert, direkt ab in die DB damit!
        this.loadingMatch = true;
        try {
            const response = await apiCall('update_appointment_contact', {
                appointment_id: this.form.id,
                ceid: this.form.ceid, // Schickt entweder die neue ceid oder null (beim Entkoppeln)
                action: isLinked ? 'unlink' : 'link'
            }, 'POST');

            if (response.success) {
                // Optionale Kür: Hauptliste im Hintergrund direkt aktualisieren
                window.dispatchEvent(new CustomEvent('refresh-appointments'));
            } else {
                alert(response.message || 'Fehler beim Aktualisieren der Verbindung.');
                // Fallback: Bei Fehler den alten Zustand wiederherstellen
                this.form.ceid = isLinked ? contact.ceid : null;
            }
        } catch (error) {
            console.error('Fehler beim Live-Verknüpfen:', error);
            this.form.ceid = isLinked ? contact.ceid : null;
        } finally {
            this.loadingMatch = false;
        }
    },

    async save() {
        this.loading = true;
        try {
            // Schickt die sauberen Formulardaten an deine api.php
            const response = await apiCall('save_appointment', this.form, 'POST');
            
            if (response.success) {
                // Schließt das Modal
                this.close();
                
                // Triggert den Full-Sync / die Aktualisierung der Hauptliste
                // (Deine viewComponent sollte auf dieses Event lauschen oder du rufst dort direkt die Lademethode auf)
                window.dispatchEvent(new CustomEvent('refresh-appointments'));
            } else {
                alert(response.message || 'Fehler beim Speichern des Termins.');
            }
        } catch (error) {
            console.error('Fehler im Editor-Speicherprozess:', error);
        } finally {
            this.loading = false;
        }
    }
});

document.addEventListener('alpine:init', () => {
    Alpine.data('appointmentView', appointmentViewComponent);
    Alpine.data('appointmentEditor', appointmentEditorComponent);
});
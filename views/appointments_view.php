<div x-data="appointmentComponent()" x-init="fetchAppointments()" class="px-6 py-12 lg:px-8 w-full max-w-4xl mx-auto">
    <div class="sm:mx-auto sm:w-full">
        <h2 class="text-center text-3xl font-bold leading-9 tracking-tight text-slate-900 dark:text-white">
            Deine iCloud Termine
        </h2>
    </div>

    <div x-show="loading" class="mt-10 text-center text-gray-500">
        <p>Lade Termine aus iCloud...</p>
    </div>

    <div x-show="!loading" class="mt-10 bg-white dark:bg-gray-800 rounded-2xl shadow-xl overflow-hidden">
        <ul role="list" class="divide-y divide-gray-200 dark:divide-gray-700">
            <template x-for="appointment in appointments" :key="appointment.id">
                <li class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-blue-600 dark:text-blue-400" x-text="appointment.date"></p>
                            <p class="text-lg font-semibold text-slate-900 dark:text-white" x-text="appointment.title"></p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Aktiv</span>
                    </div>
                </li>
            </template>
        </ul>
        
        <div x-show="appointments.length === 0" class="p-8 text-center text-gray-500">
            Keine Termine gefunden.
        </div>
    </div>
</div>

<script>
function appointmentComponent() {
    return {
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
    }
}
</script>
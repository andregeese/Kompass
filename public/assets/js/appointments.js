export default function appointmentsModule(core) {
    return {
        async init_appointments() {
            if (this.isOnline) {
                const data = await safeFetch('api.php?action=crm_get_appointments');
                if (data?.success) {
                    this.appointments = data.details.appointments;
                    localStorage.setItem('cached_appointments', JSON.stringify(this.appointments));
                }
            }
        }
    };
}
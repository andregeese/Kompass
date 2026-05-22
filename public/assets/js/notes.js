export default function notesModule(core) {
    return {
        async init_notes() {
            if (this.isOnline) {
                const data = await safeFetch('api.php?action=crm_get_notes');
                if (data?.success) {
                    this.notes = data.details.notes;
                    localStorage.setItem('cached_notes', JSON.stringify(this.notes));
                }
            }
        },

        addNote() {
            const content = this.newNoteContent?.trim();
            if (!content) return;

            const payload = { id: 'local_' + Date.now(), content, status: 'pending' };
            this.notes.unshift(payload);
            localStorage.setItem('cached_notes', JSON.stringify(this.notes));

            if (this.isOnline) {
                const res = await safeFetch('api.php?action=crm_save_note', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                if (res?.success) payload.id = res.details.db_id;
            } else {
                let queue = JSON.parse(localStorage.getItem('queue_notes') || '[]');
                queue.push(payload);
                localStorage.setItem('queue_notes', JSON.stringify(queue));
            }
            this.newNoteContent = '';
        },

        async toggleNoteStatus(noteId) {
            const note = this.notes.find(n => n.id === noteId);
            if (!note) return;
            note.status = note.status === 'completed' ? 'pending' : 'completed';
            localStorage.setItem('cached_notes', JSON.stringify(this.notes));

            if (this.isOnline) {
                await safeFetch('api.php?action=crm_toggle_note', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: noteId, status: note.status })
                });
            }
        },

        async syncOfflineNotes() {
            let queue = JSON.parse(localStorage.getItem('queue_notes') || '[]');
            if (!queue.length) return;
            for (const item of queue) {
                await safeFetch('api.php?action=crm_save_note', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(item)
                });
            }
            localStorage.removeItem('queue_notes');
            this.init_notes();
        }
    };
}
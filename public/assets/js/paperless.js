/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

export default function paperlessModule(core) {
    return {
        async init_paperless() {
            // Einstiegspunkt für DMS spezifisches Setup falls benötigt
        },
        async findMatchSuggestions(searchQuery) {
            let clean = searchQuery.replace(/^(Re|Aw|Fwd):\s*/i, '').trim();
            try {
                const data = await safeFetch(`api.php?action=paperless_search&query=${encodeURIComponent(clean)}`);
                if (data?.success) this.paperlessMatches = data.details.documents || [];
            } catch (err) { console.error(err); }
        },
        async linkToPaperless(docId) {
            if (!confirm(`Mit Dokument #${docId} verknüpfen?`)) return;
            const fd = new FormData();
            fd.append('id', this.selectedId);
            fd.append('paperless_id', docId);
            const data = await safeFetch('api.php?action=paperless_match', { method: 'POST', body: fd });
            if (data?.success) {
                this.currentMail.is_dms = true;
                this.currentMail.paperless_id = docId;
            }
        },
        async moveToDMS(mail, tag = 'Recht') {
            const fd = new FormData();
            fd.append('id', mail.hash);
            fd.append('tag', tag);
            await safeFetch('api.php?action=dms', { method: 'POST', body: fd });
            mail.paperless_id = true;
        }
    };
}
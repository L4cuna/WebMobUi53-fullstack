/**
 * Store centralisé pour les sondages.
 */

import { ref } from 'vue';
import { useFetchApi } from '@/composables/useFetchApi';

// État global (partagé entre toutes les instances du store)
const polls = ref([]);

export function usePollStore() {
    const { fetchApi } = useFetchApi();

    // Initialise la liste depuis les données Blade (évite un appel API au démarrage)
    function setPolls(data) {
        polls.value = data;
    }

    // Crée un sondage via POST /api/v1/polls et l'ajoute en tête de liste
    async function createPoll(data) {
        const newPoll = await fetchApi({ url: 'polls', method: 'POST', data });
        polls.value = [newPoll, ...polls.value];
        return newPoll;
    }

    // Met à jour un sondage via PUT /api/v1/polls/:id et rafraîchit la liste
    async function updatePoll(id, data) {
        const updated = await fetchApi({ url: `polls/${id}`, method: 'PUT', data });
        const index = polls.value.findIndex(p => p.id === id);
        if (index !== -1) polls.value[index] = updated; // remplacement réactif
        return updated;
    }

    // Supprime un sondage via DELETE et retire l'élément du tableau
    async function deletePoll(id) {
        await fetchApi({ url: `polls/${id}`, method: 'DELETE' });
        polls.value = polls.value.filter(p => p.id !== id);
    }

    return { polls, setPolls, createPoll, updatePoll, deletePoll };
}
<script setup>
/**
 * Tableau des sondages du dashboard.
 * affiche la liste et signaler les actions au parent.
 *
 * Concepts :
 * - defineProps : reçoit le tableau polls du parent
 * - defineEmits : signale edit/delete/copy-link vers le parent
 * - v-for : boucle sur les sondages (la :key est obligatoire pour Vue)
 * - :class binding dynamique selon l'état du sondage
 */

defineProps({
    polls: { type: Array, required: true },
});

const emit = defineEmits(['edit', 'delete', 'copy-link']);

// Retourne le statut lisible et la classe CSS correspondante
function getStatus(poll) {
    if (poll.is_draft) return { label: 'Brouillon', css: 'bg-gray-100 text-gray-600' };
    if (poll.ends_at && new Date(poll.ends_at) < new Date()) {
        return { label: 'Terminé', css: 'bg-red-100 text-red-600' };
    }
    return { label: 'Actif', css: 'bg-green-100 text-green-700' };
}

function formatDate(d) {
    if (! d) return '—';
    return new Date(d).toLocaleString('fr-CH', { dateStyle: 'short', timeStyle: 'short' });
}
</script>

<template>
    <!-- Message vide -->
    <p v-if="polls.length === 0" class="text-center text-gray-400 py-8">
        Aucun sondage. Créez-en un !
    </p>

    <!-- Tableau -->
    <div v-else class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="border-b text-gray-500">
                <tr>
                    <th class="py-2 pr-4 font-medium">Sondage</th>
                    <th class="py-2 pr-4 font-medium">État</th>
                    <th class="py-2 pr-4 font-medium">Votes</th>
                    <th class="py-2 pr-4 font-medium">Fin</th>
                    <th class="py-2 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- :key obligatoire sur v-for pour que Vue identifie chaque ligne -->
                <tr v-for="poll in polls" :key="poll.id" class="border-b hover:bg-gray-50">

                    <td class="py-3 pr-4">
                        <p class="font-medium">{{ poll.title || poll.question }}</p>
                        <p v-if="poll.title" class="text-xs text-gray-400 truncate max-w-xs">
                            {{ poll.question }}
                        </p>
                    </td>

                    <td class="py-3 pr-4">
                        <!-- :class lie dynamiquement les classes selon l'état -->
                        <span :class="getStatus(poll).css"
                            class="text-xs px-2 py-0.5 rounded-full font-medium">
                            {{ getStatus(poll).label }}
                        </span>
                    </td>

                    <td class="py-3 pr-4 text-gray-500">{{ poll.votes_count ?? 0 }}</td>

                    <td class="py-3 pr-4 text-gray-400 text-xs">{{ formatDate(poll.ends_at) }}</td>

                    <td class="py-3">
                        <div class="flex gap-1 flex-wrap">
                            <!-- @click écoute le clic et émet l'événement vers le parent -->
                            <button @click="emit('copy-link', poll)"
                                class="px-2 py-1 text-xs rounded bg-blue-50 text-blue-600 hover:bg-blue-100">
                                🔗 Lien
                            </button>
                            <button @click="emit('edit', poll)"
                                class="px-2 py-1 text-xs rounded bg-yellow-50 text-yellow-700 hover:bg-yellow-100">
                                ✏️ Éditer
                            </button>
                            <button @click="emit('delete', poll.id)"
                                class="px-2 py-1 text-xs rounded bg-red-50 text-red-600 hover:bg-red-100">
                                🗑 Supprimer
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
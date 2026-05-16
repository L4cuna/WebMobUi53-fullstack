<script setup>
/**
 * Tableau des sondages du dashboard.
 * affiche la liste et signaler les actions au parent.
 */

defineProps({
    polls: { type: Array, required: true },
});
 
const emit = defineEmits(['edit', 'delete', 'copy-link']);
 
function getStatus(poll) {
    if (poll.is_draft) return { label: 'Brouillon', css: 'bg-gray-100 text-gray-600' };
    if (poll.ends_at) {
        const date = poll.ends_at.includes('T') ? new Date(poll.ends_at) : new Date(poll.ends_at + 'Z');
        if (date < new Date()) return { label: 'Terminé', css: 'bg-red-100 text-red-600' };
    }
    return { label: 'Actif', css: 'bg-green-100 text-green-700' };
}
 
function formatDate(d) {
    if (! d) return null;
    // Ajoute 'Z' seulement si la date ne contient pas déjà de timezone
    const date = d.includes('T') ? new Date(d) : new Date(d + 'Z');
    return date.toLocaleString('fr-CH', { dateStyle: 'short', timeStyle: 'short' });
}
</script>
 
<template>
    <p v-if="polls.length === 0" class="text-center text-gray-400 py-8">
        Aucun sondage. Créez-en un !
    </p>
 
    <div v-else class="space-y-3">
        <div v-for="poll in polls" :key="poll.id"
            class="border rounded-lg p-4 hover:bg-gray-50">
 
            <!-- Titre + badge état -->
            <div class="flex items-start justify-between gap-2 mb-2">
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-900 truncate">
                        {{ poll.title || poll.question }}
                    </p>
                    <p v-if="poll.title" class="text-xs text-gray-400 truncate mt-0.5">
                        {{ poll.question }}
                    </p>
                </div>
                <span :class="getStatus(poll).css"
                    class="text-xs px-2 py-0.5 rounded-full font-medium shrink-0">
                    {{ getStatus(poll).label }}
                </span>
            </div>
 
            <!-- Infos -->
            <div class="flex gap-4 text-xs text-gray-400 mb-3">
                <span>{{ poll.votes_count ?? 0 }} vote{{ (poll.votes_count ?? 0) > 1 ? 's' : '' }}</span>
                <span v-if="formatDate(poll.ends_at)">Fin : {{ formatDate(poll.ends_at) }}</span>
            </div>
 
            <!-- Actions -->
            <div class="flex gap-2">
                <button @click="emit('copy-link', poll)"
                    class="flex-1 py-1.5 text-xs rounded bg-blue-50 text-blue-600 hover:bg-blue-100">
                    🔗 Lien
                </button>
                <button @click="emit('edit', poll)"
                    class="flex-1 py-1.5 text-xs rounded bg-yellow-50 text-yellow-700 hover:bg-yellow-100">
                    ✏️ Éditer
                </button>
                <button @click="emit('delete', poll.id)"
                    class="flex-1 py-1.5 text-xs rounded bg-red-50 text-red-600 hover:bg-red-100">
                    🗑 Supprimer
                </button>
            </div>
 
        </div>
    </div>
</template>
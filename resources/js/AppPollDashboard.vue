<script setup>
/**
 * Composant racine du dashboard des sondages.
 */

import { ref, computed } from 'vue';
import PollTable from '@/components/PollTable.vue';
import PollForm from '@/components/PollForm.vue';
import { usePollStore } from '@/stores/usePollStore';

const props = defineProps({
    polls:    { type: Array, default: () => [] },
    loginUrl: { type: String, default: null },
    username: { type: String, default: null },
});

const { polls, setPolls, createPoll, updatePoll, deletePoll } = usePollStore();
// Chargement initial depuis les données Blade (eager loading, pas d'appel API)
setPolls(props.polls);

// Vue active : 'list' | 'create' | 'edit'
const view       = ref('list');
const editTarget = ref(null);  // sondage en cours d'édition

// computed : le titre se recalcule automatiquement quand view ou editTarget changent
const title = computed(() => {
    if (view.value === 'create') return 'Nouveau sondage';
    if (view.value === 'edit')   return `Éditer : ${editTarget.value?.title || editTarget.value?.question}`;
    return 'Mes sondages';
});

// Notification flash locale
const flash = ref(null);
function notify(msg, type = 'success') {
    flash.value = { msg, type };
    setTimeout(() => { flash.value = null; }, 3000);
}

// ── Handlers reçus des enfants ──────────────────────────────────────────────

function onEdit(poll) {
    editTarget.value = poll;
    view.value = 'edit';
}

async function onDelete(id) {
    if (! confirm('Supprimer ce sondage ?')) return;
    try {
        await deletePoll(id);
        notify('Sondage supprimé.');
} catch (e) {
        notify('Erreur lors de la suppression.', 'error');
    }
}

function onCopyLink(poll) {
    const url = `${window.location.origin}/polls/${poll.secret_token}`;
    navigator.clipboard.writeText(url).then(() => notify('Lien copié !'));
}

async function onFormSubmit(data) {
    try {
        if (view.value==="create") {
            await createPoll(data);
            notify('Sondage créé !');
        } else {
            await updatePoll(editTarget.value.id, data);
            notify('Sondage mis à jour.');
        }
        view.value = 'list';
        editTarget.value = null;
} catch (e) {
        notify(e?.data?.message || 'Une erreur est survenue.', 'error');
    }
}

function onCancel() {
    view.value = 'list';
    editTarget.value = null;
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 p-4 sm:p-6">
        <div class="max-w-4xl mx-auto">

            <!-- En-tête -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-xl font-bold text-gray-900">{{ title }}</h1>
                    <a href="/" class="text-xs text-gray-400 hover:underline">← Retour au site</a>
                </div>
                <button v-if="view === 'list'" @click="view = 'create'"
                    class="px-4 py-2 text-sm bg-teal-600 text-white rounded hover:bg-teal-700">
                    + Nouveau sondage
                </button>
            </div>

            <!-- Flash -->
            <div v-if="flash" :class="flash.type === 'error'
                    ? 'bg-red-50 text-red-700 border-red-200'
                    : 'bg-green-50 text-green-700 border-green-200'"
                class="mb-4 px-4 py-2 rounded border text-sm">
                {{ flash.msg }}
            </div>

            <!-- Contenu -->
            <div class="bg-white rounded-lg border p-6">

                <PollTable v-if="view === 'list'"
                    :polls="polls"
                    @edit="onEdit"
                    @delete="onDelete"
                    @copy-link="onCopyLink"
                />

                <!-- Mode création : PollForm sans poll (valeurs par défaut) -->
                <PollForm v-else-if="view === 'create'"
                    @submit="onFormSubmit"
                    @cancel="onCancel"
                />

                <!-- Mode édition : PollForm avec le sondage existant -->
                <PollForm v-else-if="view === 'edit'"
                    :poll="editTarget"
                    @submit="onFormSubmit"
                    @cancel="onCancel"
                />
            </div>

        </div>
    </div>
</template>
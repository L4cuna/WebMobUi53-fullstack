<script setup>
import { ref, computed, onMounted } from 'vue';
import { useFetchApi } from '@/composables/useFetchApi';
import { usePolling } from '@/composables/usePolling';

const props = defineProps({
    token:           { type: String, required: true },
    isAuthenticated: { type: Boolean, default: false },
});

const { fetchApi } = useFetchApi();

const poll        = ref(null);
const loadError   = ref(null);
const isLoading   = ref(true);
const selectedIds = ref([]);
const voteError   = ref('');
const isVoting    = ref(false);

async function loadPoll() {
    try {
        const data = await fetchApi({ url: `polls/${props.token}` });
        poll.value = data;
        if (data.user_voted_option_ids?.length) {
            selectedIds.value = [...data.user_voted_option_ids];
        }
    } catch (err) {
        loadError.value = err.status === 404 ? 'Ce sondage n\'existe pas.' : 'Erreur de chargement.';
    } finally {
        isLoading.value = false;
    }
}

onMounted(loadPoll);
// rafraîchit les résultats toutes les 5s — le composable gère clearInterval automatiquement
usePolling(loadPoll, 5000);

const isExpired = computed(() =>
    poll.value?.ends_at && new Date(poll.value.ends_at) < new Date()
);

const canVote = computed(() => {
    if (! poll.value || ! props.isAuthenticated) return false;
    if (poll.value.is_draft || isExpired.value)  return false;
    if (poll.value.user_has_voted && ! poll.value.allow_vote_change) return false;
    return true;
});

const canSeeResults = computed(() => {
    if (! poll.value)              return false;
    if (poll.value.is_owner)       return true;
    if (poll.value.results_public) return true;
    if (poll.value.user_has_voted) return true;
    return false;
});

const totalVotes = computed(() =>
    poll.value?.options?.reduce((sum, o) => sum + (o.votes_count ?? 0), 0) ?? 0
);

function toggleOption(id) {
    if (! poll.value.allow_multiple_choices) {
        selectedIds.value = [id]; // choix unique : on remplace
    } else {
        const i = selectedIds.value.indexOf(id);
        if (i === -1) selectedIds.value.push(id);
        else          selectedIds.value.splice(i, 1);
    }
}

async function submitVote() {
    if (selectedIds.value.length === 0) {
        voteError.value = 'Sélectionnez au moins une option.';
        return;
    }
    isVoting.value  = true;
    voteError.value = '';
    try {
        await fetchApi({
            url:    `polls/${props.token}/vote`,
            method: 'POST',
            data:   { option_ids: selectedIds.value },
        });
        await loadPoll();
    } catch (err) {
        voteError.value = err.data?.message || 'Erreur lors du vote.';
    } finally {
        isVoting.value = false;
    }
}

function percent(option) {
    if (totalVotes.value === 0) return 0;
    return Math.round(((option.votes_count ?? 0) / totalVotes.value) * 100);
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 py-8 px-4">
        <div class="max-w-lg mx-auto space-y-4">

            <div v-if="isLoading" class="text-center text-gray-400 py-16">Chargement…</div>

            <div v-else-if="loadError"
                class="bg-red-50 border border-red-200 rounded-lg p-6 text-red-600 text-center">
                {{ loadError }}
            </div>

            <template v-else-if="poll">

                <!-- En-tête -->
                <div class="bg-white rounded-lg border p-5">
                    <h1 class="text-lg font-bold text-gray-900">{{ poll.title || poll.question }}</h1>
                    <p v-if="poll.title" class="text-gray-500 mt-1 text-sm">{{ poll.question }}</p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <span v-if="isExpired"
                            class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-600">
                            ⏰ Sondage terminé — vote fermé
                        </span>
                        <span v-else-if="poll.is_draft"
                            class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">
                            Brouillon
                        </span>
                        <span v-else
                            class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-600">
                            Sondage actif
                        </span>
                        <span v-if="poll.ends_at && !isExpired"
                            class="text-xs px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700">
                            Fin : {{ new Date(poll.ends_at).toLocaleString('fr-CH') }}
                        </span>
                        <span v-if="poll.allow_multiple_choices"
                            class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-600">
                            Choix multiples
                        </span>
                    </div>
                </div>

                <!-- Vote -->
                <div class="bg-white rounded-lg border p-5">
                    <div v-if="!isAuthenticated" class="text-center py-4">
                        <p class="text-gray-500 mb-3 text-sm">Connectez-vous pour voter.</p>
                        <a href="/auth/login"
                            class="px-4 py-2 bg-teal-600 text-white text-sm rounded hover:bg-teal-700">
                            Se connecter
                        </a>
                    </div>

                    <div v-else-if="canVote">
                        <p class="font-medium text-sm mb-3">
                            {{ poll.user_has_voted ? 'Modifier votre vote' : 'Votez' }}
                        </p>
                        <div class="space-y-2">
                            <label v-for="option in poll.options" :key="option.id"
                                class="flex items-center gap-3 p-3 border rounded cursor-pointer hover:bg-slate-50"
                                :class="{ 'border-teal-500 bg-teal-50': selectedIds.includes(option.id) }">
                                <input
                                    :type="poll.allow_multiple_choices ? 'checkbox' : 'radio'"
                                    :checked="selectedIds.includes(option.id)"
                                    @change="toggleOption(option.id)"
                                    class="accent-teal-600" />
                                <span class="text-sm">{{ option.label }}</span>
                            </label>
                        </div>
                        <p v-if="voteError" class="text-red-500 text-sm mt-2">{{ voteError }}</p>
                        <button @click="submitVote"
                            :disabled="isVoting || selectedIds.length === 0"
                            class="mt-4 w-full py-2 bg-teal-600 text-white text-sm rounded hover:bg-teal-700 disabled:opacity-50">
                            {{ isVoting ? 'Envoi…' : (poll.user_has_voted ? 'Modifier le vote' : 'Voter') }}
                        </button>
                    </div>

                    <div v-else-if="poll.user_has_voted" class="text-green-600 text-sm text-center py-3">
                        ✓ Vote enregistré.
                    </div>
                    <div v-else-if="isExpired" class="text-gray-500 text-sm text-center py-3">
                        Le vote est fermé.
                    </div>
                </div>

                <!-- Résultats avec graphique à barres CSS -->
                <div v-if="canSeeResults" class="bg-white rounded-lg border p-5">
                    <h2 class="font-medium text-sm mb-4 text-gray-700">
                        Résultats — {{ totalVotes }} vote{{ totalVotes > 1 ? 's' : '' }}
                        <span class="text-xs text-gray-400">(actualisé toutes les 5s)</span>
                    </h2>
                    <div class="space-y-3">
                        <div v-for="option in poll.options" :key="option.id">
                            <div class="flex justify-between text-sm mb-1">
                                <span>{{ option.label }}</span>
                                <span class="text-gray-400">{{ option.votes_count ?? 0 }} ({{ percent(option) }}%)</span>
                            </div>
                            <!-- largeur proportionnelle au pourcentage, transition fluide -->
                            <div class="h-4 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-teal-500 rounded-full transition-all duration-500"
                                    :style="{ width: percent(option) + '%' }">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="!poll.is_owner && !poll.results_public && !poll.user_has_voted"
                    class="bg-white rounded-lg border p-5 text-center text-gray-400 text-sm">
                    🔒 Les résultats seront visibles après votre vote.
                </div>

            </template>
        </div>
    </div>
</template>
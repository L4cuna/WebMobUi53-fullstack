<script setup>
/**
 * Formulaire de création ET d'édition d'un sondage.
 * Réutilisable : si `poll` est fourni → mode édition, sinon → mode création.
 */

import { ref, computed } from 'vue';

const props = defineProps({
    poll: { type: Object, default: null },
});

const emit = defineEmits(['submit', 'cancel']);

// Champs du formulaire initialisés avec les valeurs existantes (édition) ou par défaut (création)
const title    = ref(props.poll?.title ?? '');
const question = ref(props.poll?.question ?? '');
// Les options sont des chaînes simples dans le formulaire
const options  = ref(props.poll?.options?.map(o => o.label) ?? ['', '']);

const is_draft               = ref(!!(props.poll?.is_draft ?? true));
const allow_multiple_choices = ref(!!(props.poll?.allow_multiple_choices ?? false));
const allow_vote_change      = ref(!!(props.poll?.allow_vote_change ?? false));
const results_public         = ref(!!(props.poll?.results_public ?? false));
// On stocke la durée en minutes dans le formulaire, l'API reçoit des secondes
const durationMinutes = ref(props.poll?.duration ? Math.round(props.poll.duration / 60) : null);

const isEditMode  = computed(() => props.poll !== null);
const submitLabel = computed(() => isEditMode.value ? 'Enregistrer' : 'Créer');

const errorMsg = ref('');

// Gestion des options (ajout/suppression de lignes)
function addOption() {
    options.value.push('');
}

function removeOption(index) {
    if (options.value.length > 2) options.value.splice(index, 1);
}

function handleSubmit() {
    // Validation minimale locale avant d'envoyer
    if (! question.value.trim()) {
        errorMsg.value = 'La question est obligatoire.';
        return;
    }
    const filledOptions = options.value.filter(o => o.trim());
    if (filledOptions.length < 2) {
        errorMsg.value = 'Il faut au moins 2 options remplies.';
        return;
    }

    errorMsg.value = '';

    // On émet les données vers le parent (AppPollDashboard)
    // C'est le parent qui appelle l'API via le store → séparation des responsabilités
    emit('submit', {
        title:                  title.value.trim() || null,
        question:               question.value.trim(),
        options:                filledOptions,
        is_draft:               is_draft.value,
        allow_multiple_choices: allow_multiple_choices.value,
        allow_vote_change:      allow_vote_change.value,
        results_public:         results_public.value,
        duration:               durationMinutes.value ? durationMinutes.value * 60 : null,
    });
}
</script>

<template>
    <form @submit.prevent="handleSubmit" class="space-y-4">

        <!-- Titre optionnel -->
        <div>
            <label class="block text-sm font-medium mb-1">Titre (optionnel)</label>
            <input v-model="title" type="text" placeholder="Titre du sondage"
                class="w-full border rounded px-3 py-2 text-sm" />
        </div>

        <!-- Question obligatoire -->
        <div>
            <label class="block text-sm font-medium mb-1">Question *</label>
            <input v-model="question" type="text" placeholder="Votre question…"
                class="w-full border rounded px-3 py-2 text-sm"
                :class="{ 'border-red-400': errorMsg && !question.trim() }" />
        </div>

        <!-- Options de réponse -->
        <div>
            <label class="block text-sm font-medium mb-2">Options *</label>
            <!-- v-for sur un tableau de strings, v-model lie chaque champ à options[index] -->
            <div v-for="(_, index) in options" :key="index" class="flex gap-2 mb-2">
                <input v-model="options[index]" type="text" :placeholder="`Option ${index + 1}`"
                    class="flex-1 border rounded px-3 py-2 text-sm" />
                <button type="button" @click="removeOption(index)"
                    :disabled="options.length <= 2"
                    class="text-red-400 disabled:opacity-30 px-2">✕</button>
            </div>
            <button type="button" @click="addOption"
                class="text-sm text-teal-600 hover:underline">+ Ajouter une option</button>
        </div>

        <!-- Paramètres -->
        <fieldset class="border rounded p-3 space-y-2">
            <legend class="text-sm font-medium px-1">Paramètres</legend>

            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" v-model="is_draft" />
                Brouillon (non publié)
            </label>

            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" v-model="allow_multiple_choices" />
                Autoriser plusieurs choix
            </label>

            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" v-model="allow_vote_change" />
                Autoriser la modification du vote
            </label>

            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" v-model="results_public" />
                Résultats publics (visibles sans connexion)
            </label>

            <div>
                <label class="block text-sm mb-1">Durée (minutes, optionnel)</label>
                <input v-model.number="durationMinutes" type="number" min="1" placeholder="Ex: 60"
                    class="w-28 border rounded px-3 py-2 text-sm" />
            </div>
        </fieldset>

        <!-- Message d'erreur -->
        <p v-if="errorMsg" class="text-red-500 text-sm">{{ errorMsg }}</p>

        <!-- Actions -->
        <div class="flex justify-between pt-2">
            <button type="button" @click="emit('cancel')"
                class="px-4 py-2 text-sm bg-gray-100 rounded hover:bg-gray-200">
                Annuler
            </button>
            <button type="submit"
                class="px-4 py-2 text-sm bg-teal-600 text-white rounded hover:bg-teal-700">
                {{ submitLabel }}
            </button>
        </div>

    </form>
</template>
<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiPollController extends Controller
{
    /**
     * GET /api/v1/polls
     * Liste les sondages de l'utilisateur connecté.     *
     * withCount('votes') ajoute un champ votes_count calculé par SQL,
     */
    public function index(Request $request)
    {
        $polls = $request->user()
            ->polls()
            ->with('options')
            ->withCount('votes')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($polls);
    }

    /**
     * GET /api/v1/polls/{token}
     * Affiche un sondage via son token. Route publique (sans auth requise).
     *
     * Logique d'accès :
     * - Brouillon → seul le créateur voit
     * - Résultats non publics → votes_count masqués pour les non-créateurs
     * - Si connecté → on indique les options déjà votées par l'utilisateur
     */
    public function show(Request $request, string $token)
    {
        $poll = Poll::with(['options' => fn($q) => $q->withCount('votes')])
            ->where('secret_token', $token)
            ->first();

        if (! $poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        $user    = $request->user(); // null si anonyme
        $isOwner = $user && $user->id === $poll->user_id;

        if ($poll->is_draft && ! $isOwner) {
            return response()->json(['message' => 'Ce sondage n\'est pas disponible.'], 403);
        }

        // makeHidden masque le champ dans la réponse JSON
        if (! $poll->results_public && ! $isOwner) {
            $poll->options->each(fn($o) => $o->makeHidden('votes_count'));
        }

        // Infos de vote de l'utilisateur connecté
        if ($user) {
            $votedIds = PollVote::where('poll_id', $poll->id)
                ->where('user_id', $user->id)
                ->pluck('poll_option_id');

            $poll->setAttribute('user_voted_option_ids', $votedIds);
            $poll->setAttribute('user_has_voted', $votedIds->isNotEmpty());
        }

        $poll->setAttribute('is_owner', $isOwner);

        return response()->json($poll);
    }

    /**
     * POST /api/v1/polls
     * Crée un nouveau sondage avec ses options.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'question'               => 'required|string|max:500',
            'title'                  => 'nullable|string|max:255',
            'options'                => 'required|array|min:2',
            'options.*'              => 'required|string|max:255',
            'is_draft'               => 'boolean',
            'allow_multiple_choices' => 'boolean',
            'allow_vote_change'      => 'boolean',
            'results_public'         => 'boolean',
            'duration'               => 'nullable|integer|min:1', // durée en secondes
        ]);

        $poll = new Poll();
        $poll->user_id                = $request->user()->id;
        $poll->question               = $validated['question'];
        $poll->title                  = $validated['title'] ?? null;
        $poll->secret_token           = Str::random(32);
        $poll->is_draft               = $validated['is_draft'] ?? true;
        $poll->allow_multiple_choices = $validated['allow_multiple_choices'] ?? false;
        $poll->allow_vote_change      = $validated['allow_vote_change'] ?? false;
        $poll->results_public         = $validated['results_public'] ?? false;
        $poll->duration               = $validated['duration'] ?? null;

        // Si on lance directement (pas brouillon) → on note started_at et ends_at
        if (! $poll->is_draft) {
            $poll->started_at = now();
            if ($poll->duration) {
                $poll->ends_at = now()->addSeconds($poll->duration);
            }
        }

        $poll->save();

        foreach ($validated['options'] as $label) {
            $option          = new PollOption();
            $option->poll_id = $poll->id;
            $option->label   = $label;
            $option->save();
        }

        return response()->json($poll->load('options'), 201);
    }

    /**
     * PUT /api/v1/polls/{id}
     * Modifie un sondage existant.
     */
    public function update(Request $request, int $id)
    {
        $poll = Poll::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        $validated = $request->validate([
            'question'               => 'required|string|max:500',
            'title'                  => 'nullable|string|max:255',
            'options'                => 'required|array|min:2',
            'options.*'              => 'required|string|max:255',
            'is_draft'               => 'boolean',
            'allow_multiple_choices' => 'boolean',
            'allow_vote_change'      => 'boolean',
            'results_public'         => 'boolean',
            'duration'               => 'nullable|integer|min:1',
        ]);

        $wasDraft = $poll->is_draft;

        $poll->question               = $validated['question'];
        $poll->title                  = $validated['title'] ?? null;
        $poll->is_draft               = $validated['is_draft'] ?? $poll->is_draft;
        $poll->allow_multiple_choices = $validated['allow_multiple_choices'] ?? $poll->allow_multiple_choices;
        $poll->allow_vote_change      = $validated['allow_vote_change'] ?? $poll->allow_vote_change;
        $poll->results_public         = $validated['results_public'] ?? $poll->results_public;
        $poll->duration               = $validated['duration'] ?? null;

        // Passage de brouillon à actif → on enregistre le lancement
        if ($wasDraft && ! $poll->is_draft && ! $poll->started_at) {
            $poll->started_at = now();
            if ($poll->duration) {
                $poll->ends_at = now()->addSeconds($poll->duration);
            }
        }

        $poll->save();

        // On ne modifie les options que s'il n'y a pas encore de votes
        if (! $poll->votes()->exists()) {
            $poll->options()->delete();
            foreach ($validated['options'] as $label) {
                $option          = new PollOption();
                $option->poll_id = $poll->id;
                $option->label   = $label;
                $option->save();
            }
        }

        return response()->json($poll->load('options'));
    }

    /**
     * DELETE /api/v1/polls/{id}
     * Supprime un sondage (repris de l'existant, inchangé).
     */
    public function remove(Request $request, int $id)
    {
        $poll = Poll::where('id', $id)->where('user_id', $request->user()->id)->first();

        if (! $poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        $poll->delete();

        return response()->json(['message' => 'Supprimé.']);
    }

    /**
     * POST /api/v1/polls/{token}/vote
     * Enregistre un vote.
     *
     * Vérifications dans l'ordre :
     * 1. Sondage existe
     * 2. Sondage actif (pas brouillon, pas expiré)
     * 3. Options valides (appartiennent à ce sondage)
     * 4. Unicité du vote (choix unique OU changement autorisé)
     */
    public function vote(Request $request, string $token)
    {
        $poll = Poll::with('options')->where('secret_token', $token)->first();

        if (! $poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }
        if ($poll->is_draft) {
            return response()->json(['message' => 'Ce sondage n\'est pas ouvert.'], 403);
        }
        if ($poll->ends_at && now()->isAfter($poll->ends_at)) {
            return response()->json(['message' => 'Ce sondage est terminé.'], 403);
        }

        $validated = $request->validate([
            'option_ids'   => 'required|array|min:1',
            'option_ids.*' => 'integer|exists:poll_options,id',
        ]);

        $optionIds = $validated['option_ids'];
        $validIds  = $poll->options->pluck('id')->toArray();
        $userId    = $request->user()->id;

        foreach ($optionIds as $optId) {
            if (! in_array($optId, $validIds)) {
                return response()->json(['message' => 'Option invalide.'], 422);
            }
        }

        if (! $poll->allow_multiple_choices && count($optionIds) > 1) {
            return response()->json(['message' => 'Un seul choix autorisé.'], 422);
        }

        $hasVoted = PollVote::where('poll_id', $poll->id)->where('user_id', $userId)->exists();

        if ($hasVoted) {
            if (! $poll->allow_vote_change) {
                return response()->json(['message' => 'Vous avez déjà voté.'], 422);
            }
            PollVote::where('poll_id', $poll->id)->where('user_id', $userId)->delete();
        }

        foreach ($optionIds as $optionId) {
            $vote                 = new PollVote();
            $vote->poll_id        = $poll->id;
            $vote->user_id        = $userId;
            $vote->poll_option_id = $optionId;
            $vote->save();
        }

        $poll->load(['options' => fn($q) => $q->withCount('votes')]);

        return response()->json($poll);
    }
}
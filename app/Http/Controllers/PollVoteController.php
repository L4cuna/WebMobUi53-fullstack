<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Contrôleur pour la page de vote (route web, pas API).
 */
class PollVoteController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        return view('polls.vote', ['token' => $token]);
    }
}
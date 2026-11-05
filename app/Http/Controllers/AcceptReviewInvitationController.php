<?php

namespace App\Http\Controllers;

use App\Domain\Reviews\PlsReviewInvitation;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AcceptReviewInvitationController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $invitation = PlsReviewInvitation::where('token', $token)
            ->firstOrFail();

        $user = $request->user();

        if ($user === null) {
            session(['url.intended' => route('pls.invitations.accept', ['token' => $token])]);

            return redirect()->route('login');
        }

        if (mb_strtolower($user->email) !== mb_strtolower($invitation->email)) {
            abort(403, __('This invitation was sent to :email. Please log in with that account.', [
                'email' => $invitation->email,
            ]));
        }

        $invitation->acceptFor($user);

        return redirect()->route('pls.reviews.workflow', $invitation->review)
            ->with('toast', Toast::success(
                __('Invitation accepted'),
                __('Invitation accepted. Welcome to the review.'),
            ));
    }
}

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
            ->whereNull('accepted_at')
            ->firstOrFail();

        $user = $request->user();

        if ($user === null) {
            session(['pending_invitation_token' => $token]);

            return redirect()->route('login');
        }

        if (mb_strtolower($user->email) !== mb_strtolower($invitation->email)) {
            abort(403, __('This invitation was sent to :email. Please log in with that account.', [
                'email' => $invitation->email,
            ]));
        }

        if ($invitation->review->memberships()->where('user_id', $user->id)->exists()) {
            return redirect()->route('pls.reviews.workflow', $invitation->review)
                ->with('toast', Toast::warning(
                    __('Access already granted'),
                    __('You already have access to this review.'),
                ));
        }

        $invitation->acceptFor($user);

        return redirect()->route('pls.reviews.workflow', $invitation->review)
            ->with('toast', Toast::success(
                __('Invitation accepted'),
                __('Invitation accepted. Welcome to the review.'),
            ));
    }
}

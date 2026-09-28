<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Invitations\AcceptInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AcceptInvitationRequest;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController extends Controller
{
    /**
     * Show the accept-invitation form for a raw token.
     */
    public function show(string $token): Response
    {
        $invitation = Invitation::where('token_hash', hash('sha256', $token))->first();
        $pending = $invitation !== null && $invitation->isPending();

        return Inertia::render('auth/AcceptInvitation', [
            'token' => $token,
            'valid' => $pending,
            'email' => $pending ? $invitation->email : null,
        ]);
    }

    /**
     * Accept the invitation, creating the tenant user.
     */
    public function store(AcceptInvitationRequest $request, string $token, AcceptInvitation $accept): RedirectResponse
    {
        $accept->handle(
            $token,
            $request->string('name')->toString(),
            $request->string('password')->toString(),
        );

        return to_route('login')->with('status', __('Invitation accepted. Please sign in.'));
    }
}

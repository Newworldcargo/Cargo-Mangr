<?php

namespace Modules\Users\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Modules\Users\Services\ImpersonationService;

class ImpersonationController extends Controller
{
    public function __construct(
        private readonly ImpersonationService $impersonation,
        private readonly AuditLogService $auditLogs,
    ) {
        $this->middleware('auth');
    }

    public function start(Request $request, $id): RedirectResponse
    {
        $actor = $request->user();
        $target = User::findOrFail($id);

        abort_unless($this->impersonation->canImpersonate($actor, $target), 403);

        Session::put(ImpersonationService::SESSION_KEY, $actor->id);
        $this->auditLogs->createLog(
            'impersonation_started',
            $target,
            User::class,
            [],
            ['impersonator_id' => $actor->id, 'impersonated_user_id' => $target->id],
            'Started an administrative impersonation session.'
        );

        Auth::login($target);
        $request->session()->regenerate();

        return redirect()->route('admin.index')->with('message_alert', 'You are now logged in as '.$target->name.'.');
    }

    public function leave(Request $request): RedirectResponse
    {
        $impersonator = $this->impersonation->impersonator();
        $target = $request->user();

        abort_unless($impersonator && $target, 403);

        $this->auditLogs->createLog(
            'impersonation_ended',
            $target,
            User::class,
            [],
            ['impersonator_id' => $impersonator->id, 'impersonated_user_id' => $target->id],
            'Ended an administrative impersonation session.'
        );

        Auth::login($impersonator);
        Session::forget(ImpersonationService::SESSION_KEY);
        $request->session()->regenerate();

        return redirect()->route('users.show', ['id' => $impersonator->id])
            ->with('message_alert', 'Returned to your administrator account.');
    }
}

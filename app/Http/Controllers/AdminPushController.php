<?php

namespace App\Http\Controllers;

use App\Services\Push\AdminPushService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPushController extends Controller
{
    public function __construct(
        private AdminPushService $pushes,
    ) {}

    public function index(): View
    {
        return view('admin.push.index', [
            'history' => $this->pushes->history(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:500'],
            'mode' => ['required', 'in:all,self'],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Potwierdź wysyłkę.',
        ]);

        $result = $this->pushes->send(
            $request->user(),
            $validated['title'],
            $validated['body'],
            $validated['mode'] === 'self',
        );

        $label = $validated['mode'] === 'self' ? 'próbę do siebie' : 'broadcast';

        return redirect()
            ->route('admin.push.index')
            ->with('success', "Wysłano {$label} (odbiorców: {$result['recipientCount']}).");
    }
}

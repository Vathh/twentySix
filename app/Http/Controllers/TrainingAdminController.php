<?php

namespace App\Http\Controllers;

use App\Domain\Training\TrainingChallengeCatalog;
use App\Domain\Training\TrainingKind;
use App\Http\Requests\Admin\TrainingDefinitionRequest;
use App\Models\Training\TrainingDefinition;
use App\Models\Users\User;
use App\Services\Training\TrainingDefinitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrainingAdminController extends Controller
{
    public function __construct(
        private TrainingDefinitionService $definitions,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.training.index', [
            'counts' => $this->definitions->counts($this->admin($request)),
        ]);
    }

    public function list(Request $request, string $kind): View
    {
        $trainingKind = $this->kind($kind);
        if ($trainingKind === TrainingKind::Exercise) {
            return view('admin.training.list', [
                'kind' => $trainingKind,
                'active' => collect(),
                'inactive' => collect(),
                'catalog' => true,
            ]);
        }

        $catalog = $this->definitions->catalog($this->admin($request), $trainingKind);

        return view('admin.training.list', [
            'kind' => $trainingKind,
            'active' => $catalog['active'],
            'inactive' => $catalog['inactive'],
            'catalog' => false,
        ]);
    }

    public function create(Request $request, string $kind): View
    {
        $trainingKind = $this->kind($kind);
        if ($trainingKind === TrainingKind::Exercise) {
            abort(404);
        }

        return view('admin.training.form', $this->formData($trainingKind, null));
    }

    public function store(TrainingDefinitionRequest $request, string $kind): RedirectResponse
    {
        $trainingKind = $this->kind($kind);
        if ($trainingKind === TrainingKind::Exercise) {
            abort(404);
        }

        $definition = $this->definitions->create(
            $this->admin($request),
            $request->definitionPayload($trainingKind),
            $request->file('image'),
        );

        return redirect()
            ->route('admin.training.show', $definition)
            ->with('success', 'Zapisano. '.$trainingKind->activeSentence(false));
    }

    public function show(Request $request, TrainingDefinition $definition): View
    {
        return view('admin.training.show', [
            'definition' => $this->definitions->present($this->admin($request), (int) $definition->id),
        ]);
    }

    public function edit(Request $request, TrainingDefinition $definition): View
    {
        $draft = $this->definitions->draft($this->admin($request), (int) $definition->id);

        return view('admin.training.form', $this->formData($draft->kind, $draft));
    }

    public function update(TrainingDefinitionRequest $request, TrainingDefinition $definition): RedirectResponse
    {
        $updated = $this->definitions->updateDraft(
            $this->admin($request),
            (int) $definition->id,
            $request->definitionPayload($definition->kind),
            $request->file('image'),
            $request->removesImage(),
        );

        return redirect()
            ->route('admin.training.show', $updated)
            ->with('success', 'Zapisano zmiany.');
    }

    public function activate(Request $request, TrainingDefinition $definition): RedirectResponse
    {
        if ($definition->kind === TrainingKind::Event) {
            $this->validatePassword($request);
        }

        $updated = $this->definitions->activate($this->admin($request), (int) $definition->id);

        return redirect()
            ->route('admin.training.show', $updated)
            ->with('success', $updated->kind->activeSentence(true));
    }

    public function deactivate(Request $request, TrainingDefinition $definition): RedirectResponse
    {
        if ($definition->kind === TrainingKind::Event) {
            $this->validatePassword($request);
        }

        $updated = $this->definitions->deactivate($this->admin($request), (int) $definition->id);

        return redirect()
            ->route('admin.training.show', $updated)
            ->with('success', $updated->kind->activeSentence(false));
    }

    public function cancel(Request $request, TrainingDefinition $definition): RedirectResponse
    {
        $this->validatePassword($request);
        $this->definitions->cancel($this->admin($request), (int) $definition->id);

        return redirect()
            ->route('admin.training.list', $definition->kind->slug())
            ->with('success', $definition->kind->cancelledSentence());
    }

    public function remove(Request $request, TrainingDefinition $definition): RedirectResponse
    {
        $this->validatePassword($request);
        $kind = $definition->kind;
        $this->definitions->remove($this->admin($request), (int) $definition->id);

        return redirect()
            ->route('admin.training.list', $kind->slug())
            ->with('success', $kind->removedSentence());
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(TrainingKind $kind, ?TrainingDefinition $definition): array
    {
        return [
            'kind' => $kind,
            'definition' => $definition,
            'challengeMechanics' => TrainingChallengeCatalog::all(),
        ];
    }

    private function kind(string $slug): TrainingKind
    {
        $kind = TrainingKind::tryFromSlug($slug);
        if ($kind === null) {
            abort(404);
        }

        return $kind;
    }

    private function admin(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }

    private function validatePassword(Request $request): void
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'acknowledge' => ['accepted'],
        ], [], [
            'current_password' => 'hasło',
            'acknowledge' => 'potwierdzenie',
        ]);
    }
}

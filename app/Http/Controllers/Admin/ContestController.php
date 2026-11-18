<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ContestController extends Controller
{
    public function index()
    {
        $contests = Contest::query()
            ->withCount('participations', 'contestProblems')
            ->orderByRaw("FIELD(status, 'running', 'approved', 'draft', 'ended')")
            ->orderByDesc('start_time')
            ->paginate(20);

        return view('admin.contests.index', compact('contests'));
    }

    public function create()
    {
        // Creation of drafts is always allowed.
        return view('admin.contests.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateContest($request, null);

        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['created_by'] = Auth::id();
        $data['status'] = Contest::STATUS_DRAFT;

        $contest = Contest::create($data);

        return redirect()
            ->route('admin.contests.show', $contest)
            ->with('status', 'Draft contest created.');
    }

    public function show(Contest $contest)
    {
        $contest->load([
            'contestProblems.problem',
            'participations.team.members',
            'creator',
            'approver',
        ]);

        $stats = match ($contest->status) {
            Contest::STATUS_RUNNING => [
                'submissions_total'   => $contest->submissions()->count(),
                'submissions_pending' => $contest->submissions()->pending()->count(),
                'teams_count'         => $contest->participations()->count(),
                'questions_open'      => $contest->questions()->unanswered()->count(),
            ],
            Contest::STATUS_ENDED => [
                'submissions_total' => $contest->submissions()->count(),
                'teams_count'       => $contest->participations()->count(),
            ],
            default => [],
        };

        return view("admin.contests.show.{$contest->status}", compact('contest', 'stats'));
    }

    public function edit(Contest $contest)
    {
        abort_unless($contest->isDraft(), 403, 'Only draft contests can be edited.');

        return view('admin.contests.edit', compact('contest'));
    }

    public function update(Request $request, Contest $contest)
    {
        abort_unless($contest->isDraft(), 403, 'Only draft contests can be edited.');

        $data = $this->validateContest($request, $contest);

        // start_time is frozen once it has been reached.
        if ($contest->startTimeLocked() && $data['start_time'] !== $contest->start_time?->format('Y-m-d\TH:i')) {
            return back()
                ->withInput()
                ->withErrors(['start_time' => 'Start time cannot be changed after it has been reached.']);
        }

        if (empty($data['slug']) && $data['name'] !== $contest->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $contest->id);
        }

        $contest->update($data);

        return redirect()
            ->route('admin.contests.show', $contest)
            ->with('status', 'Contest updated.');
    }

    public function destroy(Contest $contest)
    {
        abort_unless($contest->isDraft(), 403, 'Only draft contests can be deleted.');

        $contest->delete();

        return redirect()
            ->route('admin.contests.index')
            ->with('status', 'Draft contest deleted.');
    }

    // ----------------------------------------------------------------

    private function validateContest(Request $request, ?Contest $existing): array
    {
        $rules = [
            'name'           => ['required', 'string', 'max:255'],
            'start_time'     => ['required', 'date'],
            'end_time'       => ['required', 'date', 'after:start_time'],
            'freeze_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'is_public'      => ['sometimes', 'boolean'],
        ];

        if ($existing === null) {
            $rules['name'][] = 'unique:contests,name';
        } else {
            $rules['name'][] = Rule::unique('contests', 'name')->ignore($existing->id);
        }

        $data = $request->validate($rules);

        $data['freeze_minutes'] = (int) ($data['freeze_minutes'] ?? 0);
        $data['is_public']      = $request->boolean('is_public', true);

        return $data;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'contest';
        $slug = $base;
        $i    = 1;

        while (Contest::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}

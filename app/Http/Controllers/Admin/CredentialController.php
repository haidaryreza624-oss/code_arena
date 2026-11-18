<?php

namespace App\Http\Controllers\Admin;

use App\Actions\GenerateCredentials;
use App\Http\Controllers\Controller;
use App\Models\Contest;
use Barryvdh\DomPDF\Facade\Pdf;

final class CredentialController extends Controller
{
    public function index(Contest $contest)
    {
        abort_unless($contest->isApproved(), 403, 'Credentials are only available in approved state.');

        $participations = $contest->participations()
            ->with('team.members')
            ->orderBy('username')
            ->get();

        return view('admin.credentials.index', compact('contest', 'participations'));
    }

    /**
     * Generate missing credentials, stream the PDF immediately.
     * Plaintext passwords live only in this request.
     */
    public function generate(Contest $contest, GenerateCredentials $action)
    {
        abort_unless($contest->isApproved(), 403);

        $plainByParticipation = $action->execute($contest);

        if (empty($plainByParticipation)) {
            return back()->withErrors([
                'generate' => 'All teams already have credentials. Use "regenerate all" to invalidate existing ones.',
            ]);
        }

        $participations = $contest->participations()
            ->with('team.members')
            ->whereIn('id', array_keys($plainByParticipation))
            ->orderBy('username')
            ->get();

        $pdf = Pdf::loadView('pdf.credentials', [
            'contest'        => $contest,
            'participations' => $participations,
            'plainByParticipation' => $plainByParticipation,
        ])->setPaper('a4');

        return $pdf->download("credentials-{$contest->slug}.pdf");
    }

    /**
     * Regenerate ALL credentials (invalidates existing). Streams PDF.
     */
    public function regenerate(Contest $contest, GenerateCredentials $action)
    {
        abort_unless($contest->isApproved(), 403);

        $plainByParticipation = $action->execute($contest, force: true);

        $participations = $contest->participations()
            ->with('team.members')
            ->orderBy('username')
            ->get();

        $pdf = Pdf::loadView('pdf.credentials', [
            'contest'        => $contest,
            'participations' => $participations,
            'plainByParticipation' => $plainByParticipation,
        ])->setPaper('a4');

        return $pdf->download("credentials-{$contest->slug}.pdf");
    }
}

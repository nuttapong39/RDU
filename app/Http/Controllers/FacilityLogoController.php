<?php

namespace App\Http\Controllers;

use App\Services\FacilityProfileService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Serves the hospital's logo from private storage (no `storage:link`); public because the login page shows it. */
class FacilityLogoController extends Controller
{
    public function __invoke(FacilityProfileService $facility): Response
    {
        $path = $facility->logoPath();
        abort_if($path === null, 404);

        $mime = array_search(pathinfo($path, PATHINFO_EXTENSION), FacilityProfileService::LOGO_MIMES, true);

        return Storage::disk('local')->response($path, null, [
            'Content-Type'           => $mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'public, max-age=86400',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}

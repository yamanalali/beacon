<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Support\LandingCaseStudies;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CaseStudyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Landing/CaseStudies', [
            'cases' => LandingCaseStudies::all(),
        ]);
    }

    public function show(string $caseStudy): Response
    {
        if (! LandingCaseStudies::isValid($caseStudy)) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('Landing/CaseStudyShow', [
            'slug' => $caseStudy,
            'cases' => LandingCaseStudies::all(),
        ]);
    }
}

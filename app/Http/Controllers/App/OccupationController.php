<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Backbone\EscoOccupation;
use App\Models\Backbone\EscoSkill;
use App\Services\Backbone\OccupationCatalog;
use App\Services\Backbone\SkillCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// ══════════════════════════════════════════════════════════════════
//  Massar — App\OccupationController (partner Occupations page)
//  Location: app/Http/Controllers/App/OccupationController.php
//  Permission: occupations.view
//
//  GET /app/occupations              index — search and browse in the
//                                            standard chosen in the top
//                                            bar (ENOC · ISCO-08 · ESCO)
//  GET /app/occupations/units/{code} unit  — one occupation (ENOC =
//                                            ISCO-08 unit group) with its
//                                            ESCO jobs and the Egypt
//                                            labour market panel
//  GET /app/occupations/esco/{id}    esco  — one detailed ESCO job in all
//                                            three standards, with the
//                                            figures of its Egyptian
//                                            occupation
//
//  GET /app/occupations/skills/{id}  skill — one ESCO skill (Step 5):
//                                            what it is and which jobs
//                                            need it
//
//  Scope v2 §4 "Shared Backbone Access": every partner reads the same
//  backbone and labour market data. Read-only.
//
//  Same data as the Super Admin screens (OccupationCatalog), with two
//  differences: figures flagged at import are removed on the server
//  (the panel says "under review"), and nothing about imports,
//  editions or data checks is shown.
// ══════════════════════════════════════════════════════════════════

class OccupationController extends Controller
{
    public function __construct(private readonly OccupationCatalog $catalog, private readonly SkillCatalog $skills) {}

    public function index(Request $request): Response
    {
        [$standard, $filters] = $this->catalog->filters($request);
        $loaded = $this->catalog->loaded();

        return Inertia::render('App/Occupations/Index', [
            'standard' => $standard,
            'filters'  => $filters,
            'loaded'   => $loaded,
            'majors'   => $loaded ? $this->catalog->majors() : [],
            'browse'   => $loaded ? $this->catalog->browse($standard, $filters) : null,
        ]);
    }

    public function unit(string $code): Response
    {
        $page = $this->catalog->unitPage($code, forPartners: true);
        abort_if(! $page, 404);

        return Inertia::render('App/Occupations/Unit', $page);
    }

    public function esco(EscoOccupation $escoOccupation): Response
    {
        abort_if(! $escoOccupation->is_active, 404);

        return Inertia::render('App/Occupations/Esco', $this->catalog->escoPage($escoOccupation, forPartners: true));
    }

    public function skill(EscoSkill $escoSkill): Response
    {
        abort_if(! $escoSkill->is_active, 404);

        return Inertia::render('App/Occupations/Skill', $this->skills->skillPage($escoSkill));
    }
}

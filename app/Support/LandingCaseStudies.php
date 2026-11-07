<?php

namespace App\Support;

/**
 * Illustrative marketing case studies (not financing approvals).
 * Content lives in lang catalogs under landing.cases.{slug}.*
 */
final class LandingCaseStudies
{
    public const SLUGS = ['records', 'compliance', 'referral'];

    public static function isValid(string $slug): bool
    {
        return in_array($slug, self::SLUGS, true);
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return self::SLUGS;
    }
}

<?php
declare(strict_types=1);

namespace InfinityBlog\Support;

final class RatingHelper
{
    /**
     * @return array{
     *   tier: string,
     *   tier_label: string,
     *   css_class: string,
     *   formatted_score: string,
     *   count: int,
     *   has_rating: bool,
     *   badge_html: string
     * }
     */
    public static function getRatingInfo(float|int|null $average, int|null $count): array
    {
        $cnt = (int) ($count ?? 0);
        $avg = (float) ($average ?? 0.0);

        if ($cnt <= 0 || $avg <= 0.0) {
            return [
                'tier' => 'none',
                'tier_label' => 'Not rated yet',
                'css_class' => 'rating-badge-none',
                'formatted_score' => '—',
                'count' => 0,
                'has_rating' => false,
                'badge_html' => '<span class="rating-badge rating-badge-none" title="Not rated yet"><span class="rating-star">★</span> <span class="rating-val">Not rated yet</span></span>',
            ];
        }

        // Clamp to 1.0 - 5.0 and round to 1 decimal place
        $rounded = round($avg, 1);
        if ($rounded < 1.0) {
            $rounded = 1.0;
        } elseif ($rounded > 5.0) {
            $rounded = 5.0;
        }

        $formattedScore = number_format($rounded, 1);

        if ($rounded < 2.0) {
            $tier = 'poor';
            $tierLabel = 'Poor';
            $cssClass = 'rating-badge-poor';
        } elseif ($rounded < 3.0) {
            $tier = 'below-average';
            $tierLabel = 'Below average';
            $cssClass = 'rating-badge-below-average';
        } elseif ($rounded < 4.0) {
            $tier = 'average';
            $tierLabel = 'Average';
            $cssClass = 'rating-badge-average';
        } elseif ($rounded < 4.5) {
            $tier = 'good';
            $tierLabel = 'Good';
            $cssClass = 'rating-badge-good';
        } else {
            $tier = 'excellent';
            $tierLabel = 'Excellent';
            $cssClass = 'rating-badge-excellent';
        }

        $countLabel = $cnt === 1 ? '1 score' : "{$cnt} scores";
        $title = "Reader Score: {$formattedScore} out of 5 ({$tierLabel}) based on {$countLabel}";
        $badgeHtml = sprintf(
            '<span class="rating-badge %s" title="%s"><span class="rating-star">★</span> <span class="rating-val">%s</span> <span class="rating-count">(%d)</span></span>',
            $cssClass,
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            $formattedScore,
            $cnt
        );

        return [
            'tier' => $tier,
            'tier_label' => $tierLabel,
            'css_class' => $cssClass,
            'formatted_score' => $formattedScore,
            'count' => $cnt,
            'has_rating' => true,
            'badge_html' => $badgeHtml,
        ];
    }
}

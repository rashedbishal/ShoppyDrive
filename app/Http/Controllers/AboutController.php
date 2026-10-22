<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use Inertia\Inertia;
use Inertia\Response;

class AboutController extends Controller
{
    /**
     * Defaults mirror the page's original hardcoded copy, so the page renders
     * identically until an admin edits it from Admin > Content > About Page.
     */
    public function show(): Response
    {
        $settings = Setting::whereIn('key', $this->keys())->pluck('value', 'key');

        return Inertia::render('static/About', [
            'content' => [
                'hero_eyebrow' => $settings['about_hero_eyebrow'] ?? 'Our Story',
                'hero_title' => $settings['about_hero_title'] ?? "Bangladesh's Smartest",
                'hero_title_highlight' => $settings['about_hero_title_highlight'] ?? 'Shopping Experience',
                'hero_description' => $settings['about_hero_description'] ?? 'ShoppyDrive was built to make premium shopping accessible to every Bangladeshi — with honest prices, genuine products, and world-class delivery.',
                'stats' => $settings['about_stats'] ?? [
                    ['value' => '50,000+', 'label' => 'Happy Customers'],
                    ['value' => '1,000+', 'label' => 'Products Listed'],
                    ['value' => '64', 'label' => 'Districts Covered'],
                    ['value' => '99%', 'label' => 'Satisfaction Rate'],
                ],
                'mission_eyebrow' => $settings['about_mission_eyebrow'] ?? 'Our Mission',
                'mission_title' => $settings['about_mission_title'] ?? 'Empowering Bangladeshis to shop with confidence',
                'mission_body' => $settings['about_mission_body'] ?? '<p>We started ShoppyDrive with a simple vision: create a shopping platform that Bangladeshis can truly trust. No misleading prices, no counterfeit products, no complicated processes.</p><p>From Dhaka to Cox\'s Bazar, we deliver genuine products at fair prices with a shopping experience that feels premium — regardless of what device you\'re using.</p>',
                'mission_features' => $settings['about_mission_features'] ?? [
                    ['icon' => 'package', 'label' => '1,000+ Products'],
                    ['icon' => 'truck', 'label' => 'Nationwide Delivery'],
                    ['icon' => 'users', 'label' => '50K+ Customers'],
                    ['icon' => 'award', 'label' => 'Quality Assured'],
                ],
                'values' => $settings['about_values'] ?? [
                    ['icon' => 'shield-check', 'title' => 'Trust & Transparency', 'description' => 'Honest pricing, verified products, and clear return policies — no hidden surprises.'],
                    ['icon' => 'zap', 'title' => 'Speed & Convenience', 'description' => 'From browsing to doorstep, we make every step fast and frictionless.'],
                    ['icon' => 'heart', 'title' => 'Customer First', 'description' => 'Your satisfaction is our priority. 24/7 support in Bangla and English.'],
                    ['icon' => 'globe', 'title' => 'Made for Bangladesh', 'description' => 'Built for local buyers with BDT pricing, bKash, Nagad, and nationwide delivery.'],
                ],
                'team' => $settings['about_team'] ?? [
                    ['name' => 'Rahima Islam', 'role' => 'CEO & Founder', 'initials' => 'RI'],
                    ['name' => 'Karim Hassan', 'role' => 'Head of Operations', 'initials' => 'KH'],
                    ['name' => 'Nadia Chowdhury', 'role' => 'Head of Product', 'initials' => 'NC'],
                    ['name' => 'Arif Rahman', 'role' => 'Tech Lead', 'initials' => 'AR'],
                ],
                'cta_title' => $settings['about_cta_title'] ?? 'Ready to start shopping?',
                'cta_description' => $settings['about_cta_description'] ?? "Join thousands of happy customers and discover why ShoppyDrive is Bangladesh's most trusted online store.",
            ],
        ]);
    }

    /** @return array<int, string> */
    private function keys(): array
    {
        return [
            'about_hero_eyebrow', 'about_hero_title', 'about_hero_title_highlight', 'about_hero_description',
            'about_stats', 'about_mission_eyebrow', 'about_mission_title', 'about_mission_body',
            'about_mission_features', 'about_values', 'about_team', 'about_cta_title', 'about_cta_description',
        ];
    }
}

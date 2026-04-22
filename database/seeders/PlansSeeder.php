<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;
use App\Models\User;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        if (!$admin) {
            return;
        }

        $plans = [

            // ================= INDIVIDUAL PLANS =================

            [
                'name' => 'Free Trial',
                'plan_type' => 'individual',
                'user_id' => $admin->id,
                'billing_cycle' => 'days',
                'duration' => 7,
                'price' => 0,
                'member_limit' => null,
                'projection_limit' => 1,
                'status' => true,
                'features' => [
                    'Upload 1 body photo',
                    '1 AI future projection (1-year horizon)',
                    'Personal wellness dashboard',
                    'Basic body stats & trends',
                    'AI improvement suggestions',
                    'Health Indicators',
                    'Recommended coaches & clinics',
                    'Achievement badges & Progress reports'
                ],
            ],

            [
                'name' => 'Plus',
                'plan_type' => 'individual',
                'user_id' => $admin->id,
                'billing_cycle' => 'monthly',
                'duration' => null,
                'price' => 29,
                'member_limit' => null,
                'projection_limit' => 2,
                'status' => true,
                'features' => [
                    'Up to 2 AI body projections',
                    'AI-generated health suggestions (Limited)',
                    'Recommended Business',
                    'Achievement badges',
                    'Progress tracking',
                    'X% Improved vs baseline',
                    'Recalculated after every photo',
                    'Standard Support Services'
                ],
            ],

            [
                'name' => 'Premium',
                'plan_type' => 'individual',
                'user_id' => $admin->id,
                'billing_cycle' => 'monthly',
                'duration' => null,
                'price' => 35,
                'member_limit' => null,
                'projection_limit' => 4,
                'status' => true,
                'features' => [
                    'EVERYTHING IN PLUS',
                    'Up to 4 AI projections',
                    'External fitness tracker sync',
                    'Downloadable progress reports',
                    'Historical trends vs AI projections',
                    'Priority Email support',
                    'Full access AI-generated health suggestions',
                    'Future Health Insights (5 year projection)'
                ],
            ],

            // ================= PROFESSIONAL PLANS =================

            [
                'name' => 'Tier 1 Professional',
                'plan_type' => 'professional',
                'user_id' => $admin->id,
                'billing_cycle' => 'half_annual',
                'duration' => null,
                'price' => 250,
                'member_limit' => 8,
                'projection_limit' => 16,
                'status' => true,
                'features' => [
                    'Up to 8 client accounts',
                    '16 Projections/month*',
                    'Client progress tracking',
                    'White-label reports',
                    'Email support',
                    'Basic analytics dashboard',
                    'Core body visualization & health insights',
                    'Limited customization'
                ],
            ],

            [
                'name' => 'Tier 2 Professional',
                'plan_type' => 'professional',
                'user_id' => $admin->id,
                'billing_cycle' => 'half_annual',
                'duration' => null,
                'price' => 750,
                'member_limit' => 25,
                'projection_limit' => 50,
                'status' => true,
                'features' => [
                    'Up to 25 client accounts',
                    '50 Projections/month*',
                    'Everything in Tier 1',
                    'Advanced analytics dashboard',
                    'API access',
                    'Priority email support',
                    'Custom branding',
                    'Team collaboration (3 seats)',
                    'Dedicated account manager'
                ],
            ],

            [
                'name' => 'Tier 3 Professional',
                'plan_type' => 'professional',
                'user_id' => $admin->id,
                'billing_cycle' => 'half_annual',
                'duration' => null,
                'price' => 3500,
                'member_limit' => 150,
                'projection_limit' => 600,
                'status' => true,
                'features' => [
                    'Up to 150 client accounts',
                    'Everything in Tier 2',
                    '600 Projections/month*',
                    'Priority phone & email support',
                    'Team collaboration (10 seats)',
                    'Quarterly business reviews',
                    'Dedicated account manager'
                ],
            ],

            [
                'name' => 'Enterprise',
                'plan_type' => 'professional',
                'user_id' => $admin->id,
                'billing_cycle' => 'custom',
                'duration' => null,
                'price' => 0,
                'member_limit' => 0,
                'projection_limit' => 0,
                'status' => true,
                'features' => [
                    'Unlimited client accounts',
                    'Everything in Tier 3',
                    'Dedicated account manager',
                    'Custom SLA agreements',
                    'On-premise deployment option',
                    'Unlimited team seats',
                    'White-glove onboarding',
                    'Custom feature development',
                    'Quarterly business reviews'
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                [
                    'name' => $plan['name'],
                    'plan_type' => $plan['plan_type']
                ],
                $plan
            );
        }
    }
}

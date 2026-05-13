<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TermsAndCondition;

class TermsAndConditionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TermsAndCondition::updateOrCreate(

            ['id' => 1],

            [
                'content' => [

                    'title' => 'BioVue Digital Wellness – Terms of Service',

                    'last_updated' => 'May 11, 2026',

                    'intro' => 'Welcome to BioVue Digital Wellness (“BioVue,” “we,” “our,” or “us”). These Terms govern your access to and use of the Services.',

                    'agreement' => 'By using the Services, you agree to these Terms. If you do not agree, please stop using the Services.',

                    'sections' => [

                        [
                            'title' => '1. About BioVue',
                            'description' => 'BioVue is a wellness and motivation platform for habit building and lifestyle improvement. It is not a medical provider.'
                        ],

                        [
                            'title' => '2. Eligibility',
                            'description' => 'You must be at least 18 years old to use the Services.'
                        ],

                        [
                            'title' => '3. Health & AI Disclaimer',
                            'description' => 'AI-generated outputs are informational only and may not be accurate.',
                            'points' => [
                                'Not medical advice',
                                'No guaranteed results',
                                'Not a replacement for professionals'
                            ]
                        ],

                        [
                            'title' => '4. User Accounts',
                            'description' => 'Users must provide accurate information and maintain account security.'
                        ],

                        [
                            'title' => '5. Trainer Connections',
                            'description' => 'Trainers are independent and not employees of BioVue.'
                        ],

                        [
                            'title' => '6. Subscriptions',
                            'description' => 'Subscriptions renew automatically unless canceled.'
                        ],

                        [
                            'title' => '7. Acceptable Use',
                            'points' => [
                                'No misuse of services',
                                'No illegal activity',
                                'No harassment or abuse'
                            ]
                        ],

                        [
                            'title' => '8. Intellectual Property',
                            'description' => 'All platform content belongs to BioVue or its licensors.'
                        ],

                        [
                            'title' => '9. Service Availability',
                            'description' => 'Services may be interrupted or modified at any time.'
                        ],

                        [
                            'title' => '10. Limitation of Liability',
                            'description' => 'BioVue is not responsible for indirect or consequential damages.'
                        ],

                        [
                            'title' => '11. Termination',
                            'description' => 'Accounts may be suspended or terminated for violations.'
                        ],

                        [
                            'title' => '12. Governing Law',
                            'description' => 'These Terms are governed by the laws of Texas.'
                        ],

                        [
                            'title' => '13. Changes',
                            'description' => 'Terms may be updated at any time.'
                        ],

                        [
                            'title' => '14. Contact',
                            'description' => 'info@biovuedigitalwellness.com'
                        ],

                    ],

                ],

                'is_active' => true,
            ]
        );
    }
}
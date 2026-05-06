<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PrivacyPolicy;

class PrivacyPolicySeeder extends Seeder
{
    public function run(): void
    {
        $policy = [
            'title' => 'BioVue Privacy Policy',

            'content' => [
                [
                    'heading' => 'Information We Collect',
                    'content' => 'We collect name, email, account details, wellness inputs, images, and usage data automatically and manually provided by users.'
                ],
                [
                    'heading' => 'How We Use Your Information',
                    'content' => 'We use data to provide services, personalize experience, improve performance, send notifications, and support requests.'
                ],
                [
                    'heading' => 'Wellness Data',
                    'content' => 'Wellness data is used only for BioVue experience and is not sold or used for advertising. It is not medical data.'
                ],
                [
                    'heading' => 'User Images & Content Usage',
                    'content' => 'User uploaded images remain their property. We do not use or share them without permission except for service functionality.'
                ],
                [
                    'heading' => 'Sharing of Information',
                    'content' => 'We only share data with service providers, legal requirements, or security purposes. We do not sell personal data.'
                ],
                [
                    'heading' => 'Data Security',
                    'content' => 'We use technical and organizational measures to protect data, but no system is fully secure.'
                ],
                [
                    'heading' => 'Data Retention',
                    'content' => 'We keep data only as long as needed for services and legal obligations. Users can request deletion anytime.'
                ],
                [
                    'heading' => 'Your Rights',
                    'content' => 'You can access, update, delete data or opt out of communications by contacting support.'
                ],
                [
                    'heading' => 'Children’s Privacy',
                    'content' => 'BioVue is not intended for users under 18 years old.'
                ],
                [
                    'heading' => 'Contact',
                    'content' => 'For privacy questions contact: BioVueSupport@gmail.com'
                ],
            ],

            'is_active' => true,
        ];

        PrivacyPolicy::updateOrCreate(
            ['id' => 1],
            $policy
        );
    }
}
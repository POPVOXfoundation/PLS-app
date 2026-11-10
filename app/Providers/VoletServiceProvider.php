<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Mydnic\Volet\Features\FeatureManager;
use Mydnic\Volet\Features\FeedbackMessages;

class VoletServiceProvider extends ServiceProvider
{
    public function boot(FeatureManager $volet): void
    {
        // Register and configure the Feedback Messages feature
        $this->registerFeedbackMessagesFeature($volet);

        // Example of registering a custom feature
        // $volet->register(new YourCustomFeature());
    }

    private function registerFeedbackMessagesFeature(FeatureManager $volet): void
    {
        $volet->register(
            (new FeedbackMessages)
                // Configure feature display
                ->setLabel('Send us feedback')
                ->setIcon('/images/feedback/chat.svg')

                // Add feedback categories
                ->addCategory(
                    slug: 'bug',
                    name: 'Bug Report',
                    icon: '/images/feedback/bug.svg'
                )
                ->addCategory(
                    slug: 'improvement',
                    name: 'Improvement',
                    icon: '/images/feedback/improvement.svg'
                )
                ->addCategory(
                    slug: 'general',
                    name: 'General Feedback',
                    icon: '/images/feedback/general.svg'
                )
        );
    }
}

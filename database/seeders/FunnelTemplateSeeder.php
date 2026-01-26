<?php

namespace Database\Seeders;

use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelStepContent;
use App\Models\FunnelTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FunnelTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createTemplates();
        $this->createSampleFunnels();
    }

    /**
     * Create funnel templates.
     */
    protected function createTemplates(): void
    {
        $templates = [
            [
                'name' => 'Simple Sales Funnel',
                'description' => 'A straightforward sales funnel with landing page, checkout, and thank you page. Perfect for selling single products.',
                'category' => 'sales',
                'is_premium' => false,
                'is_active' => true,
                'template_data' => [
                    'steps' => [
                        ['type' => 'landing', 'name' => 'Sales Page'],
                        ['type' => 'checkout', 'name' => 'Checkout'],
                        ['type' => 'thankyou', 'name' => 'Thank You'],
                    ],
                ],
            ],
            [
                'name' => 'Product Launch Funnel',
                'description' => 'Complete product launch funnel with upsells, downsells, and order bumps. Maximize revenue per customer.',
                'category' => 'sales',
                'is_premium' => true,
                'is_active' => true,
                'template_data' => [
                    'steps' => [
                        ['type' => 'landing', 'name' => 'Sales Page'],
                        ['type' => 'checkout', 'name' => 'Checkout'],
                        ['type' => 'upsell', 'name' => 'Upsell Offer'],
                        ['type' => 'downsell', 'name' => 'Downsell Offer'],
                        ['type' => 'thankyou', 'name' => 'Thank You'],
                    ],
                ],
            ],
            [
                'name' => 'Lead Magnet Funnel',
                'description' => 'Capture leads with a valuable free resource. Perfect for building your email list.',
                'category' => 'lead',
                'is_premium' => false,
                'is_active' => true,
                'template_data' => [
                    'steps' => [
                        ['type' => 'optin', 'name' => 'Opt-in Page'],
                        ['type' => 'thankyou', 'name' => 'Thank You'],
                    ],
                ],
            ],
            [
                'name' => 'Lead to Sales Funnel',
                'description' => 'Start with lead capture, then convert leads to customers with an irresistible offer.',
                'category' => 'lead',
                'is_premium' => true,
                'is_active' => true,
                'template_data' => [
                    'steps' => [
                        ['type' => 'optin', 'name' => 'Opt-in Page'],
                        ['type' => 'landing', 'name' => 'Sales Page'],
                        ['type' => 'checkout', 'name' => 'Checkout'],
                        ['type' => 'thankyou', 'name' => 'Thank You'],
                    ],
                ],
            ],
            [
                'name' => 'Webinar Registration',
                'description' => 'Registration funnel for live or automated webinars. Includes reminder pages.',
                'category' => 'webinar',
                'is_premium' => false,
                'is_active' => true,
                'template_data' => [
                    'steps' => [
                        ['type' => 'optin', 'name' => 'Registration Page'],
                        ['type' => 'thankyou', 'name' => 'Confirmation'],
                    ],
                ],
            ],
            [
                'name' => 'Webinar Sales Funnel',
                'description' => 'Complete webinar funnel with registration, replay, and sales pages.',
                'category' => 'webinar',
                'is_premium' => true,
                'is_active' => true,
                'template_data' => [
                    'steps' => [
                        ['type' => 'optin', 'name' => 'Registration Page'],
                        ['type' => 'landing', 'name' => 'Webinar Room'],
                        ['type' => 'landing', 'name' => 'Replay Page'],
                        ['type' => 'checkout', 'name' => 'Checkout'],
                        ['type' => 'thankyou', 'name' => 'Thank You'],
                    ],
                ],
            ],
            [
                'name' => 'Membership Signup',
                'description' => 'Simple course signup funnel. Perfect for course or community signups.',
                'category' => 'course',
                'is_premium' => false,
                'is_active' => true,
                'template_data' => [
                    'steps' => [
                        ['type' => 'landing', 'name' => 'Membership Sales Page'],
                        ['type' => 'checkout', 'name' => 'Join Now'],
                        ['type' => 'thankyou', 'name' => 'Welcome'],
                    ],
                ],
            ],
            [
                'name' => 'Course Launch Funnel',
                'description' => 'Full course launch funnel with free training, application, and enrollment pages.',
                'category' => 'course',
                'is_premium' => true,
                'is_active' => true,
                'template_data' => [
                    'steps' => [
                        ['type' => 'optin', 'name' => 'Free Training Signup'],
                        ['type' => 'landing', 'name' => 'Training Video'],
                        ['type' => 'landing', 'name' => 'Course Sales Page'],
                        ['type' => 'checkout', 'name' => 'Enrollment'],
                        ['type' => 'upsell', 'name' => 'VIP Upgrade'],
                        ['type' => 'thankyou', 'name' => 'Welcome & Next Steps'],
                    ],
                ],
            ],

            // ========================================
            // MALAY CHECKOUT & THANK YOU TEMPLATES
            // Bold & Persuasive style for Books/Courses
            // ========================================

            // Checkout Template 1: Simple
            [
                'name' => 'Checkout Ringkas (BM)',
                'description' => 'Checkout ringkas dan pantas. Sesuai untuk jualan produk mudah dengan satu order bump.',
                'category' => 'checkout',
                'is_premium' => false,
                'is_active' => true,
                'template_data' => [
                    'steps' => [
                        ['type' => 'checkout', 'name' => 'Checkout', 'variant' => 'checkout-ringkas'],
                        ['type' => 'thankyou', 'name' => 'Terima Kasih', 'variant' => 'thankyou-ringkas'],
                    ],
                ],
            ],

            // Checkout Template 2: Persuasive
            [
                'name' => 'Checkout Persuasif (BM)',
                'description' => 'Checkout dengan elemen persuasif - countdown timer, testimoni, dan jaminan. Untuk kursus dan ebook.',
                'category' => 'checkout',
                'is_premium' => false,
                'is_active' => true,
                'template_data' => [
                    'steps' => [
                        ['type' => 'checkout', 'name' => 'Checkout', 'variant' => 'checkout-persuasif'],
                        ['type' => 'thankyou', 'name' => 'Terima Kasih + Upsell', 'variant' => 'thankyou-upsell'],
                    ],
                ],
            ],

            // Checkout Template 3: Premium
            [
                'name' => 'Checkout Premium (BM)',
                'description' => 'Checkout lengkap dengan video, FAQ, multiple testimoni, dan jaminan. Untuk produk premium.',
                'category' => 'checkout',
                'is_premium' => true,
                'is_active' => true,
                'template_data' => [
                    'steps' => [
                        ['type' => 'checkout', 'name' => 'Checkout Premium', 'variant' => 'checkout-premium'],
                        ['type' => 'thankyou', 'name' => 'Selamat Datang', 'variant' => 'thankyou-komuniti'],
                    ],
                ],
            ],

            // Thank You Template 1: Simple (standalone)
            [
                'name' => 'Terima Kasih Ringkas (BM)',
                'description' => 'Halaman terima kasih ringkas dengan langkah seterusnya. Clean dan profesional.',
                'category' => 'thankyou',
                'is_premium' => false,
                'is_active' => true,
                'template_data' => [
                    'steps' => [
                        ['type' => 'thankyou', 'name' => 'Terima Kasih', 'variant' => 'thankyou-ringkas'],
                    ],
                ],
            ],

            // Thank You Template 2: Upsell (standalone)
            [
                'name' => 'Terima Kasih + Upsell (BM)',
                'description' => 'Halaman terima kasih dengan tawaran one-time offer dan program rujukan.',
                'category' => 'thankyou',
                'is_premium' => false,
                'is_active' => true,
                'template_data' => [
                    'steps' => [
                        ['type' => 'thankyou', 'name' => 'Terima Kasih + Tawaran', 'variant' => 'thankyou-upsell'],
                    ],
                ],
            ],

            // Thank You Template 3: Community (standalone)
            [
                'name' => 'Terima Kasih Komuniti (BM)',
                'description' => 'Halaman alu-aluan komuniti dengan video, social sharing, dan engagement elements.',
                'category' => 'thankyou',
                'is_premium' => true,
                'is_active' => true,
                'template_data' => [
                    'steps' => [
                        ['type' => 'thankyou', 'name' => 'Selamat Datang', 'variant' => 'thankyou-komuniti'],
                    ],
                ],
            ],
        ];

        foreach ($templates as $template) {
            FunnelTemplate::updateOrCreate(
                ['name' => $template['name']],
                $template
            );
        }

        $this->command->info('Created '.count($templates).' funnel templates');
    }

    /**
     * Create sample funnels for demonstration.
     */
    protected function createSampleFunnels(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        if (! $admin) {
            $this->command->warn('Admin user not found. Skipping sample funnels.');

            return;
        }

        $funnels = [
            [
                'name' => 'Sample Product Sales Funnel',
                'type' => 'sales',
                'status' => 'published',
                'description' => 'A sample sales funnel demonstrating a typical product sales flow.',
                'settings' => [
                    'tracking' => ['fb_pixel' => '', 'google_analytics' => ''],
                    'branding' => ['primary_color' => '#3B82F6', 'font_family' => 'Inter'],
                ],
                'steps' => [
                    [
                        'name' => 'Sales Page',
                        'slug' => 'sales',
                        'type' => 'landing',
                        'is_active' => true,
                    ],
                    [
                        'name' => 'Order Form',
                        'slug' => 'checkout',
                        'type' => 'checkout',
                        'is_active' => true,
                    ],
                    [
                        'name' => 'One-Time Offer',
                        'slug' => 'upsell',
                        'type' => 'upsell',
                        'is_active' => true,
                    ],
                    [
                        'name' => 'Thank You',
                        'slug' => 'thank-you',
                        'type' => 'thankyou',
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'name' => 'Free Guide Lead Magnet',
                'type' => 'lead',
                'status' => 'draft',
                'description' => 'A lead generation funnel offering a free guide in exchange for email.',
                'settings' => [
                    'tracking' => ['fb_pixel' => '', 'google_analytics' => ''],
                    'branding' => ['primary_color' => '#10B981', 'font_family' => 'Inter'],
                ],
                'steps' => [
                    [
                        'name' => 'Get Your Free Guide',
                        'slug' => 'optin',
                        'type' => 'optin',
                        'is_active' => true,
                    ],
                    [
                        'name' => 'Download Page',
                        'slug' => 'download',
                        'type' => 'thankyou',
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'name' => 'Webinar Registration Funnel',
                'type' => 'webinar',
                'status' => 'draft',
                'description' => 'Register attendees for your upcoming webinar.',
                'settings' => [
                    'tracking' => ['fb_pixel' => '', 'google_analytics' => ''],
                    'branding' => ['primary_color' => '#8B5CF6', 'font_family' => 'Inter'],
                ],
                'steps' => [
                    [
                        'name' => 'Register for Free Training',
                        'slug' => 'register',
                        'type' => 'optin',
                        'is_active' => true,
                    ],
                    [
                        'name' => 'You\'re Registered!',
                        'slug' => 'confirmed',
                        'type' => 'thankyou',
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'name' => 'Course Enrollment Funnel',
                'type' => 'course',
                'status' => 'draft',
                'description' => 'Enroll students into your online course.',
                'settings' => [
                    'tracking' => ['fb_pixel' => '', 'google_analytics' => ''],
                    'branding' => ['primary_color' => '#F59E0B', 'font_family' => 'Inter'],
                ],
                'steps' => [
                    [
                        'name' => 'Course Overview',
                        'slug' => 'overview',
                        'type' => 'landing',
                        'is_active' => true,
                    ],
                    [
                        'name' => 'Enroll Now',
                        'slug' => 'enroll',
                        'type' => 'checkout',
                        'is_active' => true,
                    ],
                    [
                        'name' => 'Welcome to the Course',
                        'slug' => 'welcome',
                        'type' => 'thankyou',
                        'is_active' => true,
                    ],
                ],
            ],

            // ========================================
            // SAMPLE FUNNELS WITH MALAY TEMPLATES
            // ========================================

            [
                'name' => 'Jualan Ebook (Checkout Ringkas)',
                'type' => 'sales',
                'status' => 'draft',
                'description' => 'Funnel jualan ebook dengan checkout ringkas dan terima kasih mudah.',
                'settings' => [
                    'tracking' => ['fb_pixel' => '', 'google_analytics' => ''],
                    'branding' => ['primary_color' => '#10B981', 'font_family' => 'Inter'],
                ],
                'steps' => [
                    [
                        'name' => 'Halaman Jualan',
                        'slug' => 'jualan',
                        'type' => 'landing',
                        'is_active' => true,
                        'variant' => 'landing-ebook',
                    ],
                    [
                        'name' => 'Checkout',
                        'slug' => 'checkout',
                        'type' => 'checkout',
                        'is_active' => true,
                        'variant' => 'checkout-ringkas',
                    ],
                    [
                        'name' => 'Terima Kasih',
                        'slug' => 'terima-kasih',
                        'type' => 'thankyou',
                        'is_active' => true,
                        'variant' => 'thankyou-ringkas',
                    ],
                ],
            ],

            [
                'name' => 'Kursus Online (Checkout Persuasif)',
                'type' => 'course',
                'status' => 'draft',
                'description' => 'Funnel kursus dengan checkout persuasif dan upsell terima kasih.',
                'settings' => [
                    'tracking' => ['fb_pixel' => '', 'google_analytics' => ''],
                    'branding' => ['primary_color' => '#F59E0B', 'font_family' => 'Inter'],
                ],
                'steps' => [
                    [
                        'name' => 'Halaman Kursus',
                        'slug' => 'kursus',
                        'type' => 'landing',
                        'is_active' => true,
                        'variant' => 'landing-kursus',
                    ],
                    [
                        'name' => 'Daftar Sekarang',
                        'slug' => 'daftar',
                        'type' => 'checkout',
                        'is_active' => true,
                        'variant' => 'checkout-persuasif',
                    ],
                    [
                        'name' => 'Tahniah & Tawaran Khas',
                        'slug' => 'tahniah',
                        'type' => 'thankyou',
                        'is_active' => true,
                        'variant' => 'thankyou-upsell',
                    ],
                ],
            ],

            [
                'name' => 'Program Premium (Checkout Premium)',
                'type' => 'course',
                'status' => 'draft',
                'description' => 'Funnel program premium dengan checkout lengkap dan selamat datang komuniti.',
                'settings' => [
                    'tracking' => ['fb_pixel' => '', 'google_analytics' => ''],
                    'branding' => ['primary_color' => '#8B5CF6', 'font_family' => 'Inter'],
                ],
                'steps' => [
                    [
                        'name' => 'Program Eksklusif',
                        'slug' => 'program',
                        'type' => 'landing',
                        'is_active' => true,
                        'variant' => 'landing-premium',
                    ],
                    [
                        'name' => 'Sertai Sekarang',
                        'slug' => 'sertai',
                        'type' => 'checkout',
                        'is_active' => true,
                        'variant' => 'checkout-premium',
                    ],
                    [
                        'name' => 'Selamat Datang',
                        'slug' => 'selamat-datang',
                        'type' => 'thankyou',
                        'is_active' => true,
                        'variant' => 'thankyou-komuniti',
                    ],
                ],
            ],
        ];

        foreach ($funnels as $funnelData) {
            $steps = $funnelData['steps'];
            unset($funnelData['steps']);

            $funnel = Funnel::updateOrCreate(
                [
                    'user_id' => $admin->id,
                    'name' => $funnelData['name'],
                ],
                array_merge($funnelData, [
                    'uuid' => Str::uuid()->toString(),
                    'user_id' => $admin->id,
                    'published_at' => $funnelData['status'] === 'published' ? now() : null,
                ])
            );

            // Create steps
            $previousStep = null;
            foreach ($steps as $order => $stepData) {
                // Extract variant before creating step (variant is for content only, not DB)
                $variant = $stepData['variant'] ?? null;
                unset($stepData['variant']);

                $step = FunnelStep::updateOrCreate(
                    [
                        'funnel_id' => $funnel->id,
                        'slug' => $stepData['slug'],
                    ],
                    array_merge($stepData, [
                        'sort_order' => $order,
                        'settings' => [],
                    ])
                );

                // Link previous step to this one
                if ($previousStep) {
                    $previousStep->update(['next_step_id' => $step->id]);
                }
                $previousStep = $step;

                // Create placeholder content (with optional variant support)
                FunnelStepContent::updateOrCreate(
                    ['funnel_step_id' => $step->id],
                    [
                        'content' => $this->getSampleContent($stepData['type'], $stepData['name'], $variant),
                        'is_published' => $funnel->isPublished(),
                        'published_at' => $funnel->isPublished() ? now() : null,
                    ]
                );
            }
        }

        $this->command->info('Created '.count($funnels).' sample funnels');
    }

    /**
     * Get sample content for a step type.
     * All content is returned in Puck's array format.
     */
    protected function getSampleContent(string $type, string $name, ?string $variant = null): array
    {
        // Check for specific template variants
        if ($type === 'landing' && $variant) {
            return $this->convertToPuckFormat($this->getLandingTemplateContent($variant, $name));
        }

        if ($type === 'checkout' && $variant) {
            return $this->convertToPuckFormat($this->getCheckoutTemplateContent($variant, $name));
        }

        if ($type === 'thankyou' && $variant) {
            return $this->convertToPuckFormat($this->getThankYouTemplateContent($variant, $name));
        }

        // Base content structure (will be converted to Puck array format)
        $baseContent = [
            'content' => [],
            'root' => [
                'props' => [],
            ],
        ];

        // Add type-specific sample content structure
        switch ($type) {
            case 'landing':
            case 'sales':
                $baseContent['content'] = [
                    'hero-section' => [
                        'type' => 'HeroSection',
                        'props' => [
                            'headline' => 'Transform Your Life Today',
                            'subheadline' => 'Discover the proven system that has helped thousands achieve their goals.',
                            'ctaText' => 'Get Started Now',
                        ],
                    ],
                ];
                break;

            case 'optin':
                $baseContent['content'] = [
                    'optin-form' => [
                        'type' => 'OptinForm',
                        'props' => [
                            'headline' => 'Get Your Free Guide',
                            'description' => 'Enter your email below to receive instant access.',
                            'buttonText' => 'Send Me The Guide',
                            'fields' => ['name', 'email'],
                        ],
                    ],
                ];
                break;

            case 'checkout':
                $baseContent['content'] = [
                    'checkout-form' => [
                        'type' => 'CheckoutForm',
                        'props' => [
                            'headline' => 'Complete Your Order',
                            'showOrderSummary' => true,
                            'showGuarantee' => true,
                        ],
                    ],
                ];
                break;

            case 'upsell':
                $baseContent['content'] = [
                    'upsell-offer' => [
                        'type' => 'HeroSection',
                        'props' => [
                            'headline' => 'Wait! One-Time Special Offer',
                            'subheadline' => 'Enhance your purchase with this exclusive add-on.',
                            'ctaText' => 'Yes, Add This To My Order',
                        ],
                    ],
                ];
                break;

            case 'downsell':
                $baseContent['content'] = [
                    'downsell-offer' => [
                        'type' => 'HeroSection',
                        'props' => [
                            'headline' => 'Wait! Here\'s A Better Deal',
                            'subheadline' => 'Since you passed on the previous offer, we have something special for you.',
                            'ctaText' => 'Yes, I Want This Deal',
                        ],
                    ],
                ];
                break;

            case 'thankyou':
                $baseContent['content'] = [
                    'thank-you-message' => [
                        'type' => 'HeroSection',
                        'props' => [
                            'headline' => 'Thank You!',
                            'subheadline' => 'Your order has been received. Check your email for confirmation and next steps.',
                            'ctaText' => 'Continue',
                        ],
                    ],
                ];
                break;
        }

        // Convert ALL base content to Puck's array format
        return $this->convertToPuckFormat($baseContent);
    }

    /**
     * Get landing page template content variants (Malay - Bold & Persuasive).
     * Optimized for books and courses sales pages.
     */
    protected function getLandingTemplateContent(string $variant, string $name): array
    {
        $templates = [
            // ========================================
            // TEMPLATE 1: Landing Ebook (Simple Sales)
            // Clean, focused ebook sales page
            // ========================================
            'landing-ebook' => [
                'content' => [
                    'hero-1' => [
                        'type' => 'HeroSection',
                        'props' => [
                            'headline' => '📚 Rahsia Yang Ramai Orang Tak Tahu...',
                            'subheadline' => 'Bagaimana anda boleh [HASIL] dalam masa [TEMPOH] tanpa [HALANGAN BIASA]',
                            'ctaText' => 'Dapatkan Sekarang →',
                            'alignment' => 'center',
                        ],
                    ],
                    'spacer-1' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'text-problem' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center; max-width: 800px; margin: 0 auto;"><h2 style="font-size: 28px; color: #dc2626; margin-bottom: 20px;">😫 Adakah Anda Mengalami Masalah Ini?</h2></div>',
                            'alignment' => 'center',
                        ],
                    ],
                    'features-problem' => [
                        'type' => 'FeaturesGrid',
                        'props' => [
                            'columns' => 2,
                            'features' => [
                                [
                                    'icon' => '❌',
                                    'title' => 'Tak Tahu Nak Mula Dari Mana',
                                    'description' => 'Terlalu banyak maklumat yang mengelirukan',
                                ],
                                [
                                    'icon' => '❌',
                                    'title' => 'Dah Cuba Tapi Tak Jadi',
                                    'description' => 'Penat mencuba tanpa hasil yang memuaskan',
                                ],
                                [
                                    'icon' => '❌',
                                    'title' => 'Takut Nak Ambil Langkah',
                                    'description' => 'Ragu-ragu dengan kebolehan sendiri',
                                ],
                                [
                                    'icon' => '❌',
                                    'title' => 'Tiada Mentor/Panduan',
                                    'description' => 'Belajar sendiri tanpa bimbingan yang betul',
                                ],
                            ],
                        ],
                    ],
                    'spacer-2' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '40px'],
                    ],
                    'text-solution' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center; padding: 30px; background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); border-radius: 20px;"><h2 style="font-size: 32px; color: #166534; margin: 0;">✨ Kini Ada Penyelesaiannya!</h2><p style="font-size: 18px; color: #15803d; margin-top: 15px;">Panduan lengkap yang akan membimbing anda langkah demi langkah</p></div>',
                            'alignment' => 'center',
                        ],
                    ],
                    'spacer-3' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '40px'],
                    ],
                    'text-benefits' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center;"><h2 style="font-size: 28px; color: #1e293b;">📖 Apa Yang Anda Akan Pelajari:</h2></div>',
                            'alignment' => 'center',
                        ],
                    ],
                    'features-benefits' => [
                        'type' => 'FeaturesGrid',
                        'props' => [
                            'columns' => 3,
                            'features' => [
                                [
                                    'icon' => '✅',
                                    'title' => 'Strategi Terbukti',
                                    'description' => 'Teknik yang telah diuji dan terbukti berkesan',
                                ],
                                [
                                    'icon' => '✅',
                                    'title' => 'Langkah Praktikal',
                                    'description' => 'Panduan mudah diikuti dari A hingga Z',
                                ],
                                [
                                    'icon' => '✅',
                                    'title' => 'Hasil Cepat',
                                    'description' => 'Lihat perubahan dalam masa singkat',
                                ],
                            ],
                        ],
                    ],
                    'spacer-4' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '40px'],
                    ],
                    'testimonial-1' => [
                        'type' => 'TestimonialBlock',
                        'props' => [
                            'quote' => 'Saya tak sangka panduan ini sangat mudah difahami. Dalam masa 2 minggu sahaja, saya dah nampak hasilnya!',
                            'author' => 'Aminah binti Hassan',
                            'role' => 'Pembaca Ebook',
                        ],
                    ],
                    'spacer-5' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '40px'],
                    ],
                    'pricing-1' => [
                        'type' => 'PricingCard',
                        'props' => [
                            'title' => 'EBOOK LENGKAP',
                            'price' => '47',
                            'originalPrice' => '197',
                            'period' => 'sekali bayar',
                            'features' => [
                                '✅ Ebook PDF (100+ muka surat)',
                                '✅ Bonus Checklist & Template',
                                '✅ Akses Seumur Hidup',
                                '✅ Kemaskini Percuma',
                            ],
                            'ctaText' => 'BELI SEKARANG',
                            'ctaUrl' => '#checkout',
                            'highlighted' => true,
                        ],
                    ],
                    'spacer-6' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'text-guarantee' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center; padding: 25px; background: #fef3c7; border-radius: 12px; border: 2px solid #f59e0b;"><p style="margin: 0; font-size: 18px; color: #92400e;"><strong>🛡️ Jaminan 30 Hari Wang Dikembalikan</strong></p><p style="margin: 10px 0 0 0; color: #92400e;">Tiada risiko! Cuba dahulu, jika tak sesuai kami pulangkan wang anda.</p></div>',
                            'alignment' => 'center',
                        ],
                    ],
                ],
                'root' => ['props' => ['title' => $name]],
            ],

            // ========================================
            // TEMPLATE 2: Landing Kursus (Course Sales)
            // Full course sales page with video
            // ========================================
            'landing-kursus' => [
                'content' => [
                    'hero-1' => [
                        'type' => 'HeroSection',
                        'props' => [
                            'headline' => '🎓 Kuasai [KEMAHIRAN] Dalam Masa 30 Hari',
                            'subheadline' => 'Kursus online paling komprehensif untuk membantu anda mencapai [MATLAMAT]',
                            'ctaText' => 'Daftar Sekarang →',
                            'alignment' => 'center',
                        ],
                    ],
                    'spacer-1' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '20px'],
                    ],
                    'video-1' => [
                        'type' => 'VideoBlock',
                        'props' => [
                            'videoUrl' => '',
                            'autoplay' => false,
                            'muted' => false,
                        ],
                    ],
                    'text-video-caption' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<p style="text-align: center; color: #64748b; font-style: italic;">👆 Tonton video pengenalan (3 minit)</p>',
                            'alignment' => 'center',
                        ],
                    ],
                    'spacer-2' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '40px'],
                    ],
                    'text-for-who' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center;"><h2 style="font-size: 28px; color: #1e293b;">🎯 Kursus Ini Sesuai Untuk Anda Jika...</h2></div>',
                            'alignment' => 'center',
                        ],
                    ],
                    'features-target' => [
                        'type' => 'FeaturesGrid',
                        'props' => [
                            'columns' => 2,
                            'features' => [
                                [
                                    'icon' => '👤',
                                    'title' => 'Pemula Yang Nak Belajar',
                                    'description' => 'Tiada pengalaman? Tak mengapa! Kursus ini bermula dari asas.',
                                ],
                                [
                                    'icon' => '💼',
                                    'title' => 'Profesional Yang Nak Upgrade',
                                    'description' => 'Tingkatkan kemahiran untuk kenaikan gaji atau peluang baru.',
                                ],
                                [
                                    'icon' => '🏠',
                                    'title' => 'Ibu/Bapa Yang Sibuk',
                                    'description' => 'Belajar mengikut jadual sendiri, bila-bila masa.',
                                ],
                                [
                                    'icon' => '🚀',
                                    'title' => 'Usahawan Yang Nak Scale',
                                    'description' => 'Kuasai kemahiran untuk bawa bisnes ke level seterusnya.',
                                ],
                            ],
                        ],
                    ],
                    'spacer-3' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '40px'],
                    ],
                    'text-modules' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center;"><h2 style="font-size: 28px; color: #1e293b;">📚 Apa Yang Anda Akan Pelajari:</h2></div>',
                            'alignment' => 'center',
                        ],
                    ],
                    'features-modules' => [
                        'type' => 'FeaturesGrid',
                        'props' => [
                            'columns' => 2,
                            'features' => [
                                [
                                    'icon' => '1️⃣',
                                    'title' => 'Modul 1: Asas & Persediaan',
                                    'description' => 'Fahami konsep asas dan sediakan diri untuk berjaya',
                                ],
                                [
                                    'icon' => '2️⃣',
                                    'title' => 'Modul 2: Strategi & Teknik',
                                    'description' => 'Pelajari strategi yang telah terbukti berkesan',
                                ],
                                [
                                    'icon' => '3️⃣',
                                    'title' => 'Modul 3: Implementasi',
                                    'description' => 'Praktik langsung dengan panduan step-by-step',
                                ],
                                [
                                    'icon' => '4️⃣',
                                    'title' => 'Modul 4: Optimisasi & Scale',
                                    'description' => 'Tingkatkan hasil dan kembangkan kejayaan anda',
                                ],
                            ],
                        ],
                    ],
                    'spacer-4' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '40px'],
                    ],
                    'testimonial-1' => [
                        'type' => 'TestimonialBlock',
                        'props' => [
                            'quote' => 'Kursus terbaik yang pernah saya ambil! Pengajar sangat berpengalaman dan kandungan sangat praktikal.',
                            'author' => 'Mohd Faizal',
                            'role' => 'Pelajar Kursus',
                        ],
                    ],
                    'testimonial-2' => [
                        'type' => 'TestimonialBlock',
                        'props' => [
                            'quote' => 'Selepas 3 minggu ikut kursus ini, saya dah mula nampak hasil. Sangat recommended!',
                            'author' => 'Nurul Aisyah',
                            'role' => 'Usahawan Online',
                        ],
                    ],
                    'spacer-5' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '40px'],
                    ],
                    'pricing-1' => [
                        'type' => 'PricingCard',
                        'props' => [
                            'title' => 'AKSES PENUH KURSUS',
                            'price' => '197',
                            'originalPrice' => '497',
                            'period' => 'sekali bayar',
                            'features' => [
                                '✅ 30+ Video Tutorial HD',
                                '✅ Nota PDF & Worksheet',
                                '✅ Akses Group Sokongan',
                                '✅ Sijil Tamat Kursus',
                                '✅ Kemaskini Percuma Selamanya',
                            ],
                            'ctaText' => 'DAFTAR SEKARANG',
                            'ctaUrl' => '#checkout',
                            'highlighted' => true,
                        ],
                    ],
                    'spacer-6' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'text-guarantee' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center; padding: 30px; background: #f0fdf4; border-radius: 16px; border: 3px solid #22c55e;"><p style="margin: 0; font-size: 22px; color: #166534;"><strong>🛡️ JAMINAN 100% WANG DIKEMBALIKAN</strong></p><p style="margin: 15px 0 0 0; font-size: 16px; color: #166534;">Cuba kursus ini selama 30 hari. Jika anda tidak berpuas hati, hubungi kami dan kami akan pulangkan wang anda sepenuhnya. Tiada soalan ditanya!</p></div>',
                            'alignment' => 'center',
                        ],
                    ],
                ],
                'root' => ['props' => ['title' => $name]],
            ],

            // ========================================
            // TEMPLATE 3: Landing Premium (Premium Program)
            // High-ticket program sales page
            // ========================================
            'landing-premium' => [
                'content' => [
                    'hero-1' => [
                        'type' => 'HeroSection',
                        'props' => [
                            'headline' => '🏆 Program Transformasi Eksklusif',
                            'subheadline' => 'Sertai program intensif yang telah bantu 5,000+ pelajar capai kejayaan luar biasa',
                            'ctaText' => 'Mohon Sekarang →',
                            'alignment' => 'center',
                        ],
                    ],
                    'spacer-1' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '20px'],
                    ],
                    'text-social-proof' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="display: flex; justify-content: center; gap: 40px; flex-wrap: wrap; padding: 20px;"><div style="text-align: center;"><p style="font-size: 36px; font-weight: bold; color: #3b82f6; margin: 0;">5,000+</p><p style="color: #64748b; margin: 5px 0 0 0;">Pelajar Berjaya</p></div><div style="text-align: center;"><p style="font-size: 36px; font-weight: bold; color: #22c55e; margin: 0;">4.9/5</p><p style="color: #64748b; margin: 5px 0 0 0;">Rating Pelajar</p></div><div style="text-align: center;"><p style="font-size: 36px; font-weight: bold; color: #f59e0b; margin: 0;">97%</p><p style="color: #64748b; margin: 5px 0 0 0;">Kadar Kejayaan</p></div></div>',
                            'alignment' => 'center',
                        ],
                    ],
                    'spacer-2' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'video-1' => [
                        'type' => 'VideoBlock',
                        'props' => [
                            'videoUrl' => '',
                            'autoplay' => false,
                            'muted' => false,
                        ],
                    ],
                    'spacer-3' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '40px'],
                    ],
                    'text-transformation' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center;"><h2 style="font-size: 32px; color: #1e293b;">🚀 Transformasi Yang Akan Anda Alami:</h2></div>',
                            'alignment' => 'center',
                        ],
                    ],
                    'features-transformation' => [
                        'type' => 'FeaturesGrid',
                        'props' => [
                            'columns' => 3,
                            'features' => [
                                [
                                    'icon' => '💡',
                                    'title' => 'Mindset Juara',
                                    'description' => 'Ubah cara berfikir untuk capai kejayaan luar biasa',
                                ],
                                [
                                    'icon' => '📈',
                                    'title' => 'Strategi Terbukti',
                                    'description' => 'Framework yang telah bantu ramai orang berjaya',
                                ],
                                [
                                    'icon' => '👥',
                                    'title' => 'Komuniti Eksklusif',
                                    'description' => 'Network dengan individu yang sama visi',
                                ],
                            ],
                        ],
                    ],
                    'spacer-4' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '40px'],
                    ],
                    'text-included' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center;"><h2 style="font-size: 28px; color: #1e293b;">📦 Apa Yang Termasuk Dalam Program:</h2></div>',
                            'alignment' => 'center',
                        ],
                    ],
                    'features-included' => [
                        'type' => 'FeaturesGrid',
                        'props' => [
                            'columns' => 2,
                            'features' => [
                                [
                                    'icon' => '🎥',
                                    'title' => '12 Modul Video Premium (Nilai RM2,997)',
                                    'description' => 'Kandungan eksklusif dengan akses seumur hidup',
                                ],
                                [
                                    'icon' => '📞',
                                    'title' => '4x Sesi Coaching Group (Nilai RM1,997)',
                                    'description' => 'Bimbingan langsung bersama mentor',
                                ],
                                [
                                    'icon' => '📋',
                                    'title' => 'Template & SOP Lengkap (Nilai RM997)',
                                    'description' => '50+ template siap pakai untuk kejayaan anda',
                                ],
                                [
                                    'icon' => '🤝',
                                    'title' => 'Akses Komuniti VIP (Nilai RM997)',
                                    'description' => 'Network eksklusif dengan ahli-ahli berjaya',
                                ],
                            ],
                        ],
                    ],
                    'spacer-5' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '40px'],
                    ],
                    'testimonial-1' => [
                        'type' => 'TestimonialBlock',
                        'props' => [
                            'quote' => 'Program ini benar-benar mengubah hidup saya! Dalam 6 bulan, pendapatan saya meningkat 5x ganda. Terima kasih!',
                            'author' => 'Dato\' Ahmad Razali',
                            'role' => 'CEO, Razali Holdings',
                        ],
                    ],
                    'testimonial-2' => [
                        'type' => 'TestimonialBlock',
                        'props' => [
                            'quote' => 'Pelaburan terbaik yang pernah saya buat. Ilmu dan networking yang saya dapat sangat bernilai!',
                            'author' => 'Datin Seri Fauziah',
                            'role' => 'Pengasas, FS Beauty Empire',
                        ],
                    ],
                    'spacer-6' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '40px'],
                    ],
                    'pricing-1' => [
                        'type' => 'PricingCard',
                        'props' => [
                            'title' => 'PROGRAM PREMIUM',
                            'price' => '997',
                            'originalPrice' => '6,988',
                            'period' => 'atau 3x RM347',
                            'features' => [
                                '✅ 12 Modul Video Premium',
                                '✅ 4x Sesi Coaching Group',
                                '✅ 50+ Template & SOP',
                                '✅ Akses Komuniti VIP Seumur Hidup',
                                '✅ Bonus: 2x Sesi 1-on-1 dengan Mentor',
                                '✅ Sijil Profesional',
                            ],
                            'ctaText' => 'SERTAI SEKARANG',
                            'ctaUrl' => '#checkout',
                            'highlighted' => true,
                        ],
                    ],
                    'spacer-7' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'faq-1' => [
                        'type' => 'FaqAccordion',
                        'props' => [
                            'items' => [
                                [
                                    'question' => 'Adakah program ini sesuai untuk pemula?',
                                    'answer' => 'Ya! Program ini direka untuk semua peringkat. Kami akan bimbing anda dari asas sehingga mahir.',
                                ],
                                [
                                    'question' => 'Berapa lama saya boleh akses program ini?',
                                    'answer' => 'Anda akan dapat akses seumur hidup! Termasuk semua kemaskini masa depan.',
                                ],
                                [
                                    'question' => 'Ada jaminan wang dikembalikan?',
                                    'answer' => 'Ya! Kami menawarkan jaminan 30 hari. Jika tidak berpuas hati, kami pulangkan 100% wang anda.',
                                ],
                            ],
                        ],
                    ],
                    'spacer-8' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'text-guarantee' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center; padding: 40px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 20px; border: 4px solid #f59e0b;"><p style="margin: 0; font-size: 28px; color: #92400e;"><strong>🛡️ JAMINAN TRIPLE PROTECTION</strong></p><p style="margin: 20px 0; font-size: 16px; color: #92400e;"><strong>1.</strong> Jaminan Wang Dikembalikan 30 Hari<br><strong>2.</strong> Jaminan Akses Seumur Hidup<br><strong>3.</strong> Jaminan Sokongan Berterusan</p><p style="margin: 0; font-size: 14px; color: #92400e;">🔒 Pembayaran 100% Selamat • 📧 Sokongan 24/7 • ⭐ 4.9/5 Rating</p></div>',
                            'alignment' => 'center',
                        ],
                    ],
                ],
                'root' => ['props' => ['title' => $name]],
            ],
        ];

        return $templates[$variant] ?? $templates['landing-ebook'];
    }

    /**
     * Get checkout page template content variants (Malay - Bold & Persuasive).
     * Optimized for books and courses with high conversion elements.
     */
    protected function getCheckoutTemplateContent(string $variant, string $name): array
    {
        $templates = [
            // ========================================
            // TEMPLATE 1: Checkout Ringkas (Simple)
            // Clean, focused, fast checkout
            // ========================================
            'checkout-ringkas' => [
                'content' => [
                    'hero-1' => [
                        'type' => 'HeroSection',
                        'props' => [
                            'headline' => 'Lengkapkan Pesanan Anda',
                            'subheadline' => 'Anda hanya selangkah lagi untuk memiliki ilmu yang akan mengubah hidup anda!',
                            'alignment' => 'center',
                        ],
                    ],
                    'spacer-1' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '20px'],
                    ],
                    'checkout-form' => [
                        'type' => 'CheckoutForm',
                        'props' => [
                            'showBillingAddress' => true,
                            'showShippingAddress' => false,
                        ],
                    ],
                    'spacer-2' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '20px'],
                    ],
                    'order-bump-1' => [
                        'type' => 'OrderBump',
                        'props' => [
                            'headline' => 'Bonus Eksklusif!',
                            'description' => 'Tambah workbook lengkap dengan harga istimewa - hanya untuk pembeli hari ini!',
                            'checkboxLabel' => 'Ya! Tambah ke pesanan saya',
                            'price' => '29.00',
                            'comparePrice' => '79.00',
                            'highlightColor' => '#fef3c7',
                        ],
                    ],
                    'spacer-3' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'trust-text' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center; padding: 20px; background: #f0fdf4; border-radius: 12px; border: 2px solid #22c55e;"><p style="margin: 0; font-size: 18px; color: #166534;"><strong>🔒 Pembayaran Selamat 100%</strong></p><p style="margin: 10px 0 0 0; color: #166534;">Transaksi anda dilindungi dengan enkripsi SSL • Jaminan Wang Dikembalikan 30 Hari</p></div>',
                            'alignment' => 'center',
                        ],
                    ],
                ],
                'root' => ['props' => ['title' => $name]],
            ],

            // ========================================
            // TEMPLATE 2: Checkout Persuasif (Persuasive)
            // With testimonials, countdown, benefits
            // ========================================
            'checkout-persuasif' => [
                'content' => [
                    'countdown-1' => [
                        'type' => 'CountdownTimer',
                        'props' => [
                            'endDate' => date('Y-m-d H:i', strtotime('+3 days')),
                            'expiredMessage' => 'Tawaran telah tamat!',
                        ],
                    ],
                    'hero-1' => [
                        'type' => 'HeroSection',
                        'props' => [
                            'headline' => '⚡ TAWARAN TERHAD!',
                            'subheadline' => 'Dapatkan akses penuh dengan harga istimewa sebelum tawaran ini tamat!',
                            'alignment' => 'center',
                        ],
                    ],
                    'spacer-1' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '20px'],
                    ],
                    'features-1' => [
                        'type' => 'FeaturesGrid',
                        'props' => [
                            'columns' => 3,
                            'features' => [
                                [
                                    'icon' => '📚',
                                    'title' => 'Akses Seumur Hidup',
                                    'description' => 'Belajar bila-bila masa, tanpa had',
                                ],
                                [
                                    'icon' => '🎯',
                                    'title' => 'Langkah Demi Langkah',
                                    'description' => 'Mudah difahami dan diikuti',
                                ],
                                [
                                    'icon' => '💬',
                                    'title' => 'Sokongan Komuniti',
                                    'description' => 'Sertai group eksklusif',
                                ],
                            ],
                        ],
                    ],
                    'spacer-2' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '20px'],
                    ],
                    'testimonial-1' => [
                        'type' => 'TestimonialBlock',
                        'props' => [
                            'quote' => 'Alhamdulillah, selepas belajar dengan panduan ini, pendapatan saya meningkat 3x ganda dalam masa 2 bulan! Sangat berbaloi!',
                            'author' => 'Ahmad Rizal',
                            'role' => 'Usahawan Online',
                        ],
                    ],
                    'spacer-3' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'checkout-form' => [
                        'type' => 'CheckoutForm',
                        'props' => [
                            'showBillingAddress' => true,
                            'showShippingAddress' => false,
                        ],
                    ],
                    'spacer-4' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '20px'],
                    ],
                    'order-bump-1' => [
                        'type' => 'OrderBump',
                        'props' => [
                            'headline' => '🎁 BONUS KHAS: Template & Checklist',
                            'description' => 'Jimat masa dengan template siap pakai yang telah terbukti berkesan. Nilai sebenar RM199!',
                            'checkboxLabel' => 'Ya! Saya nak bonus ini',
                            'price' => '47.00',
                            'comparePrice' => '199.00',
                            'highlightColor' => '#fef3c7',
                        ],
                    ],
                    'spacer-5' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'guarantee-text' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center; padding: 30px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 16px; border: 3px solid #f59e0b;"><p style="margin: 0; font-size: 24px; color: #92400e;"><strong>🛡️ JAMINAN 100% WANG DIKEMBALIKAN</strong></p><p style="margin: 15px 0 0 0; font-size: 16px; color: #92400e;">Jika anda tidak berpuas hati dalam masa 30 hari, kami akan pulangkan wang anda sepenuhnya. Tiada soalan ditanya!</p></div>',
                            'alignment' => 'center',
                        ],
                    ],
                ],
                'root' => ['props' => ['title' => $name]],
            ],

            // ========================================
            // TEMPLATE 3: Checkout Premium (Premium)
            // Video, FAQ, multiple social proof
            // ========================================
            'checkout-premium' => [
                'content' => [
                    'countdown-1' => [
                        'type' => 'CountdownTimer',
                        'props' => [
                            'endDate' => date('Y-m-d H:i', strtotime('+7 days')),
                            'expiredMessage' => 'Harga akan kembali ke harga asal!',
                        ],
                    ],
                    'hero-1' => [
                        'type' => 'HeroSection',
                        'props' => [
                            'headline' => '🔥 PELUANG TERAKHIR!',
                            'subheadline' => 'Sertai 5,000+ pelajar yang telah berjaya mengubah kehidupan mereka',
                            'alignment' => 'center',
                        ],
                    ],
                    'spacer-1' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '20px'],
                    ],
                    'video-1' => [
                        'type' => 'VideoBlock',
                        'props' => [
                            'videoUrl' => '',
                            'autoplay' => false,
                            'muted' => false,
                        ],
                    ],
                    'spacer-2' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'text-value' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center;"><h2 style="font-size: 28px; color: #1e293b; margin-bottom: 20px;">📦 APA YANG ANDA AKAN DAPAT:</h2></div>',
                            'alignment' => 'center',
                        ],
                    ],
                    'features-1' => [
                        'type' => 'FeaturesGrid',
                        'props' => [
                            'columns' => 2,
                            'features' => [
                                [
                                    'icon' => '📖',
                                    'title' => 'Modul Lengkap (Nilai RM497)',
                                    'description' => '12 modul video HD dengan nota PDF',
                                ],
                                [
                                    'icon' => '📋',
                                    'title' => 'Template Siap Pakai (Nilai RM297)',
                                    'description' => '50+ template yang boleh terus digunakan',
                                ],
                                [
                                    'icon' => '👥',
                                    'title' => 'Akses Komuniti (Nilai RM197)',
                                    'description' => 'Group sokongan eksklusif seumur hidup',
                                ],
                                [
                                    'icon' => '🎁',
                                    'title' => 'Bonus Khas (Nilai RM397)',
                                    'description' => 'Sesi Q&A bulanan bersama pakar',
                                ],
                            ],
                        ],
                    ],
                    'spacer-3' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '20px'],
                    ],
                    'pricing-1' => [
                        'type' => 'PricingCard',
                        'props' => [
                            'title' => 'PAKEJ LENGKAP',
                            'price' => '197',
                            'originalPrice' => '1,388',
                            'period' => 'sekali bayar',
                            'features' => [
                                '✅ Semua 12 Modul Video HD',
                                '✅ 50+ Template Premium',
                                '✅ Akses Komuniti Seumur Hidup',
                                '✅ Bonus Sesi Q&A Bulanan',
                                '✅ Kemaskini Percuma Selamanya',
                                '✅ Sijil Tamat Kursus',
                            ],
                            'ctaText' => 'DAFTAR SEKARANG',
                            'ctaUrl' => '#checkout-form',
                            'highlighted' => true,
                        ],
                    ],
                    'spacer-4' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'checkout-form' => [
                        'type' => 'CheckoutForm',
                        'props' => [
                            'showBillingAddress' => true,
                            'showShippingAddress' => false,
                        ],
                    ],
                    'spacer-5' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '20px'],
                    ],
                    'order-bump-1' => [
                        'type' => 'OrderBump',
                        'props' => [
                            'headline' => '⚡ UPGRADE VIP: Coaching 1-on-1',
                            'description' => 'Dapatkan 2 sesi coaching peribadi dengan mentor berpengalaman. Terhad untuk 50 orang pertama sahaja!',
                            'checkboxLabel' => 'Ya! Saya nak coaching VIP',
                            'price' => '97.00',
                            'comparePrice' => '497.00',
                            'highlightColor' => '#dbeafe',
                        ],
                    ],
                    'spacer-6' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'testimonial-1' => [
                        'type' => 'TestimonialBlock',
                        'props' => [
                            'quote' => 'Saya sangat skeptikal pada mulanya, tapi selepas ikut kursus ini, saya berjaya jana RM15,000 pertama saya dalam masa sebulan!',
                            'author' => 'Siti Nurhaliza',
                            'role' => 'Ibu & Usahawan',
                        ],
                    ],
                    'testimonial-2' => [
                        'type' => 'TestimonialBlock',
                        'props' => [
                            'quote' => 'Panduan paling lengkap yang pernah saya jumpa. Setiap sen yang saya laburkan, berbaloi 100x ganda!',
                            'author' => 'Muhammad Hafiz',
                            'role' => 'Digital Marketer',
                        ],
                    ],
                    'spacer-7' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'faq-1' => [
                        'type' => 'FaqAccordion',
                        'props' => [
                            'items' => [
                                [
                                    'question' => 'Adakah kursus ini sesuai untuk pemula?',
                                    'answer' => 'Ya! Kursus ini direka khas untuk semua peringkat. Kami mulakan dari asas dan membimbing anda langkah demi langkah.',
                                ],
                                [
                                    'question' => 'Berapa lama saya boleh akses kursus ini?',
                                    'answer' => 'Anda akan dapat akses seumur hidup! Belajar mengikut kadar anda sendiri, bila-bila masa.',
                                ],
                                [
                                    'question' => 'Bagaimana jika saya tidak berpuas hati?',
                                    'answer' => 'Kami menawarkan jaminan wang dikembalikan 30 hari. Jika anda tidak berpuas hati, hubungi kami dan kami akan pulangkan wang anda sepenuhnya.',
                                ],
                                [
                                    'question' => 'Bila saya akan dapat akses?',
                                    'answer' => 'Sebaik sahaja pembayaran disahkan, anda akan menerima email dengan butiran login dalam masa 5 minit.',
                                ],
                            ],
                        ],
                    ],
                    'spacer-8' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'guarantee-text' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center; padding: 40px; background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); border-radius: 20px; border: 4px solid #22c55e;"><p style="margin: 0; font-size: 28px; color: #166534;"><strong>🛡️ JAMINAN TANPA RISIKO</strong></p><p style="margin: 20px 0; font-size: 18px; color: #166534;">Kami yakin dengan kualiti kursus ini. Cuba selama 30 hari - jika tidak berbaloi, kami pulangkan 100% wang anda!</p><p style="margin: 0; font-size: 14px; color: #166534;">🔒 Pembayaran Selamat • 📧 Sokongan 24/7 • ⭐ 4.9/5 Rating</p></div>',
                            'alignment' => 'center',
                        ],
                    ],
                ],
                'root' => ['props' => ['title' => $name]],
            ],
        ];

        return $templates[$variant] ?? $templates['checkout-ringkas'];
    }

    /**
     * Get thank you page template content variants (Malay - Bold & Persuasive).
     * Optimized for engagement and upsells.
     */
    protected function getThankYouTemplateContent(string $variant, string $name): array
    {
        $templates = [
            // ========================================
            // TEMPLATE 1: Terima Kasih Ringkas (Simple)
            // Clean confirmation with next steps
            // ========================================
            'thankyou-ringkas' => [
                'content' => [
                    'hero-1' => [
                        'type' => 'HeroSection',
                        'props' => [
                            'headline' => '🎉 Tahniah! Pesanan Anda Berjaya!',
                            'subheadline' => 'Terima kasih kerana mempercayai kami. Anda telah membuat keputusan yang tepat!',
                            'alignment' => 'center',
                        ],
                    ],
                    'spacer-1' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'text-order' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center; padding: 30px; background: #f0fdf4; border-radius: 16px; border: 2px solid #22c55e;"><p style="margin: 0; font-size: 20px; color: #166534;"><strong>✅ Pesanan anda telah disahkan!</strong></p><p style="margin: 15px 0 0 0; color: #166534;">Nombor pesanan dan butiran akses telah dihantar ke email anda.</p></div>',
                            'alignment' => 'center',
                        ],
                    ],
                    'spacer-2' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'text-steps' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: left; padding: 30px; background: #f8fafc; border-radius: 16px;"><h3 style="margin: 0 0 20px 0; font-size: 22px; color: #1e293b;">📋 Langkah Seterusnya:</h3><ol style="margin: 0; padding-left: 20px; color: #475569; font-size: 16px; line-height: 2;"><li><strong>Semak email anda</strong> - Butiran akses telah dihantar</li><li><strong>Login ke akaun anda</strong> - Gunakan email yang didaftarkan</li><li><strong>Mula belajar!</strong> - Akses semua kandungan dengan segera</li></ol></div>',
                            'alignment' => 'left',
                        ],
                    ],
                    'spacer-3' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'text-support' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center; padding: 20px; background: #eff6ff; border-radius: 12px;"><p style="margin: 0; color: #1e40af;"><strong>Ada soalan?</strong> Hubungi kami di support@example.com</p></div>',
                            'alignment' => 'center',
                        ],
                    ],
                    'spacer-4' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'button-access' => [
                        'type' => 'ButtonBlock',
                        'props' => [
                            'text' => 'Akses Kursus Sekarang →',
                            'url' => '/dashboard',
                            'variant' => 'success',
                            'size' => 'large',
                            'fullWidth' => true,
                        ],
                    ],
                ],
                'root' => ['props' => ['title' => $name]],
            ],

            // ========================================
            // TEMPLATE 2: Terima Kasih + Upsell
            // With one-time offer and cross-sells
            // ========================================
            'thankyou-upsell' => [
                'content' => [
                    'hero-1' => [
                        'type' => 'HeroSection',
                        'props' => [
                            'headline' => '🎉 TAHNIAH! Pesanan Berjaya!',
                            'subheadline' => 'Sebelum anda pergi... kami ada tawaran ISTIMEWA untuk anda!',
                            'alignment' => 'center',
                        ],
                    ],
                    'spacer-1' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '20px'],
                    ],
                    'text-order' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center; padding: 20px; background: #f0fdf4; border-radius: 12px; border: 2px solid #22c55e;"><p style="margin: 0; color: #166534;"><strong>✅ Pesanan #[ORDER_NUMBER] disahkan!</strong> Semak email untuk butiran akses.</p></div>',
                            'alignment' => 'center',
                        ],
                    ],
                    'spacer-2' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'countdown-1' => [
                        'type' => 'CountdownTimer',
                        'props' => [
                            'endDate' => date('Y-m-d H:i', strtotime('+15 minutes')),
                            'expiredMessage' => 'Tawaran telah tamat!',
                        ],
                    ],
                    'text-upsell-header' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center;"><h2 style="font-size: 32px; color: #dc2626; margin: 0;">⚡ TAWARAN SEKALI SEUMUR HIDUP!</h2><p style="font-size: 18px; color: #1e293b; margin-top: 10px;">Hanya untuk pembeli hari ini - tawaran ini TIDAK akan ditawarkan lagi!</p></div>',
                            'alignment' => 'center',
                        ],
                    ],
                    'spacer-3' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '20px'],
                    ],
                    'pricing-upsell' => [
                        'type' => 'PricingCard',
                        'props' => [
                            'title' => 'UPGRADE KE PAKEJ VIP',
                            'price' => '97',
                            'originalPrice' => '497',
                            'period' => 'sekali bayar',
                            'features' => [
                                '🎯 2x Sesi Coaching 1-on-1',
                                '📱 Akses Group WhatsApp VIP',
                                '📚 5 Ebook Bonus Eksklusif',
                                '🎁 Template Premium (Nilai RM299)',
                                '⚡ Fast-Track Support 24 Jam',
                            ],
                            'ctaText' => 'YA! UPGRADE SEKARANG',
                            'ctaUrl' => '#',
                            'highlighted' => true,
                        ],
                    ],
                    'spacer-4' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '20px'],
                    ],
                    'button-skip' => [
                        'type' => 'ButtonBlock',
                        'props' => [
                            'text' => 'Tidak, terima kasih. Teruskan ke dashboard →',
                            'url' => '/dashboard',
                            'variant' => 'outline',
                            'size' => 'medium',
                            'fullWidth' => false,
                        ],
                    ],
                    'spacer-5' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '40px'],
                    ],
                    'divider-1' => [
                        'type' => 'Divider',
                        'props' => ['style' => 'solid', 'color' => '#e5e7eb'],
                    ],
                    'spacer-6' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'text-referral' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center; padding: 30px; background: linear-gradient(135deg, #fdf4ff 0%, #fae8ff 100%); border-radius: 16px;"><h3 style="margin: 0 0 15px 0; font-size: 24px; color: #86198f;">🎁 KONGSI & DAPAT GANJARAN!</h3><p style="margin: 0 0 20px 0; color: #a21caf;">Jemput rakan anda dan dapatkan <strong>RM50 kredit</strong> untuk setiap rujukan yang berjaya!</p><div style="display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;"><a href="#" style="padding: 10px 20px; background: #1877f2; color: white; border-radius: 8px; text-decoration: none;">📘 Facebook</a><a href="#" style="padding: 10px 20px; background: #25d366; color: white; border-radius: 8px; text-decoration: none;">📱 WhatsApp</a><a href="#" style="padding: 10px 20px; background: #1da1f2; color: white; border-radius: 8px; text-decoration: none;">🐦 Twitter</a></div></div>',
                            'alignment' => 'center',
                        ],
                    ],
                ],
                'root' => ['props' => ['title' => $name]],
            ],

            // ========================================
            // TEMPLATE 3: Terima Kasih Komuniti (Community)
            // Welcome to community with engagement elements
            // ========================================
            'thankyou-komuniti' => [
                'content' => [
                    'hero-1' => [
                        'type' => 'HeroSection',
                        'props' => [
                            'headline' => '🎊 SELAMAT DATANG KE KELUARGA KAMI!',
                            'subheadline' => 'Anda kini sebahagian daripada komuniti 5,000+ ahli yang berjaya!',
                            'alignment' => 'center',
                        ],
                    ],
                    'spacer-1' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '20px'],
                    ],
                    'text-welcome' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center; padding: 30px; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 20px;"><p style="margin: 0; font-size: 20px; color: #1e40af;"><strong>✅ Tahniah atas keputusan bijak anda!</strong></p><p style="margin: 15px 0 0 0; font-size: 16px; color: #1e40af;">Anda baru sahaja melabur dalam diri sendiri - itulah langkah pertama menuju kejayaan!</p></div>',
                            'alignment' => 'center',
                        ],
                    ],
                    'spacer-2' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'video-welcome' => [
                        'type' => 'VideoBlock',
                        'props' => [
                            'videoUrl' => '',
                            'autoplay' => false,
                            'muted' => false,
                        ],
                    ],
                    'text-video-caption' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<p style="text-align: center; color: #64748b; font-style: italic;">👆 Tonton video alu-aluan khas daripada pengasas kami</p>',
                            'alignment' => 'center',
                        ],
                    ],
                    'spacer-3' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'features-community' => [
                        'type' => 'FeaturesGrid',
                        'props' => [
                            'columns' => 3,
                            'features' => [
                                [
                                    'icon' => '👥',
                                    'title' => 'Sertai Komuniti',
                                    'description' => 'Akses group Facebook eksklusif untuk sokongan & networking',
                                ],
                                [
                                    'icon' => '📱',
                                    'title' => 'Group WhatsApp',
                                    'description' => 'Dapatkan tips harian & motivasi terus ke telefon anda',
                                ],
                                [
                                    'icon' => '🎓',
                                    'title' => 'Mula Belajar',
                                    'description' => 'Akses portal pembelajaran dengan 50+ video tutorial',
                                ],
                            ],
                        ],
                    ],
                    'spacer-4' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'text-steps' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="padding: 30px; background: #fffbeb; border-radius: 16px; border-left: 4px solid #f59e0b;"><h3 style="margin: 0 0 20px 0; color: #92400e;">📋 3 LANGKAH UNTUK BERMULA:</h3><div style="color: #78350f;"><p style="margin: 0 0 15px 0;"><strong>Langkah 1:</strong> Semak email anda untuk butiran login</p><p style="margin: 0 0 15px 0;"><strong>Langkah 2:</strong> Sertai group komuniti (link dalam email)</p><p style="margin: 0;"><strong>Langkah 3:</strong> Tonton video pengenalan di portal</p></div></div>',
                            'alignment' => 'left',
                        ],
                    ],
                    'spacer-5' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'button-community' => [
                        'type' => 'ButtonBlock',
                        'props' => [
                            'text' => '👥 Sertai Group Komuniti',
                            'url' => '#',
                            'variant' => 'primary',
                            'size' => 'large',
                            'fullWidth' => true,
                        ],
                    ],
                    'spacer-6' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '15px'],
                    ],
                    'button-portal' => [
                        'type' => 'ButtonBlock',
                        'props' => [
                            'text' => '🎓 Akses Portal Pembelajaran',
                            'url' => '/dashboard',
                            'variant' => 'success',
                            'size' => 'large',
                            'fullWidth' => true,
                        ],
                    ],
                    'spacer-7' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '40px'],
                    ],
                    'divider-1' => [
                        'type' => 'Divider',
                        'props' => ['style' => 'solid', 'color' => '#e5e7eb'],
                    ],
                    'spacer-8' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'text-share' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center; padding: 30px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-radius: 16px;"><h3 style="margin: 0 0 15px 0; color: #166534;">🎁 KONGSI KEJAYAAN ANDA!</h3><p style="margin: 0 0 20px 0; color: #15803d;">Ceritakan kepada rakan anda & dapatkan <strong>BONUS EKSKLUSIF</strong>!</p><div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;"><a href="#" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: #1877f2; color: white; border-radius: 10px; text-decoration: none; font-weight: bold;">📘 Kongsi di Facebook</a><a href="#" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: #25d366; color: white; border-radius: 10px; text-decoration: none; font-weight: bold;">📱 Kongsi di WhatsApp</a></div></div>',
                            'alignment' => 'center',
                        ],
                    ],
                    'spacer-9' => [
                        'type' => 'Spacer',
                        'props' => ['height' => '30px'],
                    ],
                    'text-survey' => [
                        'type' => 'TextBlock',
                        'props' => [
                            'content' => '<div style="text-align: center; padding: 25px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;"><p style="margin: 0 0 10px 0; color: #475569;"><strong>💭 Kami ingin dengar pendapat anda!</strong></p><p style="margin: 0; color: #64748b;">Bagaimana pengalaman pembelian anda? <a href="#" style="color: #3b82f6;">Isi survey ringkas (30 saat)</a></p></div>',
                            'alignment' => 'center',
                        ],
                    ],
                ],
                'root' => ['props' => ['title' => $name]],
            ],
        ];

        return $templates[$variant] ?? $templates['thankyou-ringkas'];
    }

    /**
     * Convert object-based content structure to Puck's array format.
     * Puck expects content as an array of components, each with a unique ID.
     */
    protected function convertToPuckFormat(array $templateContent): array
    {
        $contentArray = [];

        if (isset($templateContent['content']) && is_array($templateContent['content'])) {
            foreach ($templateContent['content'] as $key => $component) {
                // Generate unique ID for each component
                $uniqueId = $component['type'].'-'.Str::uuid()->toString();

                $contentArray[] = [
                    'type' => $component['type'],
                    'props' => array_merge($component['props'], ['id' => $uniqueId]),
                ];
            }
        }

        return [
            'content' => $contentArray,
            'root' => [
                'props' => (object) [], // Cast to object so JSON encodes as {} not []
            ],
        ];
    }
}

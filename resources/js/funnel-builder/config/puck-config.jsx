/**
 * Puck Editor Configuration
 * Defines all available components for the funnel builder
 */

import React, { useState } from 'react';
import MediaManager from '../components/MediaManager';

/**
 * Custom Image Field with Media Manager
 */
const ImageField = ({ value, onChange, field }) => {
    const [isOpen, setIsOpen] = useState(false);

    const handleSelect = (image) => {
        onChange(image?.url || '');
    };

    const handleClear = () => {
        onChange('');
    };

    return (
        <div className="mb-4">
            <label className="block text-xs font-medium text-gray-500 mb-1">
                {field.label}
            </label>

            {value ? (
                <div className="relative group">
                    <img
                        src={value}
                        alt="Selected"
                        className="w-full h-24 object-cover rounded border bg-gray-100"
                    />
                    <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded flex items-center justify-center gap-2">
                        <button
                            type="button"
                            onClick={() => setIsOpen(true)}
                            className="px-2 py-1 bg-white text-gray-700 text-xs rounded hover:bg-gray-100"
                        >
                            Change
                        </button>
                        <button
                            type="button"
                            onClick={handleClear}
                            className="px-2 py-1 bg-red-500 text-white text-xs rounded hover:bg-red-600"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            ) : (
                <button
                    type="button"
                    onClick={() => setIsOpen(true)}
                    className="w-full h-24 border-2 border-dashed border-gray-300 rounded flex flex-col items-center justify-center text-gray-400 hover:border-gray-400 hover:text-gray-500 transition-colors"
                >
                    <svg className="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span className="text-xs">Select Image</span>
                </button>
            )}

            <MediaManager
                isOpen={isOpen}
                onClose={() => setIsOpen(false)}
                onSelect={handleSelect}
            />
        </div>
    );
};

/**
 * Hero Section Component
 */
const HeroSection = ({ headline, subheadline, ctaText, ctaUrl, backgroundImage, alignment }) => (
    <section
        className={`relative py-20 px-6 ${alignment === 'center' ? 'text-center' : alignment === 'right' ? 'text-right' : 'text-left'}`}
        style={{
            backgroundImage: backgroundImage ? `url(${backgroundImage})` : undefined,
            backgroundSize: 'cover',
            backgroundPosition: 'center',
        }}
    >
        {backgroundImage && <div className="absolute inset-0 bg-black/50" />}
        <div className="relative max-w-4xl mx-auto">
            <h1 className="text-4xl md:text-6xl font-bold text-white mb-6">{headline}</h1>
            {subheadline && <p className="text-xl md:text-2xl text-gray-200 mb-8">{subheadline}</p>}
            {ctaText && (
                <a
                    href={ctaUrl || '#'}
                    className="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-8 rounded-lg text-lg transition-colors"
                >
                    {ctaText}
                </a>
            )}
        </div>
    </section>
);

/**
 * Text Block Component
 */
const TextBlock = ({ content, alignment }) => (
    <div
        className={`py-8 px-6 max-w-4xl mx-auto ${alignment === 'center' ? 'text-center' : alignment === 'right' ? 'text-right' : 'text-left'}`}
        dangerouslySetInnerHTML={{ __html: content }}
    />
);

/**
 * Image Component
 */
const ImageBlock = ({ src, alt, caption, maxWidth }) => (
    <figure className="py-8 px-6" style={{ maxWidth: maxWidth || '100%', margin: '0 auto' }}>
        <img src={src} alt={alt} className="w-full rounded-lg shadow-lg" />
        {caption && <figcaption className="text-center text-gray-600 mt-4">{caption}</figcaption>}
    </figure>
);

/**
 * Video Component
 */
const VideoBlock = ({ videoUrl, autoplay, muted }) => {
    // Handle YouTube URLs
    const getEmbedUrl = (url) => {
        if (!url) return null;
        if (url.includes('youtube.com') || url.includes('youtu.be')) {
            const videoId = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/)?.[1];
            if (videoId) {
                return `https://www.youtube.com/embed/${videoId}${autoplay ? '?autoplay=1' : ''}${muted ? '&mute=1' : ''}`;
            }
        }
        if (url.includes('vimeo.com')) {
            const videoId = url.match(/vimeo\.com\/(\d+)/)?.[1];
            if (videoId) {
                return `https://player.vimeo.com/video/${videoId}${autoplay ? '?autoplay=1' : ''}${muted ? '&muted=1' : ''}`;
            }
        }
        return url;
    };

    const embedUrl = getEmbedUrl(videoUrl);

    return (
        <div className="py-8 px-6 max-w-4xl mx-auto">
            <div className="relative pb-[56.25%] h-0 overflow-hidden rounded-lg shadow-lg">
                <iframe
                    src={embedUrl}
                    className="absolute top-0 left-0 w-full h-full"
                    frameBorder="0"
                    allowFullScreen
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                />
            </div>
        </div>
    );
};

/**
 * Button Component
 */
const ButtonBlock = ({ text, url, variant, size, fullWidth }) => {
    const variants = {
        primary: 'bg-blue-600 hover:bg-blue-700 text-white',
        secondary: 'bg-gray-600 hover:bg-gray-700 text-white',
        success: 'bg-green-600 hover:bg-green-700 text-white',
        danger: 'bg-red-600 hover:bg-red-700 text-white',
        outline: 'border-2 border-blue-600 text-blue-600 hover:bg-blue-600 hover:text-white',
    };

    const sizes = {
        small: 'py-2 px-4 text-sm',
        medium: 'py-3 px-6 text-base',
        large: 'py-4 px-8 text-lg',
    };

    return (
        <div className={`py-4 px-6 ${fullWidth ? '' : 'text-center'}`}>
            <a
                href={url || '#'}
                className={`inline-block font-bold rounded-lg transition-colors ${variants[variant] || variants.primary} ${sizes[size] || sizes.medium} ${fullWidth ? 'w-full text-center' : ''}`}
            >
                {text}
            </a>
        </div>
    );
};

/**
 * Testimonial Component
 */
const TestimonialBlock = ({ quote, author, role, avatar }) => (
    <div className="py-8 px-6 max-w-2xl mx-auto">
        <blockquote className="bg-white rounded-xl shadow-lg p-8">
            <p className="text-xl text-gray-700 italic mb-6">"{quote}"</p>
            <div className="flex items-center">
                {avatar && (
                    <img src={avatar} alt={author} className="w-12 h-12 rounded-full mr-4" />
                )}
                <div>
                    <p className="font-bold text-gray-900">{author}</p>
                    {role && <p className="text-gray-600 text-sm">{role}</p>}
                </div>
            </div>
        </blockquote>
    </div>
);

/**
 * Features Grid Component
 */
const FeaturesGrid = ({ features, columns }) => (
    <div className="py-12 px-6 max-w-6xl mx-auto">
        <div className={`grid gap-8 ${columns === 2 ? 'md:grid-cols-2' : columns === 4 ? 'md:grid-cols-4' : 'md:grid-cols-3'}`}>
            {features?.map((feature, index) => (
                <div key={index} className="text-center p-6">
                    {feature.icon && (
                        <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <span className="text-2xl">{feature.icon}</span>
                        </div>
                    )}
                    <h3 className="text-xl font-bold mb-2">{feature.title}</h3>
                    <p className="text-gray-600">{feature.description}</p>
                </div>
            ))}
        </div>
    </div>
);

/**
 * Pricing Card Component
 */
const PricingCard = ({ title, price, originalPrice, period, features, ctaText, ctaUrl, highlighted }) => (
    <div className={`py-8 px-6 ${highlighted ? 'scale-105' : ''}`}>
        <div className={`rounded-2xl p-8 ${highlighted ? 'bg-blue-600 text-white shadow-2xl' : 'bg-white shadow-lg'}`}>
            <h3 className="text-2xl font-bold mb-4">{title}</h3>
            <div className="mb-6">
                {originalPrice && (
                    <span className={`text-lg line-through ${highlighted ? 'text-blue-200' : 'text-gray-400'}`}>
                        RM {originalPrice}
                    </span>
                )}
                <div className="flex items-baseline">
                    <span className="text-4xl font-bold">RM {price}</span>
                    {period && <span className={`ml-2 ${highlighted ? 'text-blue-200' : 'text-gray-500'}`}>/{period}</span>}
                </div>
            </div>
            <ul className="mb-8 space-y-3">
                {features?.map((feature, index) => (
                    <li key={index} className="flex items-center">
                        <svg className={`w-5 h-5 mr-3 ${highlighted ? 'text-blue-200' : 'text-green-500'}`} fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                        </svg>
                        {feature}
                    </li>
                ))}
            </ul>
            <a
                href={ctaUrl || '#'}
                className={`block text-center py-3 px-6 rounded-lg font-bold transition-colors ${highlighted ? 'bg-white text-blue-600 hover:bg-gray-100' : 'bg-blue-600 text-white hover:bg-blue-700'}`}
            >
                {ctaText}
            </a>
        </div>
    </div>
);

/**
 * Countdown Timer Component
 */
const CountdownTimer = ({ endDate, expiredMessage }) => {
    const [timeLeft, setTimeLeft] = React.useState({ days: 0, hours: 0, minutes: 0, seconds: 0 });
    const [expired, setExpired] = React.useState(false);

    React.useEffect(() => {
        const timer = setInterval(() => {
            const now = new Date().getTime();
            const end = new Date(endDate).getTime();
            const distance = end - now;

            if (distance < 0) {
                setExpired(true);
                clearInterval(timer);
                return;
            }

            setTimeLeft({
                days: Math.floor(distance / (1000 * 60 * 60 * 24)),
                hours: Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)),
                minutes: Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60)),
                seconds: Math.floor((distance % (1000 * 60)) / 1000),
            });
        }, 1000);

        return () => clearInterval(timer);
    }, [endDate]);

    if (expired) {
        return <div className="text-center py-8 text-xl text-red-600">{expiredMessage || 'Offer expired!'}</div>;
    }

    return (
        <div className="py-8 px-6">
            <div className="flex justify-center gap-4">
                {[
                    { value: timeLeft.days, label: 'Days' },
                    { value: timeLeft.hours, label: 'Hours' },
                    { value: timeLeft.minutes, label: 'Minutes' },
                    { value: timeLeft.seconds, label: 'Seconds' },
                ].map((item, index) => (
                    <div key={index} className="text-center">
                        <div className="bg-gray-900 text-white rounded-lg p-4 min-w-[80px]">
                            <span className="text-3xl font-bold">{String(item.value).padStart(2, '0')}</span>
                        </div>
                        <span className="text-sm text-gray-600 mt-2 block">{item.label}</span>
                    </div>
                ))}
            </div>
        </div>
    );
};

/**
 * FAQ Accordion Component
 */
const FaqAccordion = ({ items }) => {
    const [openIndex, setOpenIndex] = React.useState(null);

    return (
        <div className="py-8 px-6 max-w-3xl mx-auto">
            <div className="space-y-4">
                {items?.map((item, index) => (
                    <div key={index} className="border rounded-lg">
                        <button
                            onClick={() => setOpenIndex(openIndex === index ? null : index)}
                            className="w-full px-6 py-4 text-left flex justify-between items-center"
                        >
                            <span className="font-semibold">{item.question}</span>
                            <svg
                                className={`w-5 h-5 transition-transform ${openIndex === index ? 'rotate-180' : ''}`}
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        {openIndex === index && (
                            <div className="px-6 pb-4 text-gray-600">{item.answer}</div>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
};

/**
 * Opt-in Form Component
 */
const OptinForm = ({ headline, description, buttonText, placeholderName, placeholderEmail, showName }) => (
    <div className="py-12 px-6 bg-gray-100">
        <div className="max-w-md mx-auto text-center">
            {headline && <h3 className="text-2xl font-bold mb-4">{headline}</h3>}
            {description && <p className="text-gray-600 mb-6">{description}</p>}
            <form className="space-y-4" onSubmit={(e) => e.preventDefault()}>
                {showName && (
                    <input
                        type="text"
                        placeholder={placeholderName || 'Your Name'}
                        className="w-full px-4 py-3 rounded-lg border focus:ring-2 focus:ring-blue-500 outline-none"
                    />
                )}
                <input
                    type="email"
                    placeholder={placeholderEmail || 'Your Email'}
                    className="w-full px-4 py-3 rounded-lg border focus:ring-2 focus:ring-blue-500 outline-none"
                />
                <button
                    type="submit"
                    className="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition-colors"
                >
                    {buttonText || 'Subscribe'}
                </button>
            </form>
        </div>
    </div>
);

/**
 * Spacer Component
 */
const Spacer = ({ height }) => <div style={{ height: height || '40px' }} />;

/**
 * Divider Component
 */
const Divider = ({ style, color }) => (
    <div className="py-4 px-6">
        <hr
            className={`${style === 'dashed' ? 'border-dashed' : style === 'dotted' ? 'border-dotted' : ''}`}
            style={{ borderColor: color || '#e5e7eb' }}
        />
    </div>
);

/**
 * Columns Component
 */
const Columns = ({ columns, gap, children }) => (
    <div
        className={`grid gap-${gap || 4}`}
        style={{
            gridTemplateColumns: `repeat(${columns || 2}, 1fr)`,
        }}
    >
        {children}
    </div>
);

/**
 * Container Component
 */
const Container = ({ maxWidth, padding, backgroundColor, children }) => (
    <div
        style={{
            maxWidth: maxWidth || '1200px',
            padding: padding || '20px',
            backgroundColor: backgroundColor || 'transparent',
            margin: '0 auto',
        }}
    >
        {children}
    </div>
);

/**
 * Product Card Component (for checkout pages)
 * Displays product information with pricing in the funnel
 */
const ProductCard = ({ productId, layout, showImage, showDescription, customTitle, customDescription, customPrice, customComparePrice }) => {
    // In editor, show a preview representation
    const isEditor = typeof window !== 'undefined' && window.location.pathname.includes('/editor');

    return (
        <div
            className={`border rounded-xl p-6 bg-white shadow-sm ${layout === 'horizontal' ? 'flex gap-6' : ''}`}
            data-product-id={productId}
            data-funnel-product
        >
            {showImage && (
                <div className={`${layout === 'horizontal' ? 'w-1/3 flex-shrink-0' : 'mb-4'}`}>
                    <div className="aspect-square bg-gradient-to-br from-gray-100 to-gray-200 rounded-lg flex items-center justify-center">
                        <svg className="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
            )}
            <div className="flex-1">
                <h3 className="text-xl font-bold text-gray-900 mb-2">
                    {customTitle || 'Product Name'}
                </h3>
                {showDescription && (
                    <p className="text-gray-600 mb-4 text-sm">
                        {customDescription || 'Product description will appear here. This is where you can highlight the key benefits and features of your product.'}
                    </p>
                )}
                <div className="flex items-baseline gap-3">
                    <span className="text-2xl font-bold text-blue-600">
                        RM {customPrice || '99.00'}
                    </span>
                    {customComparePrice && (
                        <span className="text-lg text-gray-400 line-through">
                            RM {customComparePrice}
                        </span>
                    )}
                </div>
                {productId ? (
                    <p className="text-xs text-gray-400 mt-3 flex items-center gap-1">
                        <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                        </svg>
                        Product ID: {productId} (Loaded from database)
                    </p>
                ) : (
                    <p className="text-xs text-orange-500 mt-3 flex items-center gap-1">
                        <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                        </svg>
                        Select a product in the Products tab
                    </p>
                )}
            </div>
        </div>
    );
};

/**
 * Checkout Form Component
 */
const CheckoutForm = ({ showBillingAddress, showShippingAddress }) => (
    <div className="border rounded-lg p-6 bg-white shadow-sm">
        <h3 className="text-xl font-bold mb-4">Checkout Form</h3>
        <p className="text-gray-500">[Checkout form will be rendered here]</p>
        <ul className="text-sm text-gray-400 mt-4 space-y-1">
            <li>• Payment details</li>
            {showBillingAddress && <li>• Billing address</li>}
            {showShippingAddress && <li>• Shipping address</li>}
        </ul>
    </div>
);

/**
 * Order Bump Component
 * Checkbox-style offer displayed at checkout
 */
const OrderBump = ({ bumpId, headline, description, price, comparePrice, checkboxLabel, highlightColor }) => {
    const [checked, setChecked] = React.useState(false);
    const bgColor = highlightColor || '#fef3c7'; // Default yellow
    const borderColor = highlightColor ? highlightColor.replace('f', 'd') : '#fcd34d';

    return (
        <div
            className="rounded-xl overflow-hidden shadow-sm transition-all duration-200"
            style={{
                backgroundColor: checked ? bgColor : 'white',
                border: `2px solid ${checked ? borderColor : '#e5e7eb'}`,
            }}
            data-bump-id={bumpId}
            data-funnel-order-bump
        >
            {/* Header strip */}
            <div className="bg-gradient-to-r from-yellow-400 to-orange-400 px-4 py-2">
                <div className="flex items-center gap-2 text-white font-bold text-sm">
                    <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                    SPECIAL ONE-TIME OFFER
                </div>
            </div>

            {/* Content */}
            <div className="p-4">
                <label className="flex items-start gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        checked={checked}
                        onChange={(e) => setChecked(e.target.checked)}
                        className="mt-1 w-5 h-5 rounded border-2 border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    <div className="flex-1">
                        <p className="font-bold text-gray-900">
                            {checkboxLabel || 'Yes! Add this to my order'}
                        </p>
                        <h4 className="text-lg font-semibold text-gray-800 mt-2">
                            {headline || 'Bonus Product Name'}
                        </h4>
                        <p className="text-gray-600 text-sm mt-1">
                            {description || 'Get this exclusive add-on at a special discounted price only available with your purchase today!'}
                        </p>
                        <div className="flex items-baseline gap-2 mt-3">
                            <span className="text-xl font-bold text-green-600">
                                RM {price || '29.00'}
                            </span>
                            {comparePrice && (
                                <>
                                    <span className="text-gray-400 line-through text-sm">
                                        RM {comparePrice}
                                    </span>
                                    <span className="bg-red-100 text-red-600 text-xs font-bold px-2 py-0.5 rounded">
                                        SAVE {Math.round((1 - (parseFloat(price || 29) / parseFloat(comparePrice))) * 100)}%
                                    </span>
                                </>
                            )}
                        </div>
                    </div>
                </label>
            </div>

            {bumpId ? (
                <div className="px-4 pb-3 text-xs text-gray-400 flex items-center gap-1">
                    <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                    </svg>
                    Bump ID: {bumpId} (Loaded from database)
                </div>
            ) : (
                <div className="px-4 pb-3 text-xs text-orange-500 flex items-center gap-1">
                    <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                    </svg>
                    Configure in the Products tab
                </div>
            )}
        </div>
    );
};

/**
 * Puck Configuration
 */
export const puckConfig = {
    categories: {
        layout: {
            title: 'Layout',
            components: ['Container', 'Columns', 'Spacer', 'Divider'],
        },
        content: {
            title: 'Content',
            components: ['HeroSection', 'TextBlock', 'ImageBlock', 'VideoBlock', 'ButtonBlock'],
        },
        marketing: {
            title: 'Marketing',
            components: ['TestimonialBlock', 'FeaturesGrid', 'PricingCard', 'CountdownTimer', 'FaqAccordion'],
        },
        conversion: {
            title: 'Conversion',
            components: ['OptinForm', 'ProductCard', 'CheckoutForm', 'OrderBump'],
        },
    },
    components: {
        HeroSection: {
            label: 'Hero Section',
            fields: {
                headline: { type: 'text', label: 'Headline' },
                subheadline: { type: 'textarea', label: 'Subheadline' },
                ctaText: { type: 'text', label: 'CTA Button Text' },
                ctaUrl: { type: 'text', label: 'CTA Button URL' },
                backgroundImage: {
                    type: 'custom',
                    label: 'Background Image',
                    render: ImageField,
                },
                alignment: {
                    type: 'select',
                    label: 'Alignment',
                    options: [
                        { label: 'Left', value: 'left' },
                        { label: 'Center', value: 'center' },
                        { label: 'Right', value: 'right' },
                    ],
                },
            },
            defaultProps: {
                headline: 'Your Amazing Headline Here',
                subheadline: 'A compelling subheadline that explains your value proposition',
                ctaText: 'Get Started Now',
                ctaUrl: '#',
                alignment: 'center',
            },
            render: HeroSection,
        },
        TextBlock: {
            label: 'Text Block',
            fields: {
                content: { type: 'textarea', label: 'Content (HTML)' },
                alignment: {
                    type: 'select',
                    label: 'Alignment',
                    options: [
                        { label: 'Left', value: 'left' },
                        { label: 'Center', value: 'center' },
                        { label: 'Right', value: 'right' },
                    ],
                },
            },
            defaultProps: {
                content: '<p>Enter your text content here. You can use HTML for formatting.</p>',
                alignment: 'left',
            },
            render: TextBlock,
        },
        ImageBlock: {
            label: 'Image',
            fields: {
                src: {
                    type: 'custom',
                    label: 'Image',
                    render: ImageField,
                },
                alt: { type: 'text', label: 'Alt Text' },
                caption: { type: 'text', label: 'Caption' },
                maxWidth: { type: 'text', label: 'Max Width (e.g., 600px)' },
            },
            defaultProps: {
                src: 'https://via.placeholder.com/800x400',
                alt: 'Image description',
            },
            render: ImageBlock,
        },
        VideoBlock: {
            label: 'Video',
            fields: {
                videoUrl: { type: 'text', label: 'Video URL (YouTube/Vimeo)' },
                autoplay: { type: 'radio', label: 'Autoplay', options: [{ label: 'Yes', value: true }, { label: 'No', value: false }] },
                muted: { type: 'radio', label: 'Muted', options: [{ label: 'Yes', value: true }, { label: 'No', value: false }] },
            },
            defaultProps: {
                videoUrl: '',
                autoplay: false,
                muted: false,
            },
            render: VideoBlock,
        },
        ButtonBlock: {
            label: 'Button',
            fields: {
                text: { type: 'text', label: 'Button Text' },
                url: { type: 'text', label: 'URL' },
                variant: {
                    type: 'select',
                    label: 'Style',
                    options: [
                        { label: 'Primary', value: 'primary' },
                        { label: 'Secondary', value: 'secondary' },
                        { label: 'Success', value: 'success' },
                        { label: 'Danger', value: 'danger' },
                        { label: 'Outline', value: 'outline' },
                    ],
                },
                size: {
                    type: 'select',
                    label: 'Size',
                    options: [
                        { label: 'Small', value: 'small' },
                        { label: 'Medium', value: 'medium' },
                        { label: 'Large', value: 'large' },
                    ],
                },
                fullWidth: { type: 'radio', label: 'Full Width', options: [{ label: 'Yes', value: true }, { label: 'No', value: false }] },
            },
            defaultProps: {
                text: 'Click Here',
                url: '#',
                variant: 'primary',
                size: 'medium',
                fullWidth: false,
            },
            render: ButtonBlock,
        },
        TestimonialBlock: {
            label: 'Testimonial',
            fields: {
                quote: { type: 'textarea', label: 'Quote' },
                author: { type: 'text', label: 'Author Name' },
                role: { type: 'text', label: 'Role/Title' },
                avatar: {
                    type: 'custom',
                    label: 'Avatar',
                    render: ImageField,
                },
            },
            defaultProps: {
                quote: 'This product changed my life! Highly recommended.',
                author: 'John Doe',
                role: 'CEO, Company',
            },
            render: TestimonialBlock,
        },
        FeaturesGrid: {
            label: 'Features Grid',
            fields: {
                columns: {
                    type: 'select',
                    label: 'Columns',
                    options: [
                        { label: '2 Columns', value: 2 },
                        { label: '3 Columns', value: 3 },
                        { label: '4 Columns', value: 4 },
                    ],
                },
                features: {
                    type: 'array',
                    label: 'Features',
                    arrayFields: {
                        icon: { type: 'text', label: 'Icon (emoji)' },
                        title: { type: 'text', label: 'Title' },
                        description: { type: 'textarea', label: 'Description' },
                    },
                },
            },
            defaultProps: {
                columns: 3,
                features: [
                    { icon: '🚀', title: 'Fast', description: 'Lightning fast performance' },
                    { icon: '🔒', title: 'Secure', description: 'Bank-level security' },
                    { icon: '💡', title: 'Easy', description: 'Simple to use' },
                ],
            },
            render: FeaturesGrid,
        },
        PricingCard: {
            label: 'Pricing Card',
            fields: {
                title: { type: 'text', label: 'Plan Title' },
                price: { type: 'text', label: 'Price' },
                originalPrice: { type: 'text', label: 'Original Price (optional)' },
                period: { type: 'text', label: 'Period (e.g., month)' },
                features: {
                    type: 'array',
                    label: 'Features',
                    arrayFields: {
                        feature: { type: 'text', label: 'Feature' },
                    },
                },
                ctaText: { type: 'text', label: 'CTA Text' },
                ctaUrl: { type: 'text', label: 'CTA URL' },
                highlighted: { type: 'radio', label: 'Highlighted', options: [{ label: 'Yes', value: true }, { label: 'No', value: false }] },
            },
            defaultProps: {
                title: 'Pro Plan',
                price: '99',
                period: 'month',
                features: ['Feature 1', 'Feature 2', 'Feature 3'],
                ctaText: 'Get Started',
                ctaUrl: '#',
                highlighted: false,
            },
            render: PricingCard,
        },
        CountdownTimer: {
            label: 'Countdown Timer',
            fields: {
                endDate: { type: 'text', label: 'End Date (YYYY-MM-DD HH:mm)' },
                expiredMessage: { type: 'text', label: 'Expired Message' },
            },
            defaultProps: {
                endDate: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 16),
                expiredMessage: 'This offer has expired!',
            },
            render: CountdownTimer,
        },
        FaqAccordion: {
            label: 'FAQ Accordion',
            fields: {
                items: {
                    type: 'array',
                    label: 'FAQ Items',
                    arrayFields: {
                        question: { type: 'text', label: 'Question' },
                        answer: { type: 'textarea', label: 'Answer' },
                    },
                },
            },
            defaultProps: {
                items: [
                    { question: 'What is your refund policy?', answer: 'We offer a 30-day money-back guarantee.' },
                    { question: 'How long does shipping take?', answer: 'Shipping typically takes 3-5 business days.' },
                ],
            },
            render: FaqAccordion,
        },
        OptinForm: {
            label: 'Opt-in Form',
            fields: {
                headline: { type: 'text', label: 'Headline' },
                description: { type: 'textarea', label: 'Description' },
                buttonText: { type: 'text', label: 'Button Text' },
                placeholderName: { type: 'text', label: 'Name Placeholder' },
                placeholderEmail: { type: 'text', label: 'Email Placeholder' },
                showName: { type: 'radio', label: 'Show Name Field', options: [{ label: 'Yes', value: true }, { label: 'No', value: false }] },
            },
            defaultProps: {
                headline: 'Get Your Free Guide',
                description: 'Enter your email to receive instant access.',
                buttonText: 'Download Now',
                showName: true,
            },
            render: OptinForm,
        },
        Container: {
            label: 'Container',
            fields: {
                maxWidth: { type: 'text', label: 'Max Width' },
                padding: { type: 'text', label: 'Padding' },
                backgroundColor: { type: 'text', label: 'Background Color' },
            },
            defaultProps: {
                maxWidth: '1200px',
                padding: '20px',
            },
            render: ({ children, ...props }) => <Container {...props}>{children}</Container>,
        },
        Columns: {
            label: 'Columns',
            fields: {
                columns: {
                    type: 'select',
                    label: 'Columns',
                    options: [
                        { label: '2 Columns', value: 2 },
                        { label: '3 Columns', value: 3 },
                        { label: '4 Columns', value: 4 },
                    ],
                },
                gap: {
                    type: 'select',
                    label: 'Gap',
                    options: [
                        { label: 'Small', value: 2 },
                        { label: 'Medium', value: 4 },
                        { label: 'Large', value: 8 },
                    ],
                },
            },
            defaultProps: {
                columns: 2,
                gap: 4,
            },
            render: ({ children, ...props }) => <Columns {...props}>{children}</Columns>,
        },
        Spacer: {
            label: 'Spacer',
            fields: {
                height: { type: 'text', label: 'Height (e.g., 40px)' },
            },
            defaultProps: {
                height: '40px',
            },
            render: Spacer,
        },
        Divider: {
            label: 'Divider',
            fields: {
                style: {
                    type: 'select',
                    label: 'Style',
                    options: [
                        { label: 'Solid', value: 'solid' },
                        { label: 'Dashed', value: 'dashed' },
                        { label: 'Dotted', value: 'dotted' },
                    ],
                },
                color: { type: 'text', label: 'Color' },
            },
            defaultProps: {
                style: 'solid',
                color: '#e5e7eb',
            },
            render: Divider,
        },
        ProductCard: {
            label: 'Product Card',
            fields: {
                productId: { type: 'text', label: 'Product ID (from Products tab)' },
                layout: {
                    type: 'select',
                    label: 'Layout',
                    options: [
                        { label: 'Vertical', value: 'vertical' },
                        { label: 'Horizontal', value: 'horizontal' },
                    ],
                },
                showImage: { type: 'radio', label: 'Show Image', options: [{ label: 'Yes', value: true }, { label: 'No', value: false }] },
                showDescription: { type: 'radio', label: 'Show Description', options: [{ label: 'Yes', value: true }, { label: 'No', value: false }] },
                customTitle: { type: 'text', label: 'Custom Title (overrides product title)' },
                customDescription: { type: 'textarea', label: 'Custom Description' },
                customPrice: { type: 'text', label: 'Display Price' },
                customComparePrice: { type: 'text', label: 'Compare-at Price (strikethrough)' },
            },
            defaultProps: {
                productId: '',
                layout: 'vertical',
                showImage: true,
                showDescription: true,
                customTitle: '',
                customDescription: '',
                customPrice: '99.00',
                customComparePrice: '',
            },
            render: ProductCard,
        },
        CheckoutForm: {
            label: 'Checkout Form',
            fields: {
                showBillingAddress: { type: 'radio', label: 'Show Billing Address', options: [{ label: 'Yes', value: true }, { label: 'No', value: false }] },
                showShippingAddress: { type: 'radio', label: 'Show Shipping Address', options: [{ label: 'Yes', value: true }, { label: 'No', value: false }] },
            },
            defaultProps: {
                showBillingAddress: true,
                showShippingAddress: false,
            },
            render: CheckoutForm,
        },
        OrderBump: {
            label: 'Order Bump',
            fields: {
                bumpId: { type: 'text', label: 'Order Bump ID (from Products tab)' },
                headline: { type: 'text', label: 'Headline' },
                description: { type: 'textarea', label: 'Description' },
                checkboxLabel: { type: 'text', label: 'Checkbox Label' },
                price: { type: 'text', label: 'Display Price' },
                comparePrice: { type: 'text', label: 'Compare-at Price (strikethrough)' },
                highlightColor: { type: 'text', label: 'Highlight Color (hex)' },
            },
            defaultProps: {
                bumpId: '',
                headline: 'Exclusive Bonus Offer',
                description: 'Add this limited-time offer to your order at a special discounted price!',
                checkboxLabel: 'Yes! Add this to my order',
                price: '29.00',
                comparePrice: '59.00',
                highlightColor: '#fef3c7',
            },
            render: OrderBump,
        },
    },
};

export default puckConfig;

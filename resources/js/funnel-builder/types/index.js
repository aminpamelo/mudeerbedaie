/**
 * Funnel Builder Type Definitions
 */

/**
 * @typedef {Object} Funnel
 * @property {number} id
 * @property {string} uuid
 * @property {string} name
 * @property {string} slug
 * @property {string} description
 * @property {'sales'|'lead'|'webinar'|'course'} type
 * @property {'draft'|'published'|'archived'} status
 * @property {Object} settings
 * @property {string} published_at
 * @property {FunnelStep[]} steps
 */

/**
 * @typedef {Object} FunnelStep
 * @property {number} id
 * @property {number} funnel_id
 * @property {string} name
 * @property {string} slug
 * @property {'landing'|'sales'|'checkout'|'upsell'|'downsell'|'thankyou'|'optin'} type
 * @property {number} sort_order
 * @property {boolean} is_active
 * @property {Object} settings
 * @property {number|null} next_step_id
 * @property {number|null} decline_step_id
 * @property {FunnelStepContent} content
 * @property {FunnelProduct[]} products
 */

/**
 * @typedef {Object} FunnelStepContent
 * @property {number} id
 * @property {number} funnel_step_id
 * @property {Object} content - Puck JSON content
 * @property {string} custom_css
 * @property {string} custom_js
 * @property {string} meta_title
 * @property {string} meta_description
 * @property {string} og_image
 * @property {number} version
 * @property {boolean} is_published
 */

/**
 * @typedef {Object} FunnelProduct
 * @property {number} id
 * @property {number} funnel_step_id
 * @property {number|null} product_id
 * @property {number|null} course_id
 * @property {'main'|'bump'|'upsell'|'downsell'} type
 * @property {string} name
 * @property {string} description
 * @property {string} image_url
 * @property {number} funnel_price
 * @property {number} compare_at_price
 * @property {boolean} is_recurring
 * @property {'weekly'|'monthly'|'yearly'} billing_interval
 */

export const STEP_TYPES = {
    landing: { label: 'Landing Page', icon: 'home', color: 'blue' },
    sales: { label: 'Sales Page', icon: 'shopping-bag', color: 'green' },
    checkout: { label: 'Checkout', icon: 'credit-card', color: 'purple' },
    upsell: { label: 'Upsell', icon: 'trending-up', color: 'orange' },
    downsell: { label: 'Downsell', icon: 'trending-down', color: 'yellow' },
    thankyou: { label: 'Thank You', icon: 'check-circle', color: 'teal' },
    optin: { label: 'Opt-in', icon: 'mail', color: 'pink' },
};

export const FUNNEL_TYPES = {
    sales: { label: 'Sales Funnel', description: 'Sell products or services' },
    lead: { label: 'Lead Funnel', description: 'Capture email leads' },
    webinar: { label: 'Webinar Funnel', description: 'Promote webinar registrations' },
    course: { label: 'Course Funnel', description: 'Sell online courses' },
};

export const FUNNEL_STATUS = {
    draft: { label: 'Draft', color: 'gray' },
    published: { label: 'Published', color: 'green' },
    archived: { label: 'Archived', color: 'red' },
};

// Alias for backwards compatibility
export const FUNNEL_STATUSES = FUNNEL_STATUS;

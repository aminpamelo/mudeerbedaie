<?php

/*
|--------------------------------------------------------------------------
| SEO audit strings (admin-facing)
|--------------------------------------------------------------------------
|
| These render inside the admin SEO dashboard and the post editor sidebar,
| which are English-only. There is deliberately no `lang/ms/seo.php`: the
| analyzer caches its rendered report onto `blog_posts.seo_report`, so if
| these resolved per-request-locale a post analysed during a Malay storefront
| request would show Malay text in the English admin. Falling back to `en`
| keeps every cached report consistent.
|
| Every message states the problem AND the fix — a score nobody can act on
| is not worth showing.
|
*/

return [
    // ---------------------------------------------------------------- groups
    'group_basics' => 'Basics',
    'group_keyword' => 'Focus keyword',
    'group_content' => 'Content quality',
    'group_technical' => 'Technical',

    // ---------------------------------------------------------------- grades
    'grade_excellent' => 'Excellent',
    'grade_good' => 'Good',
    'grade_fair' => 'Needs work',
    'grade_poor' => 'Poor',

    // ---------------------------------------------------------------- basics
    'check_title_length' => 'Title length',
    'msg_title_empty' => 'No title set. Google has nothing to show in the results page.',
    'msg_title_short' => 'Title is :count characters — aim for at least :min so it carries enough context.',
    'msg_title_long' => 'Title is :count characters — Google truncates around :max. Trim it so the whole headline shows.',
    'msg_title_ok' => 'Title is :count characters — fits the search result without truncation.',

    'check_meta_description' => 'Meta description',
    'msg_desc_empty' => 'No meta description. Google will scrape a random sentence instead of your pitch — write one.',
    'msg_desc_short' => 'Description is :count characters — expand to at least :min to use the full snippet.',
    'msg_desc_long' => 'Description is :count characters — it clips near :max. Shorten it.',
    'msg_desc_ok' => 'Description is :count characters — uses the snippet well.',

    'check_excerpt' => 'Excerpt',
    'msg_excerpt_ok' => 'Excerpt set — controls how the card reads on the blog index.',
    'msg_excerpt_missing' => 'No excerpt. The blog index will fall back to trimmed body text, which often reads awkwardly.',

    'check_slug' => 'URL slug',
    'msg_slug_empty' => 'No slug set.',
    'msg_slug_long' => 'Slug is over 75 characters — shorten it to the core phrase.',
    'msg_slug_ok' => 'Slug is a clean, readable length.',

    // --------------------------------------------------------------- keyword
    'check_focus_keyword' => 'Focus keyword',
    'msg_keyword_missing' => 'No focus keyword set. Set one and the rest of this checklist can actually grade the article.',
    'msg_keyword_set' => 'Targeting ":keyword".',

    'check_keyword_title' => 'Keyword in title',
    'msg_keyword_title_ok' => 'Focus keyword appears in the title — the strongest on-page signal there is.',
    'msg_keyword_title_missing' => 'Focus keyword is missing from the title. Work it in, ideally near the front.',

    'check_keyword_slug' => 'Keyword in URL',
    'msg_keyword_slug_ok' => 'Focus keyword appears in the URL slug.',
    'msg_keyword_slug_missing' => 'Focus keyword is missing from the slug. Change it before publishing — editing a live URL costs rankings.',

    'check_keyword_description' => 'Keyword in meta description',
    'msg_keyword_desc_ok' => 'Focus keyword appears in the meta description — it gets bolded in search results.',
    'msg_keyword_desc_missing' => 'Focus keyword is missing from the meta description.',

    'check_keyword_intro' => 'Keyword in opening',
    'msg_keyword_intro_ok' => 'Focus keyword appears in the first paragraph.',
    'msg_keyword_intro_missing' => 'Focus keyword is missing from the opening paragraph. Mention it in the first 100 words.',

    'check_keyword_heading' => 'Keyword in a subheading',
    'msg_keyword_heading_ok' => 'Focus keyword appears in at least one subheading.',
    'msg_keyword_heading_missing' => 'No subheading contains the focus keyword. Add it to one H2.',

    'check_keyword_density' => 'Keyword density',
    'msg_density_no_content' => 'No content to measure density against.',
    'msg_density_low' => 'Density is :density% — the keyword barely appears. Use it a few more times naturally.',
    'msg_density_high' => 'Density is :density% — this reads as keyword stuffing and can be penalised. Cut some mentions.',
    'msg_density_ok' => 'Density is :density% — a natural, healthy range.',

    // --------------------------------------------------------------- content
    'check_content_length' => 'Content length',
    'msg_content_thin' => 'Only :count words. Thin content rarely ranks — aim for at least :min.',
    'msg_content_ok_short' => ':count words clears the minimum. Articles over :good words tend to rank noticeably better.',
    'msg_content_ok' => ':count words — substantial enough to compete.',

    'check_headings' => 'Heading structure',
    'msg_headings_none' => 'No H2 subheadings. Readers scan before they read, and headings are what they scan.',
    'msg_headings_one' => 'Only one H2. Break the article into a few more scannable sections.',
    'msg_headings_ok' => ':count H2 sections — well structured for scanning.',

    'check_featured_image' => 'Featured image',
    'msg_featured_ok' => 'Featured image set — used on the blog index and when the link is shared.',
    'msg_featured_missing' => 'No featured image. The index card and every social share will look empty.',

    'check_image_alt' => 'Image alt text',
    'msg_images_none' => 'No images in the body. Visuals lift time-on-page considerably.',
    'msg_images_no_alt' => ':count image(s) have no alt text — invisible to screen readers and to image search.',
    'msg_images_ok' => 'All :count image(s) have alt text.',

    'check_internal_links' => 'Internal links',
    'msg_internal_ok' => ':count internal link(s) — spreads authority to your own pages.',
    'msg_internal_missing' => 'No internal links. Link to a product or a related article to keep readers on site.',

    'check_external_links' => 'External links',
    'msg_external_ok' => ':count outbound link(s) — citing sources reads as credible.',
    'msg_external_missing' => 'No outbound links. Citing a reputable source signals trustworthiness.',

    'check_readability' => 'Readability',
    'msg_readability_no_content' => 'No content to assess.',
    'msg_readability_long' => 'Sentences average :avg words — long sentences lose readers. Break some up.',
    'msg_readability_short' => 'Sentences average :avg words — unusually choppy. Check the content parsed correctly.',
    'msg_readability_ok' => 'Sentences average :avg words — comfortable to read.',

    // ------------------------------------------------------------- technical
    'check_indexable' => 'Indexable',
    'msg_noindex_on' => 'This post is set to noindex — search engines are told to skip it entirely.',
    'msg_noindex_off' => 'Search engines are allowed to index this post.',

    'check_social_image' => 'Social share image',
    'msg_social_ok' => 'A share image is available for Facebook, WhatsApp and X previews.',
    'msg_social_missing' => 'No share image. Links shared to WhatsApp or Facebook will render as a bare grey box.',

    'check_category' => 'Category',
    'msg_category_ok' => 'Assigned to a category.',
    'msg_category_missing' => 'No category. Categorised posts get a cleaner URL structure and better internal linking.',

    // ------------------------------------------------------- health / issues
    'issue_missing_meta_description' => 'Missing meta description',
    'issue_missing_featured_image' => 'Missing featured image',
    'issue_missing_focus_keyword' => 'No focus keyword',
    'issue_thin_content' => 'Thin content (under 300 words)',
    'issue_noindex' => 'Excluded from search (noindex)',
    'issue_missing_title' => 'Missing title',
    'issue_duplicate_title' => 'Duplicate title',
    'issue_duplicate_meta' => 'Duplicate meta description',
    'issue_long_title' => 'Title too long for search results',
    'issue_missing_alt' => 'Images without alt text',
    'issue_missing_description' => 'Missing product description',
];

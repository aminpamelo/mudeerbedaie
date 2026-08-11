---
name: blog-seo-post
description: Write or fix a blog post so it scores 85-100 on this project's built-in SEO analyser (the score shown in the /blog-seo post editor sidebar). Use whenever asked to draft, rewrite, or improve a blog article, meta description, title, slug, focus keyword, or excerpt for the Blog & SEO workspace, or when asked "why is my SEO score low".
---

# Blog post that scores high on our SEO analyser

Our score is **not** generic SEO advice — it is 21 weighted checks in
`app/Services/Seo/SeoAnalyzer.php`. Hit those checks literally and the score is
high; write beautiful prose that misses them and the score stays "Poor".

Grades: **85+ excellent · 70+ good · 50+ fair · below 50 poor.**
Every check is pass (full weight) / warn (**half** weight) / fail (zero).
Total weight is 145 when a focus keyword is set. Skipping the focus keyword
alone costs ~40 points — never skip it.

## Non-negotiable order of work

1. **Pick the focus keyword first.** One exact phrase, 2-4 words, in the same
   language as the post (`ms` or `en`). Everything below is measured against
   this literal string, case-insensitively.
2. **Write the frame** — title, slug, meta description, excerpt — around that
   exact phrase.
3. **Write the body** to the structure rules below.
4. **Verify against the checklist** at the end before handing it over.

## The frame (fill every field — blanks are scored as failures)

| Field | Hard rule | Weight |
|---|---|---|
| `title` | **30-60 characters**, contains the exact keyword. Under 30 or over 60 = half credit. | 10 + 10 |
| `meta_title` | Only if `title` can't fit 30-60 chars. **When set, it replaces `title` for scoring**, so it must also carry the keyword. | — |
| `meta_description` | **120-158 characters**, contains the exact keyword. Must be written explicitly — the excerpt fallback is **not** counted by the scorer. | 10 + 6 |
| `slug` | Lowercase, contains the slugified keyword, letters/numbers/dashes only, ≤ 75 chars. | 5 + 6 |
| `excerpt` | 1-2 sentences, ≤ 500 chars. Empty = warning. | 4 |
| `focus_keyword` | Required. | 4 |
| `category_id` | Required — pick an existing category. | 4 |
| `noindex` | Must stay **off**. | 8 |
| `featured_image_id` | Required, and it also satisfies the social-image check. Only a human can upload it — **always tell the user this is the one thing they must do in the UI.** | 8 + 5 |

## The body

- **≥ 600 words** of plain text. 300-599 is half credit; under 300 fails hard. Aim 700-900.
- **Open with a paragraph, not a heading or image.** The exact keyword must
  appear in that first paragraph.
- **At least 2 `##` headings** (3-6 is normal). The exact keyword must appear in
  at least one `##`/`###`/`####`.
- **Keyword density 0.3%-3.0%.** The formula multiplies by the keyword's word
  count, so long keywords saturate fast:
  `density = occurrences × words_in_keyword ÷ total_words × 100`.
  For an 800-word post: a 2-word keyword wants **2-11** mentions, a 3-word
  keyword **1-7**. Over 3.0% is a hard fail, not a warning — do not stuff.
- **≥ 1 internal link** — a relative path (`/shop`, `/blog/other-post`) or a
  link to our own domain. Anchor-only `#links` are ignored.
- **≥ 1 external link** to a reputable other domain.
- **≥ 1 in-body image, and every image needs non-empty alt text**:
  `![alt that describes the image](/storage/...)`. One image with an empty alt
  fails the whole check.
- **Readability: average 6-25 words per sentence**, target 12-20. Headings and
  list items are counted as running text, so end list items with a full stop —
  a long unpunctuated bullet list inflates the average and trips the warning.

Markdown supported: CommonMark + tables, strikethrough, task lists, autolinks,
and raw HTML. Heading IDs, `loading="lazy"`, and `rel="noopener"` on outbound
links are added automatically — don't hand-write them.

## Before you hand it over

Walk this list explicitly and state the expected result for each:

```
[ ] focus keyword set (exact phrase reused everywhere)
[ ] title 30-60 chars + keyword
[ ] meta_description 120-158 chars + keyword
[ ] slug contains slugified keyword, <= 75 chars
[ ] excerpt written
[ ] category chosen
[ ] noindex off
[ ] featured image  <-- user must upload
[ ] >= 600 words
[ ] keyword in first paragraph
[ ] >= 2 H2, keyword in >= 1 heading
[ ] density between 0.3% and 3.0%  (count it, show the number)
[ ] >= 1 internal link, >= 1 external link
[ ] >= 1 image, all images have alt text
[ ] avg sentence length 12-20 words
```

Count the words and the keyword occurrences for real and report both numbers.
A draft handed over without those two numbers has not been checked.

For the exact per-check weights, thresholds, and the traps that silently cost
points, read `references/scoring-rules.md`.
`PROMPT.md` in this folder is the same spec as a standalone prompt to paste into
ChatGPT, Gemini, or any other AI outside this repo.

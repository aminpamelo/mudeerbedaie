# Exact scoring rules

Source of truth: `app/Services/Seo/SeoAnalyzer.php`. If that file changes, update
this document and `SKILL.md`.

## How the score is computed

- Each check has a weight. `pass` earns the full weight, `warn` earns
  `round(weight / 2)`, `fail` earns 0.
- `score = round(earned / total × 100)`.
- Total weight = **145** with a focus keyword set, **111** without.
- Grade: `>= 85 excellent`, `>= 70 good`, `>= 50 fair`, else `poor`.
- The report is recomputed and stored on save (`seo_score`, `seo_report`,
  `seo_checked_at`), and the editor sidebar re-runs it live as you type.

## Basics (29)

| id | weight | pass | warn | fail |
|---|---|---|---|---|
| `title_length` | 10 | 30-60 chars | 1-29 or 61+ | empty |
| `meta_description` | 10 | 120-158 chars | 1-119 or 159+ | empty |
| `excerpt` | 4 | filled | empty | — |
| `slug_quality` | 5 | ≤ 75 chars | > 75 chars | empty |

`title_length` measures `seo_title` = `meta_title` if filled, otherwise `title`.
`meta_description` measures the **raw column**, not the `seo_description`
accessor — the excerpt/body fallback used on the public page does not count here.

## Focus keyword (46 when set / 12 when missing)

With no keyword the group collapses to a single `focus_keyword` **fail** worth
12 — losing the 46-point group and roughly 40 score points at once.

| id | weight | rule |
|---|---|---|
| `focus_keyword` | 4 | pass whenever a keyword is set |
| `keyword_in_title` | 10 | `seo_title` contains the keyword (case-insensitive substring). **fail** if not — no half credit |
| `keyword_in_slug` | 6 | slug contains `Str::slug(keyword)` |
| `keyword_in_description` | 6 | `meta_description` contains the keyword |
| `keyword_in_intro` | 8 | the **first non-empty `<p>`** of the rendered HTML contains the keyword |
| `keyword_in_heading` | 6 | any `h2`/`h3`/`h4` contains the keyword |
| `keyword_density` | 6 | pass 0.3-3.0, warn < 0.3, **fail > 3.0** |

Density formula:

```
occurrences = substr_count(lower(plain_text), lower(keyword))
density     = occurrences × word_count(keyword) ÷ total_words × 100
```

Two consequences worth remembering:

1. It is a plain substring count, so occurrences inside longer words also count.
2. Multiplying by the keyword's own word count means a 3-word keyword reaches
   the ceiling three times faster than a 1-word keyword.

Safe occurrence range = `0.3 × W ÷ (100 × k)` to `3.0 × W ÷ (100 × k)`
(W = total words, k = words in keyword):

| Words | 1-word kw | 2-word kw | 3-word kw |
|---|---|---|---|
| 600 | 2-18 | 1-9 | 1-6 |
| 800 | 3-24 | 2-12 | 1-8 |
| 1200 | 4-36 | 2-18 | 2-12 |

## Content (53)

| id | weight | pass | warn | fail |
|---|---|---|---|---|
| `content_length` | 12 | ≥ 600 words | 300-599 | < 300 |
| `headings` | 8 | ≥ 2 `h2` | exactly 1 | 0 |
| `featured_image` | 8 | `featured_image_id` set | — | not set |
| `image_alt` | 8 | ≥ 1 image, all with alt | no images at all | any image with missing/empty alt |
| `internal_links` | 7 | ≥ 1 | 0 | — |
| `external_links` | 4 | ≥ 1 | 0 | — |
| `readability` | 6 | avg 6-25 words/sentence | > 25 or < 6 | empty body |

- Word count = whitespace-split of `strip_tags(content_html)`.
- A link is **internal** when its href has no host (`/shop`, `page.html`) or the
  host equals `config('app.url')`'s host; anything else is external. `href="#..."`
  links are skipped entirely by both counts.
- Sentences split on `[.!?]+\s`. Headings, list items, and table cells become
  part of the running text, so unpunctuated blocks merge into one very long
  "sentence" and can push the average past 25.
- Note the asymmetry: having **no** images is only a warning (4 of 8 points),
  but having an image with an empty alt is a hard zero.

## Technical (17)

| id | weight | pass | warn | fail |
|---|---|---|---|---|
| `indexable` | 8 | `noindex` off | — | `noindex` on |
| `social_image` | 5 | `og_image_id` or `featured_image_id` set | neither | — |
| `category` | 4 | `category_id` set | not set | — |

## Field constraints from `StorePostRequest`

- `title` required, ≤ 255.
- `slug` required, ≤ 255, `alpha_dash` (letters, numbers, dashes, underscores
  only — no slashes or dots), unique across posts.
- `excerpt` ≤ 500, `meta_title` ≤ 255, `meta_description` ≤ 500 (but the scorer
  wants ≤ 158), `focus_keyword` ≤ 255.
- `locale` must be `ms` or `en`; `status` one of `draft|scheduled|published|archived`;
  `published_at` required when status is `scheduled`.
- `canonical_url` must be a valid URL when present.
- `tags` = array of strings ≤ 80 chars each.

## The ceiling without a human

An AI can fill everything except `featured_image_id` / `og_image_id`, which need
an uploaded media record. Missing the featured image costs 8 points of weight
outright plus half of the 5-point social check — about **7 score points**. So a
fully AI-written post tops out near **93**; say so rather than promising 100.

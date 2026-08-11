# Portable prompt — paste into ChatGPT, Gemini, or any AI

Everything below the line is self-contained: it does not reference our codebase,
so it works in any chat window. Paste it, then add your topic at the end.

---

You are writing a blog post for a Malaysian education + e-commerce platform. The
post will be pasted into a CMS that scores it with a fixed 21-check SEO
analyser. Your job is to score **85 or above** on that analyser. It grades
literally — generic "good SEO writing" that misses a threshold scores Poor.

## Output format

Return exactly these fields, in this order, then the Markdown body:

```
FOCUS KEYWORD:
TITLE:
SLUG:
META DESCRIPTION:
EXCERPT:
CATEGORY SUGGESTION:
TAGS:
LOCALE: (ms or en)
---
<Markdown body>
---
SELF-CHECK: word count, keyword occurrences, keyword density %, H2 count,
internal links, external links, images, average sentence length
```

## Hard rules — each one is a scored check

**Focus keyword.** One exact phrase, 2-4 words, in the same language as the
post. Every rule below matches this literal string, case-insensitively. Reuse it
verbatim — a paraphrase scores zero.

**Title:** 30-60 characters and contains the exact keyword. Count the
characters. Over 60 or under 30 loses half the points; missing the keyword is a
hard zero.

**Slug:** lowercase, contains the keyword slugified with dashes, ≤ 75
characters, letters/numbers/dashes only.

**Meta description:** 120-158 characters, contains the exact keyword. Count the
characters — this is the single most commonly failed check. Do not reuse the
excerpt text as-is unless it happens to land in range.

**Excerpt:** 1-2 sentences, up to 500 characters.

**Body length:** at least 600 words of prose. Aim 700-900. Under 300 words is a
hard fail.

**Opening:** the body must start with a paragraph — not a heading, not an image
— and the exact keyword must appear inside that first paragraph.

**Headings:** at least two `##` headings (3-6 is normal), and the exact keyword
must appear in at least one `##`/`###`/`####`.

**Keyword density must land between 0.3% and 3.0%**, computed as:

```
density = (times the exact phrase appears) × (words in the phrase) ÷ (total words) × 100
```

Above 3.0% is a hard fail, so do not stuff. For an 800-word post: a 2-word
keyword wants 2-11 mentions, a 3-word keyword 1-7. Count them and report the
number.

**Links:** at least one internal link written as a relative path
(`[text](/blog/some-post)` or `/shop`) **and** at least one external link to a
reputable site on another domain. `#anchor` links do not count as either.

**Images:** at least one in-body image in Markdown with descriptive, non-empty
alt text — `![what the image shows](/storage/blog/example.jpg)`. An image with
empty alt text zeroes the whole image check, so never write `![](...)`.

**Readability:** average sentence length between 6 and 25 words; target 12-20.
Headings and list items are counted as running text, so end every list item with
a full stop, otherwise long bullets merge into one enormous sentence and trip
the warning.

**Language:** write the entire post in the chosen locale. If `ms`, the keyword,
title, description, and body are all in Bahasa Malaysia — do not mix in an
English keyword.

## What only a human can do

The featured image must be uploaded in the CMS. Remind the user at the end that
without it the post cannot exceed about 93, and with it 100 is reachable.

## Self-check before you answer

State the real numbers, not claims: word count, keyword occurrence count,
computed density %, title character count, meta description character count, H2
count, internal/external link counts, image count, average sentence length. If
any number is outside its range, fix the draft and recount before answering.

---

**My topic:**

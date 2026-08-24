<?php

namespace App\Http\Requests\BlogSeo;

use App\Models\BlogPost;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create/update payload for a blog post authored in the Blog & SEO workspace.
 */
class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $postId = $this->route('post')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('blog_posts', 'slug')->ignore($postId)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:blog_categories,id'],
            'blog_author_id' => ['nullable', 'exists:blog_authors,id'],
            'locale' => ['required', 'in:ms,en'],
            'status' => ['required', 'in:draft,scheduled,published,archived'],
            'published_at' => ['nullable', 'date', Rule::requiredIf(fn () => $this->input('status') === BlogPost::STATUS_SCHEDULED)],
            'is_featured' => ['boolean'],
            'allow_comments' => ['boolean'],
            'featured_image_id' => ['nullable', 'exists:media,id'],
            'og_image_id' => ['nullable', 'exists:media,id'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'focus_keyword' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'noindex' => ['boolean'],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:80'],
            'product_ids' => ['array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'published_at.required' => 'Pick a date and time for the scheduled post.',
            'slug.alpha_dash' => 'The URL slug may only contain letters, numbers, dashes and underscores.',
            'slug.unique' => 'Another post already uses that URL slug.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // The editor sends "" for cleared optional fields; `nullable` does not
        // skip an empty string, so `url` / `exists` would reject them.
        $this->merge([
            'canonical_url' => trim((string) $this->input('canonical_url')) ?: null,
            'category_id' => $this->input('category_id') ?: null,
            'blog_author_id' => $this->input('blog_author_id') ?: null,
            'featured_image_id' => $this->input('featured_image_id') ?: null,
            'og_image_id' => $this->input('og_image_id') ?: null,
            'published_at' => trim((string) $this->input('published_at')) ?: null,
        ]);
    }
}

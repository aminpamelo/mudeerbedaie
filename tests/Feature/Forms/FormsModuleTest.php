<?php

declare(strict_types=1);

use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function formOwner(): User
{
    return User::factory()->create(['role' => 'teacher']);
}

it('lets any authenticated user create a form', function () {
    $user = formOwner();

    $this->actingAs($user)
        ->post('/forms', [
            'title' => 'Borang Pendaftaran',
            'description' => 'Sila isi',
            'status' => 'published',
            'fields' => [
                ['type' => 'short_text', 'label' => 'Nama', 'required' => true, 'options' => [], 'settings' => []],
                ['type' => 'email', 'label' => 'Emel', 'required' => true, 'options' => [], 'settings' => []],
            ],
            'settings' => ['confirmation_message' => 'Terima kasih', 'allow_multiple' => true],
        ])
        ->assertRedirect();

    $form = Form::first();
    expect($form)->not->toBeNull()
        ->and($form->user_id)->toBe($user->id)
        ->and($form->status)->toBe('published')
        ->and($form->published_at)->not->toBeNull()
        ->and($form->fields)->toHaveCount(2)
        ->and($form->fields[0]['id'])->toStartWith('fld_')
        ->and($form->slug)->not->toBeEmpty();
});

it('forbids editing a form you do not own', function () {
    $owner = formOwner();
    $stranger = formOwner();
    $form = Form::factory()->for($owner)->create();

    $this->actingAs($stranger)
        ->get("/forms/{$form->id}/edit")
        ->assertForbidden();
});

it('lets an admin edit any form', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $form = Form::factory()->for(formOwner())->create();

    $this->actingAs($admin)
        ->get("/forms/{$form->id}/edit")
        ->assertOk();
});

it('shows a published form publicly and accepts a submission', function () {
    $form = Form::factory()->published()->create([
        'fields' => [
            ['id' => 'fld_name', 'type' => 'short_text', 'label' => 'Nama', 'required' => true, 'options' => [], 'settings' => []],
            ['id' => 'fld_food', 'type' => 'checkbox', 'label' => 'Makanan', 'required' => false, 'options' => ['Nasi', 'Mee'], 'settings' => []],
        ],
    ]);

    $this->get("/form/{$form->slug}")->assertOk();

    $this->post("/form/{$form->slug}", [
        'answers' => [
            'fld_name' => 'Ali',
            'fld_food' => ['Nasi'],
        ],
    ])->assertRedirect(route('form.public.thankyou', $form->slug));

    $submission = FormSubmission::first();
    expect($submission)->not->toBeNull()
        ->and($submission->data['fld_name'])->toBe('Ali')
        ->and($submission->data['fld_food'])->toBe(['Nasi'])
        ->and($form->fresh()->submissions_count)->toBe(1);
});

it('rejects submissions to a draft form', function () {
    $form = Form::factory()->create(['status' => 'draft']);

    $this->post("/form/{$form->slug}", ['answers' => []])
        ->assertForbidden();
});

it('validates required fields on public submission', function () {
    $form = Form::factory()->published()->create([
        'fields' => [
            ['id' => 'fld_name', 'type' => 'short_text', 'label' => 'Nama', 'required' => true, 'options' => [], 'settings' => []],
        ],
    ]);

    $this->post("/form/{$form->slug}", ['answers' => []])
        ->assertSessionHasErrors('answers.fld_name');

    expect(FormSubmission::count())->toBe(0);
});

it('stores an uploaded file with the submission', function () {
    Storage::fake('public');

    $form = Form::factory()->published()->create([
        'fields' => [
            ['id' => 'fld_doc', 'type' => 'file', 'label' => 'Dokumen', 'required' => true, 'options' => [], 'settings' => []],
        ],
    ]);

    $this->post("/form/{$form->slug}", [
        'answers' => ['fld_doc' => UploadedFile::fake()->create('resume.pdf', 10, 'application/pdf')],
    ])->assertRedirect();

    $submission = FormSubmission::first();
    expect($submission->data['fld_doc']['name'])->toBe('resume.pdf');
    Storage::disk('public')->assertExists($submission->data['fld_doc']['path']);
});

it('downloads a submission as PDF for the owner', function () {
    $owner = formOwner();
    $form = Form::factory()->for($owner)->published()->create([
        'fields' => [['id' => 'fld_name', 'type' => 'short_text', 'label' => 'Nama', 'required' => true, 'options' => [], 'settings' => []]],
    ]);
    $submission = FormSubmission::factory()->for($form)->create(['data' => ['fld_name' => 'Ali']]);

    $response = $this->actingAs($owner)
        ->get("/forms/{$form->id}/submissions/{$submission->id}/pdf");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('exports submissions as CSV', function () {
    $owner = formOwner();
    $form = Form::factory()->for($owner)->create([
        'fields' => [['id' => 'fld_name', 'type' => 'short_text', 'label' => 'Nama', 'required' => true, 'options' => [], 'settings' => []]],
    ]);
    FormSubmission::factory()->for($form)->create(['data' => ['fld_name' => 'Ali']]);

    $this->actingAs($owner)
        ->get("/forms/{$form->id}/submissions/export")
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('restricts the admin oversight page to admins', function () {
    $this->actingAs(formOwner())->get('/forms/admin')->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/forms/admin')->assertOk();
});

it('builds, submits and exports a form using every field type', function () {
    Storage::fake('public');

    $owner = formOwner();

    // One of every field type, exactly as the React builder would POST them.
    $fields = [
        ['id' => 'f_short', 'type' => 'short_text', 'label' => 'Teks Pendek', 'required' => true, 'options' => [], 'settings' => []],
        ['id' => 'f_long', 'type' => 'long_text', 'label' => 'Teks Panjang', 'required' => false, 'options' => [], 'settings' => []],
        ['id' => 'f_num', 'type' => 'number', 'label' => 'Nombor', 'required' => true, 'options' => [], 'settings' => []],
        ['id' => 'f_email', 'type' => 'email', 'label' => 'Emel', 'required' => true, 'options' => [], 'settings' => []],
        ['id' => 'f_date', 'type' => 'date', 'label' => 'Tarikh', 'required' => false, 'options' => [], 'settings' => []],
        ['id' => 'f_radio', 'type' => 'radio', 'label' => 'Sesi', 'required' => true, 'options' => ['Pagi', 'Petang'], 'settings' => []],
        ['id' => 'f_check', 'type' => 'checkbox', 'label' => 'Topik', 'required' => false, 'options' => ['Fiqh', 'Sirah'], 'settings' => []],
        ['id' => 'f_drop', 'type' => 'dropdown', 'label' => 'Negeri', 'required' => true, 'options' => ['Perak', 'Selangor'], 'settings' => []],
        ['id' => 'f_file', 'type' => 'file', 'label' => 'Dokumen', 'required' => true, 'options' => [], 'settings' => []],
        ['id' => 'f_rate', 'type' => 'rating', 'label' => 'Penilaian', 'required' => false, 'options' => [], 'settings' => ['max' => 5]],
        ['id' => 'f_phone', 'type' => 'phone', 'label' => 'Telefon', 'required' => false, 'options' => [], 'settings' => []],
        ['id' => 'f_section', 'type' => 'section', 'label' => 'Bahagian B', 'required' => false, 'options' => [], 'settings' => []],
        ['id' => 'f_para', 'type' => 'paragraph', 'label' => 'Sila jawab dengan jujur.', 'required' => false, 'options' => [], 'settings' => []],
    ];

    // Build + publish through the real store endpoint (exercises FieldSchema).
    $this->actingAs($owner)->post('/forms', [
        'title' => 'Borang Semua Elemen',
        'status' => 'published',
        'fields' => $fields,
        'settings' => ['confirmation_message' => 'Siap', 'allow_multiple' => true],
    ])->assertRedirect();

    $form = Form::first();
    expect($form->fields)->toHaveCount(13)
        ->and($form->answerableFields())->toHaveCount(11); // section + paragraph excluded

    // Public page renders.
    $this->get("/form/{$form->slug}")->assertOk();

    // Submit a valid answer for every answerable field type.
    $this->post("/form/{$form->slug}", [
        'answers' => [
            'f_short' => 'Ali bin Abu',
            'f_long' => 'Jawapan panjang di sini.',
            'f_num' => 42,
            'f_email' => 'ali@example.com',
            'f_date' => '2026-08-10',
            'f_radio' => 'Pagi',
            'f_check' => ['Fiqh', 'Sirah'],
            'f_drop' => 'Selangor',
            'f_file' => UploadedFile::fake()->create('surat.pdf', 12, 'application/pdf'),
            'f_rate' => 4,
            'f_phone' => '012-3456789',
        ],
    ])->assertRedirect(route('form.public.thankyou', $form->slug));

    $sub = FormSubmission::first();
    expect($sub->data['f_short'])->toBe('Ali bin Abu')
        ->and((string) $sub->data['f_num'])->toBe('42')
        ->and($sub->data['f_email'])->toBe('ali@example.com')
        ->and($sub->data['f_radio'])->toBe('Pagi')
        ->and($sub->data['f_check'])->toBe(['Fiqh', 'Sirah'])
        ->and($sub->data['f_drop'])->toBe('Selangor')
        ->and((string) $sub->data['f_rate'])->toBe('4')
        ->and($sub->data['f_file']['name'])->toBe('surat.pdf');
    Storage::disk('public')->assertExists($sub->data['f_file']['path']);

    // Rejects an out-of-range choice (radio value not in options).
    $this->post("/form/{$form->slug}", [
        'answers' => ['f_short' => 'x', 'f_num' => 1, 'f_email' => 'a@b.com', 'f_radio' => 'Tengahari', 'f_drop' => 'Perak', 'f_file' => UploadedFile::fake()->create('x.pdf', 1)],
    ])->assertSessionHasErrors('answers.f_radio');

    // PDF + CSV both work with all field types present.
    $this->actingAs($owner)
        ->get("/forms/{$form->id}/submissions/{$sub->id}/pdf")
        ->assertOk();
    $this->actingAs($owner)
        ->get("/forms/{$form->id}/submissions/export")
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('shows the global submissions page to admins only', function () {
    $form = Form::factory()->published()->create();
    FormSubmission::factory()->for($form)->create(['data' => ['fld_name' => 'Ali']]);

    $this->actingAs(formOwner())->get('/forms/submissions')->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/forms/submissions')->assertOk();
});

it('redirects /admin to the real admin dashboard', function () {
    $this->get('/admin')->assertRedirect('/dashboard');
});

it('lets an admin manage form categories but forbids others', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post('/forms/categories', ['name' => 'Pendaftaran', 'color' => '#4F46E5', 'is_active' => true])
        ->assertRedirect();

    expect(FormCategory::where('name', 'Pendaftaran')->exists())->toBeTrue();

    $this->actingAs(formOwner())
        ->post('/forms/categories', ['name' => 'Nope'])
        ->assertForbidden();
});

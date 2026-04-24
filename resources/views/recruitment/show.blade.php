<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $campaign->title }} &mdash; Live Host Recruitment</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
<div class="mx-auto max-w-2xl p-6">
    <header class="border-b border-gray-200 pb-6">
        <h1 class="text-3xl font-bold tracking-tight">{{ $campaign->title }}</h1>
        @if ($campaign->closes_at)
            <p class="mt-1 text-sm text-gray-500">Applications close {{ $campaign->closes_at->toFormattedDateString() }}</p>
        @endif
        @if (! empty($campaign->description))
            <div class="prose prose-sm mt-4 max-w-none text-gray-700">{!! nl2br(e($campaign->description)) !!}</div>
        @endif
    </header>

    @if ($errors->any())
        <div class="mt-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Please fix the following before submitting:</p>
            <ul class="mt-2 list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ route('recruitment.apply', $campaign->slug) }}"
          enctype="multipart/form-data"
          class="mt-6 space-y-5">
        @csrf

        <div>
            <label for="full_name" class="block text-sm font-medium text-gray-700">Full name <span class="text-red-500">*</span></label>
            <input type="text" id="full_name" name="full_name" required
                   value="{{ old('full_name') }}"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm">
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
            <input type="email" id="email" name="email" required
                   value="{{ old('email') }}"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm">
        </div>

        <div>
            <label for="phone" class="block text-sm font-medium text-gray-700">Phone number <span class="text-red-500">*</span></label>
            <input type="text" id="phone" name="phone" required
                   value="{{ old('phone') }}"
                   placeholder="e.g. 60123456789"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm">
        </div>

        <div>
            <label for="ic_number" class="block text-sm font-medium text-gray-700">IC number</label>
            <input type="text" id="ic_number" name="ic_number"
                   value="{{ old('ic_number') }}"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm">
        </div>

        <div>
            <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
            <input type="text" id="location" name="location"
                   value="{{ old('location') }}"
                   placeholder="City, State"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm">
        </div>

        <fieldset>
            <legend class="block text-sm font-medium text-gray-700">Platforms you can live on <span class="text-red-500">*</span></legend>
            <div class="mt-2 space-y-2">
                @php($selectedPlatforms = old('platforms', []))
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="platforms[]" value="tiktok"
                           @checked(in_array('tiktok', $selectedPlatforms))
                           class="rounded border-gray-300 text-gray-900 focus:ring-gray-900">
                    TikTok
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="platforms[]" value="shopee"
                           @checked(in_array('shopee', $selectedPlatforms))
                           class="rounded border-gray-300 text-gray-900 focus:ring-gray-900">
                    Shopee
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="platforms[]" value="facebook"
                           @checked(in_array('facebook', $selectedPlatforms))
                           class="rounded border-gray-300 text-gray-900 focus:ring-gray-900">
                    Facebook
                </label>
            </div>
        </fieldset>

        <div>
            <label for="experience_summary" class="block text-sm font-medium text-gray-700">Experience summary</label>
            <textarea id="experience_summary" name="experience_summary" rows="4"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm">{{ old('experience_summary') }}</textarea>
            <p class="mt-1 text-xs text-gray-500">Briefly describe your prior live selling / hosting experience.</p>
        </div>

        <div>
            <label for="motivation" class="block text-sm font-medium text-gray-700">Why do you want to join?</label>
            <textarea id="motivation" name="motivation" rows="4"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm">{{ old('motivation') }}</textarea>
        </div>

        <div>
            <label for="resume" class="block text-sm font-medium text-gray-700">Resume (optional)</label>
            <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx"
                   class="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-gray-900 file:px-4 file:py-2 file:text-white hover:file:bg-gray-700">
            <p class="mt-1 text-xs text-gray-500">PDF, DOC, or DOCX up to 5 MB.</p>
        </div>

        <div class="pt-2">
            <button type="submit" class="inline-flex items-center rounded-md bg-gray-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2">
                Submit application
            </button>
        </div>
    </form>
</div>
</body>
</html>

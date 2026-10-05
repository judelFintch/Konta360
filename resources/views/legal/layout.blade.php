<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — Konta360</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased">
    <main class="mx-auto max-w-3xl px-4 py-10">
        <a href="{{ url('/') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">← Konta360</a>
        <article class="mt-6 space-y-4 rounded-xl bg-white p-6 text-sm leading-6 text-gray-700 shadow-sm ring-1 ring-gray-200 sm:p-10 [&_h1]:text-2xl [&_h1]:font-bold [&_h1]:text-gray-900 [&_h2]:pt-4 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-gray-900 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-6">
            {{-- Draft notice: remove only once a lawyer has reviewed the text (ADR 0003 § 4). --}}
            <p class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">Projet en cours de validation juridique — version {{ config('konta360.terms_version') }}.</p>
            @yield('content')
        </article>
    </main>
</body>
</html>

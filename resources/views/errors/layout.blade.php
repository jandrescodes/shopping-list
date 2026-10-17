@extends('layout')

@section('content')
    <section class="py-8 text-center">
        <p class="text-sm font-semibold text-blue-700">@yield('code')</p>
        <h1 class="mt-2 text-2xl font-bold tracking-tight">@yield('heading')</h1>
        <p class="mt-2 text-gray-600">@yield('message')</p>

        @hasSection('retry')
            <a href="{{ url()->current() }}"
                class="mt-6 inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 font-semibold text-white hover:bg-blue-700">
                Reintentar
            </a>
        @endif
    </section>
@endsection

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Nexora ERP — Procurement Suite')</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
@yield('styles')
</head>
<body>

@include('partials.topnav')

<div class="app">
  @include('partials.sidebar')

  <main class="main">
    @yield('content')
  </main>
</div>

@include('partials.modals')
@include('partials.toast')

{{-- Shared scripts, split by concern so no single file is too long --}}
<script src="{{ asset('js/app-core.js') }}"></script>       {{-- page/notif/toast/stat helpers --}}
<script src="{{ asset('js/app-modals.js') }}"></script>     {{-- view/edit/delete record modals --}}
<script src="{{ asset('js/app-dashboard.js') }}"></script>  {{-- donut chart, report ranges, queue tabs --}}
<script src="{{ asset('js/app-filters.js') }}"></script>    {{-- table search, sort, filter panels --}}
<script src="{{ asset('js/app-deliveries.js') }}"></script> {{-- delivery tracking modal --}}
<script src="{{ asset('js/app-forms.js') }}"></script>      {{-- add PO/Supplier/Req/Delivery/Invoice forms --}}

{{-- Page-specific scripts (optional) --}}
@yield('scripts')

{{-- Final init calls (row buttons, tab counts, donut, dashboard animation) --}}
<script src="{{ asset('js/app-init.js') }}"></script>
</body>
</html>

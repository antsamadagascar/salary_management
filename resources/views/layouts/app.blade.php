<!DOCTYPE html>
<html lang="fr">
<head>
 @include('partials.head')
</head>
<body>
 @include('partials.header')
<div class="container-fluid">
<div class="row">
        <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
        @include('partials.sidebar')
        </div>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
        @yield('content')
        </main>
        </div>
        </div>
 @include('partials.footer')
<!-- Scripts supplémentaires peuvent être chargés via la section scripts -->
 @yield('scripts')
</body>
</html> 
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="ie=edge">
<title>@yield('title', 'ERP Dashboard')</title>

<!-- Styles CSS -->
<link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet"> <!-- Bootstrap -->
<link href="{{ asset('css/app.css') }}" rel="stylesheet"> <!-- CSS personnalisé -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> <!-- Font Awesome -->
<link rel="stylesheet" href="{{ asset('css/jquery.dataTables.min.css')}}"> <!-- DataTables -->
<link rel="stylesheet" href="{{ asset('css/buttons/2.3.6/css/buttons.dataTables.min.css')}}"> <!-- DataTables Buttons -->

<!-- Scripts JavaScript -->
<script src="{{ asset('js/jquery/jquery-3.6.4.min.js')}}"></script> <!-- jQuery -->
<script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script> <!-- Bootstrap -->
<script src="{{ asset('js/chart.js') }}"></script> <!-- Chart.js -->
<script src="{{ asset('js/theme.js') }}"></script> <!-- Thème -->

<!-- DataTables et Extensions -->
<script src="{{ asset('js/jquery/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('js/buttons/2.3.6/dataTables.buttons.min.js')}}"></script>
<script src="{{ asset('js/jszip/3.7.1/jszip.min.js') }}"></script>
<script src="{{ asset('js/pdfmake/0.1.68/pdfmake.min.js') }}"></script>
<script src="{{ asset('js/pdfmake/0.1.68/vfs_fonts.js') }}"></script>
<script src="{{ asset('js/buttons/2.3.6/buttons.html5.min.js')}}"></script>

@yield('styles')

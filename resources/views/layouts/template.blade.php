<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    <title>{{ $title ?? 'Users' }} - Obsesiman Report - Laravel</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- TomSelect CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">

    @livewireStyles
</head>

<body>
    <div class="app-container is-full-screen">
        @include('layouts.sidebar')

        <main class="main-content">
            @include('layouts.header')
            <div class="content-body">
                {{ $slot }}
            </div>
            <!-- Network Monitor - Only visible on mobile -->
        </main>
    </div>

   @include('layouts/notification')

    <div class="copyright">
        <p>&copy; Alphara</p>
    </div>

    <div id="overlay" class="overlay"></div>

    @if(session('success'))
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '{{ session('success') }}',
            timer: false,
            showConfirmButton: true
        });
    </script>
    @endif

    @if(session('error'))
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '{{ session('error') }}',
            timer: false,
            showConfirmButton: true
        });
    </script>
    @endif

    @livewireScripts
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

</body>

</html>
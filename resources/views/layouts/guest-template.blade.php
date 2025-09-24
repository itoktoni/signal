<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - {{ $title ?? 'Guest' }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Styles -->
    @livewireStyles

    <style>
        body {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Figtree', sans-serif;
            margin: 0;
            padding: 0;
        }
        .guest-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 2rem;
            width: 100%;
            max-width: 500px;
            position: relative;
            overflow: hidden;
        }
        .guest-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #f093fb, #f5576c);
        }
        .guest-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .guest-header h1 {
            color: #333;
            font-size: 2rem;
            font-weight: 600;
            margin: 0 0 0.5rem 0;
        }
        .guest-header p {
            color: #666;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="guest-container">
        <div class="guest-header">
            <h1>{{ config('app.name', 'Laravel') }}</h1>
            <p>{{ $title ?? 'Welcome' }}</p>
        </div>

        {{ $slot }}

        <div class="guest-actions" style="display: flex; justify-content: center; margin-top: 2rem;">
            <a href="/" style="color: #f5576c; text-decoration: none;">← Back to Home</a>
        </div>
    </div>

    @livewireScripts
</body>
</html>
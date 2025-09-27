<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - {{ $title ?? 'Auth' }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Styles -->
    @livewireStyles

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Figtree', sans-serif;
            margin: 0;
            padding: 0;
        }
        .auth-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            position: relative;
            overflow: hidden;
        }
        .auth-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .auth-header h1 {
            color: #333;
            font-size: 2rem;
            font-weight: 600;
            margin: 0 0 0.5rem 0;
        }
        .auth-header p {
            color: #666;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1>{{ config('app.name', 'Laravel') }}</h1>
            <p>{{ $title ?? 'Welcome back' }}</p>
        </div>

        {{ $slot }}

        <div class="auth-actions" style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem;">
            <div class="auth-links" style="display: flex; gap: 1rem;">
                @if (Route::has('register') && !request()->routeIs('register'))
                    <a href="{{ route('register') }}" style="color: #667eea; text-decoration: none; font-size: 0.9rem;">Create Account</a>
                @endif
                @if (Route::has('login') && !request()->routeIs('login'))
                    <a href="{{ route('login') }}" style="color: #667eea; text-decoration: none; font-size: 0.9rem;">Sign In</a>
                @endif
            </div>
            <a href="/" style="color: #667eea; text-decoration: none;">← Back to Home</a>
        </div>
    </div>

    @livewireScripts
</body>
</html>
<meta charset="UTF-8">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>@yield('title', 'Panel') - Mecacuy</title>

<link rel="icon" type="image/x-icon" href="{{ asset('dashboard/assets/images/logo/favicon.png') }}">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link
    rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap"
>

{{-- Variables y componentes se cargan una sola vez. La versión cambia al editar el archivo. --}}
<link rel="stylesheet" href="{{ asset('dashboard/assets/css/mecacuy/theme.css') }}?v={{ filemtime(public_path('dashboard/assets/css/mecacuy/theme.css')) }}">
<link rel="stylesheet" href="{{ asset('dashboard/assets/css/mecacuy/panel.css') }}?v={{ filemtime(public_path('dashboard/assets/css/mecacuy/panel.css')) }}">

{{-- Remix Icon --}}
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">

@stack('styles')

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Login') - Mecacuy</title>

    <link rel="icon" type="image/x-icon" href="{{ asset('dashboard/assets/images/logo/favicon.png') }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('dashboard/assets/images/logo/favicon.png') }}">

 
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com/">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>

    <!-- App css -->
    <link rel="stylesheet" href="{{ asset('dashboard/assets/css/mecacuy/auth.css') }}">
</head>

<body>
@yield('content')

</body>
</html>
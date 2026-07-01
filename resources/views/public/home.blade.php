<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>MecaCuy</title>

    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('landing/assets/images/favicons/apple-touch-icon.png') }}" />
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('landing/assets/images/favicons/favicon-32x32.png') }}" />
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('landing/assets/images/favicons/favicon-16x16.png') }}" />
    <meta name="description" content="Diseño y modelado de una arquitectura mecatrónica basada en IoT para la automatización integral de módulos de crianza de cuyes." />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Amatic+SC:wght@400;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('landing/assets/vendors/bootstrap/css/bootstrap.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('landing/assets/vendors/animate/animate.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('landing/assets/vendors/animate/custom-animate.css') }}" />
    <link rel="stylesheet" href="{{ asset('landing/assets/vendors/fontawesome/css/all.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('landing/assets/vendors/jarallax/jarallax.css') }}" />
    <link rel="stylesheet" href="{{ asset('landing/assets/vendors/jquery-magnific-popup/jquery.magnific-popup.css') }}" />
    <link rel="stylesheet" href="{{ asset('landing/assets/vendors/odometer/odometer.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('landing/assets/vendors/swiper/swiper.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('landing/assets/vendors/agrion-icons/style.css') }}">
    <link rel="stylesheet" href="{{ asset('landing/assets/vendors/owl-carousel/owl.carousel.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('landing/assets/vendors/owl-carousel/owl.theme.default.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('landing/assets/css/agrion.css') }}" />
    <link rel="stylesheet" href="{{ asset('landing/assets/css/agrion-responsive.css') }}" />
    <link rel="stylesheet" href="{{ asset('landing/assets/css/agrion-dark.css') }}" />
    <style>
        .project-badge {
            display: inline-block;
            padding: 8px 18px;
            border-radius: 999px;
            background: rgba(255,255,255,.12);
            color: #fff;
            font-size: 14px;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-bottom: 20px;
        }
        .mini-card {
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 16px;
            padding: 24px;
            height: 100%;
        }
        .mini-card h4,
        .mini-card p,
        .problem-card h4,
        .problem-card p { color: #fff; }
        .problem-card {
            background: #0f1b13;
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 20px;
            padding: 28px;
            height: 100%;
        }
        .section-space { padding: 120px 0; }
        .text-justify { text-align: justify; }
        .icon-box-custom {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #4baf47;
            color: #fff;
            font-size: 28px;
            margin-bottom: 18px;
        }
        .metric-box {
            text-align: center;
            padding: 28px 20px;
            border-radius: 18px;
            background: #111c15;
            border: 1px solid rgba(255,255,255,.06);
            height: 100%;
        }
        .metric-box h3,
        .metric-box p { color: #fff; }
        .main-menu__list a { cursor: pointer; }
    </style>
</head>
<body class="custom-cursor">

<div class="custom-cursor__cursor"></div>
<div class="custom-cursor__cursor-two"></div>

<div class="preloader">
    <div class="preloader__image"></div>
</div>

<div class="page-wrapper">
    <header class="main-header">
        <div class="main-header__wrapper">
            <div class="main-header__wrapper-inner">
                <div class="main-header__logo">
                    <a href="#inicio"><img src="{{ asset('landing/assets/images/resources/logo-1.png') }}" alt="MecaCuy"></a>
                </div>
                <div class="main-header__menu-box">
                    <div class="main-header__menu-box-bottom">
                        <nav class="main-menu">
                            <div class="main-menu__wrapper">
                                <div class="main-menu__wrapper-inner">
                                    <div class="main-menu__left">
                                        <div class="main-menu__main-menu-box">
                                            <a href="#" class="mobile-nav__toggler"><i class="fa fa-bars"></i></a>
                                            <ul class="main-menu__list">
                                                <li><a href="#inicio">Inicio</a></li>
                                                <li><a href="#proyecto">Proyecto</a></li>
                                                <li><a href="#problema">Problema</a></li>
                                                <li><a href="#arquitectura">Arquitectura IoT</a></li>
                                                <li><a href="#modulos">Módulos</a></li>
                                                <li><a href="#beneficios">Beneficios</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </nav>
                    </div>
                </div>
                <div class="main-header__phone-contact-box">
                    <div class="main-header__phone-number">
                        <a href="{{ route('login') }}">Login</a>
                    </div>
                    <div class="main-header__call-box">
                        <div class="main-header__call-inner">
                            <a href="{{ route('login') }}" class="main-header__call-icon">
                                <span class="fas fa-user"></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="stricky-header stricked-menu main-menu">
        <div class="sticky-header__content"></div>
    </div>

    <section class="main-slider clearfix" id="inicio">
        <div class="swiper-container thm-swiper__slider" data-swiper-options='{"slidesPerView":1,"loop":true,"effect":"fade","pagination":{"el":"#main-slider-pagination","type":"bullets","clickable":true},"navigation":{"nextEl":"#main-slider__swiper-button-next","prevEl":"#main-slider__swiper-button-prev"},"autoplay":{"delay":5000}}'>
            <div class="swiper-wrapper">
                <div class="swiper-slide">
                    <div class="image-layer" style="background-image: url({{ asset('landing/assets/images/backgrounds/main-slider-1-1.jpg') }});"></div>
                    <div class="container">
                        <div class="row">
                            <div class="col-xl-12">
                                <div class="main-slider__content">
                                    <p class="main-slider__sub-title">Automatización inteligente para crianza de cuyes</p>
                                    <h2 class="main-slider__title">Arquitectura IoT</h2>
                                    <div class="main-slider__btn-box">
                                        <a href="{{ route('login') }}" class="thm-btn main-slider__btn">
                                            Inicio de sesión <i class="icon-right-arrow"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="swiper-slide">
                    <div class="image-layer" style="background-image: url({{ asset('landing/assets/images/backgrounds/main-slider-1-2.jpg') }});"></div>
                    <div class="container">
                        <div class="row">
                            <div class="col-xl-12">
                                <div class="main-slider__content">
                                    <p class="main-slider__sub-title">Automatización inteligente para crianza de cuyes</p>
                                    <h2 class="main-slider__title">Arquitectura IoT</h2>
                                    <div class="main-slider__btn-box">
                                        <a href="{{ route('login') }}" class="thm-btn main-slider__btn">
                                            Inicio de sesión <i class="icon-right-arrow"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="swiper-slide">
                    <div class="image-layer" style="background-image: url({{ asset('landing/assets/images/backgrounds/main-slider-1-3.jpg') }});"></div>
                    <div class="container">
                        <div class="row">
                            <div class="col-xl-12">
                                <div class="main-slider__content">
                                    <p class="main-slider__sub-title">Automatización inteligente para crianza de cuyes</p>
                                    <h2 class="main-slider__title">Arquitectura IoT</h2>
                                    <div class="main-slider__btn-box">
                                        <a href="{{ route('login') }}" class="thm-btn main-slider__btn">
                                            Inicio de sesión <i class="icon-right-arrow"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="swiper-pagination" id="main-slider-pagination"></div>
            <div class="main-slider__nav">
                <div class="swiper-button-prev" id="main-slider__swiper-button-next"><i class="icon-right-arrow"></i></div>
                <div class="swiper-button-next" id="main-slider__swiper-button-prev"><i class="icon-right-arrow"></i></div>
            </div>
        </div>
    </section>

    <section class="feature-one">
        <div class="container">
            <div class="row">
                <div class="col-xl-4 col-lg-4 col-md-4">
                    <div class="feature-one__single">
                        <div class="feature-one__icon"><span class="fas fa-temperature-low"></span></div>
                        <div class="feature-one__content"><h3 class="feature-one__title">Control térmico <br> automatizado</h3></div>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-4 col-md-4">
                    <div class="feature-one__single">
                        <div class="feature-one__icon"><span class="fas fa-wifi"></span></div>
                        <div class="feature-one__content"><h3 class="feature-one__title">Supervisión remota <br> en tiempo real</h3></div>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-4 col-md-4">
                    <div class="feature-one__single">
                        <div class="feature-one__icon"><span class="fas fa-chart-line"></span></div>
                        <div class="feature-one__content"><h3 class="feature-one__title">Históricos y <br> apoyo a decisiones</h3></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="about-one section-space" id="proyecto">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-xl-6">
                    <div class="about-one__left">
                        <div class="section-title text-left">
                            <span class="section-title__tagline">Sobre el proyecto</span>
                            <h2 class="section-title__title">Arquitectura mecatrónica para módulos inteligentes de crianza de cuyes</h2>
                            <div class="section-title__icon">
                                <img src="{{ asset('landing/assets/images/icon/section-title-icon-1.png') }}" alt="icono">
                            </div>
                        </div>
                        <p class="about-one__text-2 text-justify">
                            La propuesta se orienta al diseño y modelado de un sistema basado en IoT capaz de monitorear variables ambientales críticas dentro de cada módulo de crianza y accionar mecanismos automáticos de ventilación, calefacción y soporte operativo. El enfoque busca transformar la supervisión manual en una gestión continua, trazable y respaldada por datos.
                        </p>
                        <ul class="list-unstyled about-one__points">
                            <li>
                                <div class="icon"><span class="icon-tick"></span></div>
                                <div class="text"><p>Monitoreo en tiempo real de temperatura, humedad y gases.</p></div>
                            </li>
                            <li>
                                <div class="icon"><span class="icon-tick"></span></div>
                                <div class="text"><p>Control autónomo en lazo cerrado para mantener el microclima en rangos seguros.</p></div>
                            </li>
                            <li>
                                <div class="icon"><span class="icon-tick"></span></div>
                                <div class="text"><p>Infraestructura digital con API REST, backend e históricos de operación.</p></div>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="mini-card">
                                <div class="icon-box-custom"><i class="fas fa-microchip"></i></div>
                                <h4>Procesamiento embebido</h4>
                                <p>Uso de ESP32 como núcleo de adquisición, lógica local y conectividad.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mini-card">
                                <div class="icon-box-custom"><i class="fas fa-fan"></i></div>
                                <h4>Actuación inteligente</h4>
                                <p>Ventiladores, calefactores y dosificación según condiciones críticas.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mini-card">
                                <div class="icon-box-custom"><i class="fas fa-database"></i></div>
                                <h4>Gestión de datos</h4>
                                <p>Registro histórico para análisis del comportamiento del microclima.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mini-card">
                                <div class="icon-box-custom"><i class="fas fa-shield-alt"></i></div>
                                <h4>Robustez operativa</h4>
                                <p>Modo seguro ante fallos de conectividad o anomalías del sistema.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="services-one section-space" id="problema">
        <div class="services-one__bg" style="background-image: url({{ asset('landing/assets/images/shapes/services-one-shape-1-dark.png') }});"></div>
        <div class="container">
            <div class="section-title text-center">
                <span class="section-title__tagline">Contexto del problema</span>
                <h2 class="section-title__title">Desafíos que aborda la propuesta</h2>
                <div class="section-title__icon">
                    <img src="{{ asset('landing/assets/images/icon/section-title-icon-1.png') }}" alt="icono">
                </div>
            </div>
            <div class="row g-4">
                <div class="col-xl-6 col-lg-6">
                    <div class="problem-card">
                        <div class="icon-box-custom"><i class="fas fa-thermometer-half"></i></div>
                        <h4>Inestabilidad microclimática</h4>
                        <p>La falta de control automatizado de temperatura, humedad y calidad del aire expone a los animales a condiciones de estrés térmico y riesgo sanitario.</p>
                    </div>
                </div>
                <div class="col-xl-6 col-lg-6">
                    <div class="problem-card">
                        <div class="icon-box-custom"><i class="fas fa-user-clock"></i></div>
                        <h4>Dependencia de la supervisión humana</h4>
                        <p>El operario no puede garantizar vigilancia permanente ni respuesta inmediata ante descensos térmicos o acumulación nocturna de gases.</p>
                    </div>
                </div>
                <div class="col-xl-6 col-lg-6">
                    <div class="problem-card">
                        <div class="icon-box-custom"><i class="fas fa-exclamation-triangle"></i></div>
                        <h4>Limitada trazabilidad</h4>
                        <p>Sin adquisición continua de datos no existe base histórica suficiente para análisis, estadística ni mejora del manejo productivo.</p>
                    </div>
                </div>
                <div class="col-xl-6 col-lg-6">
                    <div class="problem-card">
                        <div class="icon-box-custom"><i class="fas fa-layer-group"></i></div>
                        <h4>Baja escalabilidad tecnológica</h4>
                        <p>Los sistemas tradicionales carecen de modularidad para replicarse en múltiples jaulas o adaptarse a diferentes capacidades de producción.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="healthy-food-one section-space" id="arquitectura">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-xl-6">
                    <div class="healthy-food-one__left">
                        <div class="healthy-food-one__img">
                            <img src="{{ asset('landing/assets/images/resources/healthy-food-one-1.jpg') }}" alt="Arquitectura IoT">
                        </div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="healthy-food-one__right">
                        <div class="section-title text-left">
                            <span class="section-title__tagline">Arquitectura IoT</span>
                            <h2 class="section-title__title">Capas funcionales del sistema</h2>
                            <div class="section-title__icon">
                                <img src="{{ asset('landing/assets/images/icon/section-title-icon-1.png') }}" alt="icono">
                            </div>
                        </div>
                        <p class="healthy-food-one__text text-justify">
                            La solución integra percepción, procesamiento, actuación e interfaz. Los sensores capturan las condiciones del módulo, el controlador ejecuta la lógica de control, los actuadores estabilizan el ambiente y el backend consolida la información para visualización y análisis remoto.
                        </p>
                        <ul class="list-unstyled healthy-food-one__list">
                            <li class="healthy-food-one__single">
                                <div class="healthy-food-one__content">
                                    <div class="healthy-food-one__icon"><span class="fas fa-thermometer-three-quarters"></span></div>
                                    <p class="healthy-food-one__title">Sensores: temperatura, humedad y gases</p>
                                </div>
                            </li>
                            <li class="healthy-food-one__single">
                                <div class="healthy-food-one__content">
                                    <div class="healthy-food-one__icon"><span class="fas fa-microchip"></span></div>
                                    <p class="healthy-food-one__title">ESP32 y lógica local de control</p>
                                </div>
                            </li>
                            <li class="healthy-food-one__single">
                                <div class="healthy-food-one__content">
                                    <div class="healthy-food-one__icon"><span class="fas fa-server"></span></div>
                                    <p class="healthy-food-one__title">API REST, backend y panel de monitoreo</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="counter-one section-space" id="modulos">
        <div class="counter-one__bg" style="background-image: url({{ asset('landing/assets/images/shapes/counter-one-shape-3-dark.png') }});"></div>
        <div class="container">
            <div class="section-title text-center">
                <span class="section-title__tagline">Componentes del proyecto</span>
                <h2 class="section-title__title">Subsistemas principales</h2>
                <div class="section-title__icon">
                    <img src="{{ asset('landing/assets/images/icon/section-title-icon-1.png') }}" alt="icono">
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-3"><div class="metric-box"><h3>01</h3><p>Adquisición de datos sensoriales</p></div></div>
                <div class="col-md-3"><div class="metric-box"><h3>02</h3><p>Comunicación inalámbrica y API REST</p></div></div>
                <div class="col-md-3"><div class="metric-box"><h3>03</h3><p>Control automático de actuadores</p></div></div>
                <div class="col-md-3"><div class="metric-box"><h3>04</h3><p>Backend histórico y monitoreo remoto</p></div></div>
            </div>
        </div>
    </section>

    <section class="project-one section-space" id="beneficios">
        <div class="project-one__bg float-bob-y-2" style="background-image: url({{ asset('landing/assets/images/shapes/project-one-shape-1.png') }});"></div>
        <div class="container">
            <div class="section-title text-center">
                <span class="section-title__tagline">Aportes esperados</span>
                <h2 class="section-title__title">Beneficios técnicos, productivos y sociales</h2>
                <div class="section-title__icon">
                    <img src="{{ asset('landing/assets/images/icon/section-title-icon-1.png') }}" alt="icono">
                </div>
            </div>
            <div class="row">
                <div class="col-xl-3 col-lg-6 col-md-6 wow fadeInUp" data-wow-delay="100ms">
                    <div class="project-one__single">
                        <div class="project-one__inner">
                            <div class="project-one__img"><img src="{{ asset('landing/assets/images/project/project-one-1.jpg') }}" alt="Estabilidad ambiental"></div>
                            <div class="project-one__content">
                                <span class="project-one__tagline">Técnico</span>
                                <h3 class="project-one__title">Mejora en la estabilidad ambiental del módulo</h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-md-6 wow fadeInUp" data-wow-delay="200ms">
                    <div class="project-one__single">
                        <div class="project-one__inner">
                            <div class="project-one__img"><img src="{{ asset('landing/assets/images/project/project-one-2.jpg') }}" alt="Optimización energética"></div>
                            <div class="project-one__content">
                                <span class="project-one__tagline">Operativo</span>
                                <h3 class="project-one__title">Optimización energética y reducción de intervención manual</h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-md-6 wow fadeInUp" data-wow-delay="300ms">
                    <div class="project-one__single">
                        <div class="project-one__inner">
                            <div class="project-one__img"><img src="{{ asset('landing/assets/images/project/project-one-3.jpg') }}" alt="Escalabilidad"></div>
                            <div class="project-one__content">
                                <span class="project-one__tagline">Diseño</span>
                                <h3 class="project-one__title">Sistema modular y replicable en múltiples unidades</h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-md-6 wow fadeInUp" data-wow-delay="400ms">
                    <div class="project-one__single">
                        <div class="project-one__inner">
                            <div class="project-one__img"><img src="{{ asset('landing/assets/images/project/project-one-4.jpg') }}" alt="Modernización"></div>
                            <div class="project-one__content">
                                <span class="project-one__tagline">Impacto</span>
                                <h3 class="project-one__title">Contribución tecnológica al sector pecuario local</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-one" id="contacto">
        <div class="cta-one__bg" data-jarallax data-speed="0.2" data-imgPosition="50% 0%" style="background-image: url({{ asset('landing/assets/images/backgrounds/cta-one-bg.jpg') }});"></div>
        <div class="container">
            <div class="row">
                <div class="col-xl-12">
                    <div class="cta-one__inner">
                        <div class="cta-one__left">
                            <div class="cta-one__icon"><span class="icon-agriculture-2"></span></div>
                            <h3 class="cta-one__title">Proyecto académico orientado a la automatización integral <br> y el monitoreo inteligente de la crianza de cuyes.</h3>
                        </div>
                        <div class="cta-one__right">
                            <a href=" " class="thm-btn cta-one__btn">Ir al panel <i class="icon-right-arrow"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="site-footer">
        <div class="site-footer__top">
            <div class="container">
                <div class="site-footer__top-inner">
                    <div class="row">
                        <div class="col-xl-4 col-lg-6 col-md-6">
                            <div class="footer-widget__column footer-widget__about">
                                <div class="footer-widget__logo">
                                    <a href="#inicio"><img src="{{ asset('landing/assets/images/resources/footer-logo.png') }}" alt="MecaCuy"></a>
                                </div>
                                <div class="footer-widget__about-text-box">
                                    <p class="footer-widget__about-text">MecaCuy es una propuesta de arquitectura mecatrónica basada en IoT para optimizar el control ambiental y operativo en módulos de crianza de cuyes.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="footer-widget__column footer-widget__Explore">
                                <div class="footer-widget__title-box"><h3 class="footer-widget__title">Secciones</h3></div>
                                <ul class="footer-widget__Explore-list list-unstyled">
                                    <li><a href="#proyecto">Proyecto</a></li>
                                    <li><a href="#problema">Problema</a></li>
                                    <li><a href="#arquitectura">Arquitectura IoT</a></li>
                                    <li><a href="#modulos">Módulos</a></li>
                                    <li><a href="#beneficios">Beneficios</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-xl-5 col-lg-12 col-md-12">
                            <div class="footer-widget__column footer-widget__Contact">
                                <div class="footer-widget__title-box"><h3 class="footer-widget__title">Datos del proyecto</h3></div>
                                <ul class="footer-widget__Contact-list list-unstyled">
                                    <li>
                                        <div class="icon"><span class="fas fa-user-graduate"></span></div>
                                        <div class="text"><p>Postulante</p><h5>Univ. Janneth Choque Quispe</h5></div>
                                    </li>
                                    <li>
                                        <div class="icon"><span class="fas fa-chalkboard-teacher"></span></div>
                                        <div class="text"><p>Tutor</p><h5>Msc. Ing. Jaime Eduardo Sánchez Guzmán</h5></div>
                                    </li>
                                    <li>
                                        <div class="icon"><span class="fas fa-university"></span></div>
                                        <div class="text"><p>Institución</p><h5>UMSA · Ingeniería Mecatrónica</h5></div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="site-footer__bottom">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="site-footer__bottom-inner">
                            <p class="site-footer__bottom-text">© {{ date('Y') }} MecaCuy · Proyecto de grado</p>
                            <div class="site-footer__bottom-scroll">
                                <a href="#" data-target="html" class="scroll-to-target scroll-to-top"><i class="icon-up-arrow"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</div>

<div class="mobile-nav__wrapper">
    <div class="mobile-nav__overlay mobile-nav__toggler"></div>
    <div class="mobile-nav__content">
        <span class="mobile-nav__close mobile-nav__toggler"><i class="fa fa-times"></i></span>
        <div class="logo-box">
            <a href="#inicio" aria-label="logo image"><img src="{{ asset('landing/assets/images/resources/logo-2.png') }}" width="122" alt="MecaCuy" /></a>
        </div>
        <div class="mobile-nav__container"></div>
    </div>
</div>

<script src="{{ asset('landing/assets/vendors/jquery/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('landing/assets/vendors/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('landing/assets/vendors/jarallax/jarallax.min.js') }}"></script>
<script src="{{ asset('landing/assets/vendors/jquery-ajaxchimp/jquery.ajaxchimp.min.js') }}"></script>
<script src="{{ asset('landing/assets/vendors/jquery-appear/jquery.appear.min.js') }}"></script>
<script src="{{ asset('landing/assets/vendors/jquery-circle-progress/jquery.circle-progress.min.js') }}"></script>
<script src="{{ asset('landing/assets/vendors/jquery-magnific-popup/jquery.magnific-popup.min.js') }}"></script>
<script src="{{ asset('landing/assets/vendors/odometer/odometer.min.js') }}"></script>
<script src="{{ asset('landing/assets/vendors/swiper/swiper.min.js') }}"></script>
<script src="{{ asset('landing/assets/vendors/wow/wow.js') }}"></script>
<script src="{{ asset('landing/assets/vendors/owl-carousel/owl.carousel.min.js') }}"></script>
<script src="{{ asset('landing/assets/js/agrion.js') }}"></script>
</body>
</html>

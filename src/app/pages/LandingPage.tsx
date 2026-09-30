import '../../styles/hide-scrollbar.css';
import { Link } from 'react-router-dom';
import { useEffect, useState } from 'react';

import {
  Sprout,
  Fish,
  TrendingUp,
  Users,
  Shield,
  BarChart3,
  Brain,
  CheckCircle2,
  Menu,
  X,
  ShoppingCart,
  ArrowUpRight,
  Search,
  ClipboardList,
  Truck,
} from 'lucide-react';

import { Button } from '../components/ui/button';
import { Card, CardContent } from '../components/ui/card';
import { ImageWithFallback } from '../components/figma/ImageWithFallback';


export default function LandingPage() {
  const [menuOpen, setMenuOpen] = useState(false);
  const [activeWorkflowStep, setActiveWorkflowStep] = useState(1);

  const workflowSteps = [
    {
      id: 1,
      image: '/Images/Landing Hero 1.png',
      imageAlt: 'HarborAI product browsing and marketplace workflow',
    },
    {
      id: 2,
      image: '/Images/Landing Hero 2.png',
      imageAlt: 'HarborAI verified producer ordering workflow',
    },
    {
      id: 3,
      image: '/Images/Landing Hero 3.png',
      imageAlt: 'HarborAI order tracking and delivery workflow',
    },
  ];

  useEffect(() => {
    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setMenuOpen(false);
      }
    };

    document.addEventListener('keydown', handleEscape);

    return () => {
      document.removeEventListener('keydown', handleEscape);
    };
  }, []);


  useEffect(() => {
    document.body.style.overflow = menuOpen ? 'hidden' : '';

    return () => {
      document.body.style.overflow = '';
    };
  }, [menuOpen]);

  return (
    <div
      className="min-h-screen bg-[#F5F1E5] font-body text-[#123C5C] scrollbar-hide"
      style={{ overflowX: 'hidden' }}
    >

      {/* =========================================================
          CUSTOM FONTS + ANIMATIONS
      ========================================================= */}

      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Anton&family=Baloo+2:wght@400;500;600;700;800&display=swap');

        .font-display {
          font-family: 'Anton', Impact, ui-sans-serif, sans-serif;
        }

        .font-body {
          font-family: 'Baloo 2', ui-rounded, system-ui, sans-serif;
        }

        .hero-bg {
          animation: heroZoom 18s ease-in-out infinite alternate;
        }

        @keyframes heroZoom {
          0% {
            transform: scale(1);
          }

          100% {
            transform: scale(1.045);
          }
        }

        .hero-content {
          animation: heroContentIn 0.9s ease-out both;
        }

        @keyframes heroContentIn {
          0% {
            opacity: 0;
            transform: translateY(25px);
          }

          100% {
            opacity: 1;
            transform: translateY(0);
          }
        }

        .hero-kicker {
          animation: heroKickerIn 0.8s ease-out 0.15s both;
        }

        @keyframes heroKickerIn {
          0% {
            opacity: 0;
            transform: translateY(15px);
          }

          100% {
            opacity: 1;
            transform: translateY(0);
          }
        }

        .hero-title {
          text-shadow:
            0 4px 0 rgba(18, 60, 92, 0.12),
            0 8px 25px rgba(0, 0, 0, 0.20);
        }

        .hero-button {
          transition:
            transform 0.2s ease,
            box-shadow 0.2s ease;
        }

        .hero-button:hover {
          transform: translateY(-3px);
        }

        .floating-badge {
          animation: floatingBadge 4s ease-in-out infinite;
        }

        @keyframes floatingBadge {
          0%,
          100% {
            transform: translateY(0);
          }

          50% {
            transform: translateY(-7px);
          }
        }

        .section-heading {
          letter-spacing: -0.02em;
        }

        .workflow-image {
          animation: workflowFloat 6s ease-in-out infinite;
        }

        .workflow-image-layer {
          transition: opacity 450ms ease, transform 450ms ease;
          will-change: opacity, transform;
        }

        .workflow-step {
          transition: transform 200ms ease, opacity 200ms ease;
        }

        .workflow-step:hover {
          transform: translateX(4px);
        }

        @media (prefers-reduced-motion: reduce) {
          .workflow-image,
          .workflow-image-layer,
          .workflow-step {
            animation: none !important;
            transition: none !important;
          }
        }

        @keyframes workflowFloat {
          0%,
          100% {
            transform: translateY(0);
          }

          50% {
            transform: translateY(-8px);
          }
        }
      `}</style>


      {/* =========================================================
          NAVIGATION
      ========================================================= */}

      <nav className="fixed top-0 left-0 w-full z-50 bg-[#F5F1E5]/95 backdrop-blur-md border-b border-[#E7E1D0]">

        <div className="container mx-auto px-4 sm:px-6 lg:px-8">

          <div className="h-16 md:h-20 flex items-center justify-between">

            {/* LOGO */}

            <Link
              to="/"
              className="flex items-center gap-2 sm:gap-3 min-w-0"
              onClick={() => setMenuOpen(false)}
            >

              <img
                src="/logo.png"
                alt="HarborAI Logo"
                className="w-9 h-9 sm:w-11 sm:h-11 object-contain"
              />

              <span className="font-display text-xl sm:text-2xl text-[#123C5C] whitespace-nowrap tracking-tight">
                HarborAI
              </span>

            </Link>


            {/* DESKTOP NAVIGATION */}

            <div className="hidden xl:flex items-center gap-5 2xl:gap-7">

              <a
                href="#features"
                className="text-sm font-bold uppercase tracking-wide text-[#315D35] hover:text-[#22C55E] transition-colors"
              >
                Features
              </a>

              <a
                href="#how-it-works"
                className="text-sm font-bold uppercase tracking-wide text-[#315D35] hover:text-[#22C55E] transition-colors"
              >
                How It Works
              </a>

              <a
                href="#benefits"
                className="text-sm font-bold uppercase tracking-wide text-[#315D35] hover:text-[#22C55E] transition-colors"
              >
                Benefits
              </a>

              <Link
                to="/programs"
                className="text-sm font-bold uppercase tracking-wide text-[#315D35] hover:text-[#22C55E] transition-colors"
              >
                Programs
              </Link>

              <Link
                to="/faq"
                className="text-sm font-bold uppercase tracking-wide text-[#315D35] hover:text-[#22C55E] transition-colors"
              >
                FAQ
              </Link>

              <a
                href="#about"
                className="text-sm font-bold uppercase tracking-wide text-[#315D35] hover:text-[#22C55E] transition-colors"
              >
                About
              </a>

            </div>


            {/* DESKTOP AUTH + NOTIFICATIONS */}

            <div className="hidden xl:flex items-center gap-3">

              <Link to="/login">
                <Button
                  variant="outline"
                  className="
                    font-bold
                    rounded-xl
                    border-2
                    border-[#315D35]
                    text-[#315D35]
                    hover:bg-[#315D35]
                    hover:text-white
                    transition-all
                    duration-200
                  "
                >
                  Login
                </Button>
              </Link>

              <Link to="/register">
                <Button
                  className="
                    font-bold
                    rounded-xl
                    bg-[#FBBE24]
                    hover:bg-[#F59E0B]
                    text-[#123C5C]
                    shadow-sm
                    transition-all
                    duration-200
                  "
                >
                  Register as Producer
                </Button>
              </Link>

            </div>


            {/* MOBILE ACTIONS */}

            <div className="ml-auto flex items-center gap-1.5 xl:hidden">
              <button
                type="button"
                className="
                  flex
                  min-h-11
                  min-w-11
                  items-center
                  justify-center
                  rounded-xl
                  p-2
                  text-[#123C5C]
                  transition-colors
                  hover:bg-[#123C5C]/10
                  focus:outline-none
                  focus:ring-2
                  focus:ring-[#22C55E]/40
                  focus:ring-offset-2
                "
                aria-label="Toggle navigation menu"
                aria-expanded={menuOpen}
                onClick={() => {
                  
                  setMenuOpen((value) => !value);
                }}
              >
                {menuOpen ? (
                  <X className="h-7 w-7" />
                ) : (
                  <Menu className="h-7 w-7" />
                )}
              </button>
            </div>

          </div>


          {/* MOBILE MENU BACKDROP */}

          {menuOpen && (
            <button
              type="button"
              aria-label="Close navigation menu"
              className="fixed inset-0 top-16 z-[-1] bg-[#123C5C]/20 backdrop-blur-[2px] xl:hidden md:top-20"
              onClick={() => setMenuOpen(false)}
            />
          )}

          {/* MOBILE MENU */}

          {menuOpen && (

            <div
              className="
                xl:hidden
                absolute
                left-0
                right-0
                top-full
                max-h-[calc(100vh-4rem)]
                overflow-y-auto
                border-t
                border-[#E7E1D0]
                bg-[#F5F1E5]
                px-4
                py-4
                shadow-xl
                flex
                flex-col
                gap-1
                sm:px-6
              "
            >

              <a
                href="#features"
                onClick={() => setMenuOpen(false)}
                className="px-3 py-3.5 rounded-lg font-bold uppercase text-sm text-[#315D35] hover:bg-white"
              >
                Features
              </a>

              <a
                href="#how-it-works"
                onClick={() => setMenuOpen(false)}
                className="px-3 py-3.5 rounded-lg font-bold uppercase text-sm text-[#315D35] hover:bg-white"
              >
                How It Works
              </a>

              <a
                href="#benefits"
                onClick={() => setMenuOpen(false)}
                className="px-3 py-3.5 rounded-lg font-bold uppercase text-sm text-[#315D35] hover:bg-white"
              >
                Benefits
              </a>

              <Link
                to="/programs"
                onClick={() => setMenuOpen(false)}
                className="px-3 py-3.5 rounded-lg font-bold uppercase text-sm text-[#315D35] hover:bg-white"
              >
                Programs
              </Link>

              <Link
                to="/faq"
                onClick={() => setMenuOpen(false)}
                className="px-3 py-3.5 rounded-lg font-bold uppercase text-sm text-[#315D35] hover:bg-white"
              >
                FAQ
              </Link>

              <a
                href="#about"
                onClick={() => setMenuOpen(false)}
                className="px-3 py-3.5 rounded-lg font-bold uppercase text-sm text-[#315D35] hover:bg-white"
              >
                About
              </a>


              <div className="mt-3 flex flex-col gap-2 px-2">
                <Link to="/login">
                  <Button
                    variant="outline"
                    className="
                      w-full
                      rounded-xl
                      font-bold
                      border-2
                      border-[#315D35]
                      text-[#315D35]
                    "
                    onClick={() => {
                      setMenuOpen(false);
                      }}
                  >
                    Login
                  </Button>
                </Link>

                <Link to="/register">
                  <Button
                    className="
                      w-full
                      rounded-xl
                      font-bold
                      bg-[#FBBE24]
                      hover:bg-[#F59E0B]
                      text-[#123C5C]
                    "
                    onClick={() => {
                      setMenuOpen(false);
                      }}
                  >
                    Register as Producer
                  </Button>
                </Link>
              </div>

            </div>

          )}

        </div>

      </nav>


      {/* =========================================================
          HERO SECTION
      ========================================================= */}

      <section className="relative min-h-[calc(100svh-64px)] md:min-h-[calc(100svh-80px)] overflow-hidden">

        {/* Background */}

        <img
          src="/Images/landing-hero-aparri.png"
          alt="Aparri agricultural landscape"
          className="absolute inset-0 h-full w-full object-cover hero-bg"
        />


        {/* Readability overlays */}

        <div className="absolute inset-0 bg-white/25" />

        <div className="absolute inset-0 bg-gradient-to-b from-white/20 via-white/10 to-[#123C5C]/45" />


        {/* Hero Content */}

        <div className="relative z-10 flex min-h-[calc(100svh-64px)] md:min-h-[calc(100svh-80px)] items-start justify-center px-4 pt-20 sm:px-8 md:pt-24 lg:pt-28">

          <div className="w-full max-w-7xl text-center hero-content">

            {/* Location Badge */}

            <div className="mb-5 inline-flex items-center gap-2 rounded-full bg-[#4D8B35]/95 px-6 py-3 shadow-lg backdrop-blur-sm hero-kicker">

              <Sprout className="h-5 w-5 text-white" />

              <span
                className="text-xs font-black uppercase tracking-[0.18em] text-white sm:text-sm"
                style={{ fontFamily: "'Baloo 2', sans-serif" }}
              >
                From the Heart of Aparri
              </span>

            </div>


            {/* HEADLINE */}

            <div className="mx-auto w-full max-w-[1250px] text-center">

              <div className="pb-5 sm:pb-6 md:pb-7">

                <h2
                  className="
                    m-0
                    px-2
                    font-black
                    italic
                    uppercase
                    leading-[0.95]
                    tracking-[-0.025em]
                    text-[#3F7F2F]
                    text-[2.35rem]
                    sm:text-5xl
                    md:text-6xl
                    lg:text-7xl
                    xl:text-8xl
                  "
                  style={{
                    fontFamily: "'Anton', sans-serif",
                    textShadow: "0 2px 10px rgba(255,255,255,0.35)",
                  }}
                >
                  ANI AT HULI NG APARRI
                </h2>

              </div>


              <div>

                <h1
                  className="
                    m-0
                    px-2
                    font-black
                    italic
                    uppercase
                    leading-[0.86]
                    tracking-[0.01em]
                    text-[#123C5C]
                    text-[3.6rem]
                    sm:text-[5.3rem]
                    md:text-[6.8rem]
                    lg:text-[8.2rem]
                    xl:text-[9.5rem]
                    whitespace-normal
                    lg:whitespace-nowrap
                  "
                  style={{
                    fontFamily: "'Anton', sans-serif",
                    textShadow: "0 4px 18px rgba(255,255,255,0.35)",
                  }}
                >
                  DIRETSO SA’YO!
                </h1>

              </div>


              {/* Decorative underline */}

              <div
                className="
                  mx-auto
                  mt-5
                  h-2
                  w-48
                  rounded-full
                  bg-[#3F7F2F]
                  sm:w-56
                  md:w-64
                "
              />

            </div>


            {/* Description */}

            <p
              className="
                mx-auto
                mt-7
                max-w-2xl
                px-4
                text-base
                font-semibold
                leading-relaxed
                text-[#123C5C]
                sm:text-lg
                md:text-xl
              "
              style={{
                fontFamily: "'Baloo 2', sans-serif",
                textShadow: "0 1px 8px rgba(255,255,255,0.6)",
              }}
            >
              Fresh agricultural and fishery products from local
              farmers and fisherfolk in Aparri — straight to your home.
            </p>


            {/* CTA */}

            <div className="mt-8 flex flex-col items-stretch justify-center gap-3 px-2 sm:flex-row sm:items-center sm:px-0 sm:gap-4">

              <Link to="/login">

                <Button
                  className="
                    w-full
                    h-14
                    rounded-xl
                    bg-[#FBBE24]
                    px-8
                    text-base
                    font-black
                    uppercase
                    tracking-wide
                    text-[#123C5C]
                    shadow-lg
                    transition-all
                    duration-200
                    hover:-translate-y-1
                    hover:bg-[#F59E0B]
                    hover:shadow-xl
                    hero-button
                  "
                >
                  <ShoppingCart className="mr-2 h-5 w-5" />
                  ORDER NA!
                </Button>

              </Link>


              <Link to="/programs">

                <Button
                  className="
                    w-full
                    h-14
                    rounded-xl
                    bg-[#123C5C]
                    px-8
                    text-base
                    font-black
                    uppercase
                    tracking-wide
                    text-white
                    shadow-lg
                    transition-all
                    duration-200
                    hover:-translate-y-1
                    hover:bg-[#0E2A44]
                    hover:shadow-xl
                    hero-button
                  "
                >
                  <TrendingUp className="mr-2 h-5 w-5" />
                  PRICE TRACKER
                </Button>

              </Link>

            </div>


            {/* Trust badges */}

            <div className="mt-7 flex flex-wrap justify-center gap-3 pb-8 sm:pb-10 md:pb-12">

              <div className="flex items-center gap-2 rounded-full bg-white/90 px-5 py-2.5 shadow-md backdrop-blur-sm">

                <CheckCircle2 className="h-4 w-4 text-[#22C55E]" />

                <span className="text-sm font-bold text-[#123C5C]">
                  Verified Producers
                </span>

              </div>


              <div className="flex items-center gap-2 rounded-full bg-white/90 px-5 py-2.5 shadow-md backdrop-blur-sm">

                <Fish className="h-4 w-4 text-[#0F9488]" />

                <span className="text-sm font-bold text-[#123C5C]">
                  Agri-Fish Trading
                </span>

              </div>


              <div className="flex items-center gap-2 rounded-full bg-white/90 px-5 py-2.5 shadow-md backdrop-blur-sm">

                <BarChart3 className="h-4 w-4 text-[#123C5C]" />

                <span className="text-sm font-bold text-[#123C5C]">
                  AI-Powered
                </span>

              </div>

            </div>

          </div>

        </div>


        {/* Bottom landscape fade */}

        <div className="absolute bottom-0 left-0 right-0 z-10 h-24 bg-gradient-to-t from-[#123C5C]/50 to-transparent" />

      </section>


      {/* =========================================================
          FEATURES SECTION
      ========================================================= */}

      <section
        id="features"
        className="
          bg-white
          py-20
          sm:py-24
          scroll-mt-20
          md:scroll-mt-24
        "
      >

        <div className="container mx-auto px-4">

          <div className="text-center mb-14 md:mb-16">

            <p
              className="
                text-[#4D8B35]
                font-bold
                uppercase
                tracking-[0.18em]
                text-sm
                mb-3
              "
            >
              What HarborAI Offers
            </p>

            <h2
              className="
                font-display
                text-4xl
                sm:text-5xl
                md:text-6xl
                text-[#123C5C]
                mb-4
                section-heading
              "
            >
              PLATFORM FEATURES
            </h2>

            <p
              className="
                text-lg
                text-[#45586B]
                max-w-2xl
                mx-auto
              "
            >
              Built to support the entire agricultural and fishery
              value chain in Aparri, Cagayan.
            </p>

          </div>


          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6">

            {/* FEATURE 1 */}

            <Card
              className="
                group
                rounded-2xl
                border-2
                border-[#E7E1D0]
                hover:border-[#22C55E]
                hover:-translate-y-1
                shadow-sm
                hover:shadow-lg
                transition-all
                duration-300
                bg-white
              "
            >

              <CardContent className="p-6">

                <div
                  className="
                    w-14
                    h-14
                    bg-[#22C55E]
                    rounded-2xl
                    flex
                    items-center
                    justify-center
                    mb-4
                    rotate-3
                    group-hover:rotate-0
                    transition-transform
                  "
                >
                  <Brain className="w-7 h-7 text-white" />
                </div>

                <h3 className="text-xl font-bold text-[#123C5C] mb-2">
                  AI-Driven Recommendations
                </h3>

                <p className="text-[#45586B]">
                  Get personalized enterprise opportunities,
                  pricing guidance, and market timing suggestions
                  powered by AI.
                </p>

              </CardContent>

            </Card>


            {/* FEATURE 2 */}

            <Card
              className="
                group
                rounded-2xl
                border-2
                border-[#E7E1D0]
                hover:border-[#0F9488]
                hover:-translate-y-1
                shadow-sm
                hover:shadow-lg
                transition-all
                duration-300
                bg-white
              "
            >

              <CardContent className="p-6">

                <div
                  className="
                    w-14
                    h-14
                    bg-[#0F9488]
                    rounded-2xl
                    flex
                    items-center
                    justify-center
                    mb-4
                    rotate-3
                    group-hover:rotate-0
                    transition-transform
                  "
                >
                  <Shield className="w-7 h-7 text-white" />
                </div>

                <h3 className="text-xl font-bold text-[#123C5C] mb-2">
                  Government Program Matching
                </h3>

                <p className="text-[#45586B]">
                  Discover government support programs you
                  qualify for through eligibility matching.
                </p>

              </CardContent>

            </Card>


            {/* FEATURE 3 */}

            <Card
              className="
                group
                rounded-2xl
                border-2
                border-[#E7E1D0]
                hover:border-[#8B27C7]
                hover:-translate-y-1
                shadow-sm
                hover:shadow-lg
                transition-all
                duration-300
                bg-white
              "
            >

              <CardContent className="p-6">

                <div
                  className="
                    w-14
                    h-14
                    bg-[#8B27C7]
                    rounded-2xl
                    flex
                    items-center
                    justify-center
                    mb-4
                    rotate-3
                    group-hover:rotate-0
                    transition-transform
                  "
                >
                  <Users className="w-7 h-7 text-white" />
                </div>

                <h3 className="text-xl font-bold text-[#123C5C] mb-2">
                  Institutional Trading
                </h3>

                <p className="text-[#45586B]">
                  Connect directly with LGUs, DA/BFAR, and
                  Kadiwa outlets for transparent procurement.
                </p>

              </CardContent>

            </Card>


            {/* FEATURE 4 */}

            <Card
              className="
                group
                rounded-2xl
                border-2
                border-[#E7E1D0]
                hover:border-[#22C55E]
                hover:-translate-y-1
                shadow-sm
                hover:shadow-lg
                transition-all
                duration-300
                bg-white
              "
            >

              <CardContent className="p-6">

                <div
                  className="
                    w-14
                    h-14
                    bg-[#22C55E]
                    rounded-2xl
                    flex
                    items-center
                    justify-center
                    mb-4
                    rotate-3
                    group-hover:rotate-0
                    transition-transform
                  "
                >
                  <TrendingUp className="w-7 h-7 text-white" />
                </div>

                <h3 className="text-xl font-bold text-[#123C5C] mb-2">
                  Price & Demand Forecasting
                </h3>

                <p className="text-[#45586B]">
                  Make informed decisions with AI-assisted
                  pricing prompts and demand predictions.
                </p>

              </CardContent>

            </Card>


            {/* FEATURE 5 */}

            <Card
              className="
                group
                rounded-2xl
                border-2
                border-[#E7E1D0]
                hover:border-[#0F9488]
                hover:-translate-y-1
                shadow-sm
                hover:shadow-lg
                transition-all
                duration-300
                bg-white
              "
            >

              <CardContent className="p-6">

                <div
                  className="
                    w-14
                    h-14
                    bg-[#0F9488]
                    rounded-2xl
                    flex
                    items-center
                    justify-center
                    mb-4
                    rotate-3
                    group-hover:rotate-0
                    transition-transform
                  "
                >
                  <BarChart3 className="w-7 h-7 text-white" />
                </div>

                <h3 className="text-xl font-bold text-[#123C5C] mb-2">
                  Digital Marketplace
                </h3>

                <p className="text-[#45586B]">
                  List products, track orders, and manage
                  transactions in one secure platform.
                </p>

              </CardContent>

            </Card>


            {/* FEATURE 6 */}

            <Card
              className="
                group
                rounded-2xl
                border-2
                border-[#E7E1D0]
                hover:border-[#8B27C7]
                hover:-translate-y-1
                shadow-sm
                hover:shadow-lg
                transition-all
                duration-300
                bg-white
              "
            >

              <CardContent className="p-6">

                <div
                  className="
                    w-14
                    h-14
                    bg-[#8B27C7]
                    rounded-2xl
                    flex
                    items-center
                    justify-center
                    mb-4
                    rotate-3
                    group-hover:rotate-0
                    transition-transform
                  "
                >
                  <Fish className="w-7 h-7 text-white" />
                </div>

                <h3 className="text-xl font-bold text-[#123C5C] mb-2">
                  Enterprise Profiling
                </h3>

                <p className="text-[#45586B]">
                  Create comprehensive profiles showcasing
                  agricultural or fishery enterprise capabilities.
                </p>

              </CardContent>

            </Card>

          </div>

        </div>

      </section>


      {/* =========================================================
          HOW HARBORAI WORKS
      ========================================================= */}

      <section
        id="how-it-works"
        className="
          relative
          overflow-hidden
          bg-[#F5F1E5]
          py-20
          sm:py-24
          md:py-28
        "
      >

        {/* Decorative background shapes */}

        <div
          className="
            pointer-events-none
            absolute
            -left-24
            top-20
            h-64
            w-64
            rounded-full
            bg-[#22C55E]/10
            blur-3xl
          "
        />

        <div
          className="
            pointer-events-none
            absolute
            -right-24
            bottom-10
            h-72
            w-72
            rounded-full
            bg-[#0F9488]/10
            blur-3xl
          "
        />


        <div className="relative container mx-auto px-4 sm:px-6 lg:px-8">

          <div className="grid items-center gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16">


            {/* =====================================================
                LEFT VISUAL
                FULL IMAGE — NO CONTAINER
            ===================================================== */}

            <div className="relative order-2 lg:order-1">

              {/* Soft glow behind illustration */}

              <div
                className="
                  pointer-events-none
                  absolute
                  left-1/2
                  top-1/2
                  -translate-x-1/2
                  -translate-y-1/2
                  h-[75%]
                  w-[75%]
                  rounded-full
                  bg-[#22C55E]/10
                  blur-3xl
                "
              />


              {/* DYNAMIC WORKFLOW ILLUSTRATION */}

              <div className="relative flex w-full items-center justify-center">

                <div className="relative w-full max-w-[760px]">

                  {/*
                    The first image keeps the wrapper's natural height.
                    The three actual images are layered on top of it so
                    switching steps can fade smoothly without layout shift.
                  */}
                  <img
                    src="/Images/Landing Hero 1.png"
                    alt=""
                    aria-hidden="true"
                    className="
                      invisible
                      block
                      h-auto
                      w-full
                      object-contain
                    "
                  />

                  {workflowSteps.map((step) => (
                    <img
                      key={step.id}
                      src={step.image}
                      alt={step.imageAlt}
                      className={`
                        workflow-image-layer
                        workflow-image
                        pointer-events-none
                        absolute
                        inset-0
                        z-10
                        h-full
                        w-full
                        object-contain
                        drop-shadow-[0_25px_35px_rgba(18,60,92,0.18)]
                        ${
                          activeWorkflowStep === step.id
                            ? 'opacity-100 scale-100'
                            : 'opacity-0 scale-[0.985]'
                        }
                      `}
                    />
                  ))}

                </div>

              </div>

            </div>


            {/* =====================================================
                RIGHT WORKFLOW
            ===================================================== */}

            <div className="order-1 lg:order-2">

              {/* Section label */}

              <p
                className="
                  mb-3
                  text-sm
                  font-black
                  uppercase
                  tracking-[0.18em]
                  text-[#4D8B35]
                "
              >
                Simple. Direct. Transparent.
              </p>


              {/* Main heading */}

              <h2
                className="
                  font-display
                  text-5xl
                  uppercase
                  leading-[0.95]
                  tracking-tight
                  text-[#123C5C]
                  sm:text-6xl
                  md:text-7xl
                "
              >
                PAANO GUMAGANA?
              </h2>


              <p
                className="
                  mt-5
                  max-w-xl
                  text-lg
                  leading-relaxed
                  text-[#45586B]
                  sm:text-xl
                "
              >
                I-connect ang local producers at institutional buyers
                sa isang simple at transparent na digital workflow.
              </p>


              {/* ===================================================
                  WORKFLOW STEPS
              =================================================== */}

              <div className="relative mt-10">

                {/* Vertical connecting line */}

                <div
                  className="
                    absolute
                    left-[24px]
                    top-12
                    bottom-12
                    w-[3px]
                    bg-[#D8D3C4]
                  "
                />


                {/* STEP 1 */}

                <button
                  type="button"
                  onMouseEnter={() => setActiveWorkflowStep(1)}
                  onFocus={() => setActiveWorkflowStep(1)}
                  onClick={() => setActiveWorkflowStep(1)}
                  className={`workflow-step relative flex w-full cursor-pointer gap-5 pb-8 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FBBE24]/60 focus-visible:ring-offset-4 ${
                    activeWorkflowStep === 1 ? 'opacity-100' : 'opacity-85'
                  }`}
                  aria-pressed={activeWorkflowStep === 1}
                  aria-label="Step 1: Tingnan ang mga produkto"
                >

                  <div
                    className={`
                      relative
                      z-10
                      flex
                      h-12
                      w-12
                      flex-shrink-0
                      items-center
                      justify-center
                      rounded-full
                      border-4
                      border-[#F5F1E5]
                      bg-[#FBBE24]
                      shadow-md
                      transition-all
                      duration-200
                      ${
                        activeWorkflowStep === 1
                          ? 'scale-110 shadow-lg ring-4 ring-[#FBBE24]/20'
                          : 'scale-100'
                      }
                    `}
                  >
                    <Search className="h-5 w-5 text-[#123C5C]" />
                  </div>


                  <div className="pt-1">

                    <h3
                      className="
                        text-xl
                        font-black
                        uppercase
                        leading-tight
                        text-[#123C5C]
                        sm:text-2xl
                      "
                    >
                      TINGNAN ANG MGA PRODUKTO
                    </h3>

                    <p
                      className="
                        mt-1
                        text-base
                        leading-relaxed
                        text-[#8B7F72]
                        sm:text-lg
                      "
                    >
                      Browse verified local farmers and fisherfolk,
                      then check available products and prices.
                    </p>

                  </div>

                </button>


                {/* STEP 2 */}

                <button
                  type="button"
                  onMouseEnter={() => setActiveWorkflowStep(2)}
                  onFocus={() => setActiveWorkflowStep(2)}
                  onClick={() => setActiveWorkflowStep(2)}
                  className={`workflow-step relative flex w-full cursor-pointer gap-5 pb-8 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8B5A2B]/50 focus-visible:ring-offset-4 ${
                    activeWorkflowStep === 2 ? 'opacity-100' : 'opacity-85'
                  }`}
                  aria-pressed={activeWorkflowStep === 2}
                  aria-label="Step 2: Mag-order sa verified producer"
                >

                  <div
                    className={`
                      relative
                      z-10
                      flex
                      h-12
                      w-12
                      flex-shrink-0
                      items-center
                      justify-center
                      rounded-full
                      border-4
                      border-[#F5F1E5]
                      bg-[#8B5A2B]
                      shadow-md
                      transition-all
                      duration-200
                      ${
                        activeWorkflowStep === 2
                          ? 'scale-110 shadow-lg ring-4 ring-[#8B5A2B]/20'
                          : 'scale-100'
                      }
                    `}
                  >
                    <ClipboardList className="h-5 w-5 text-white" />
                  </div>


                  <div className="pt-1">

                    <h3
                      className="
                        text-xl
                        font-black
                        uppercase
                        leading-tight
                        text-[#123C5C]
                        sm:text-2xl
                      "
                    >
                      MAG-ORDER SA VERIFIED PRODUCER
                    </h3>

                    <p
                      className="
                        mt-1
                        text-base
                        leading-relaxed
                        text-[#8B7F72]
                        sm:text-lg
                      "
                    >
                      Piliin ang produkto, ilagay ang quantity,
                      delivery date, at shipping address bago mag-order.
                    </p>

                  </div>

                </button>


                {/* STEP 3 */}

                <button
                  type="button"
                  onMouseEnter={() => setActiveWorkflowStep(3)}
                  onFocus={() => setActiveWorkflowStep(3)}
                  onClick={() => setActiveWorkflowStep(3)}
                  className={`workflow-step relative flex w-full cursor-pointer gap-5 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1F7A2E]/50 focus-visible:ring-offset-4 ${
                    activeWorkflowStep === 3 ? 'opacity-100' : 'opacity-85'
                  }`}
                  aria-pressed={activeWorkflowStep === 3}
                  aria-label="Step 3: Track ang order hanggang delivery"
                >

                  <div
                    className={`
                      relative
                      z-10
                      flex
                      h-12
                      w-12
                      flex-shrink-0
                      items-center
                      justify-center
                      rounded-full
                      border-4
                      border-[#F5F1E5]
                      bg-[#1F7A2E]
                      shadow-md
                      transition-all
                      duration-200
                      ${
                        activeWorkflowStep === 3
                          ? 'scale-110 shadow-lg ring-4 ring-[#1F7A2E]/20'
                          : 'scale-100'
                      }
                    `}
                  >
                    <Truck className="h-5 w-5 text-white" />
                  </div>


                  <div className="pt-1">

                    <h3
                      className="
                        text-xl
                        font-black
                        uppercase
                        leading-tight
                        text-[#123C5C]
                        sm:text-2xl
                      "
                    >
                      TRACK ANG ORDER HANGGANG DELIVERY
                    </h3>

                    <p
                      className="
                        mt-1
                        text-base
                        leading-relaxed
                        text-[#8B7F72]
                        sm:text-lg
                      "
                    >
                      Sundan ang status ng order mula confirmation
                      hanggang sa fulfillment at delivery.
                    </p>

                  </div>

                </button>

              </div>


              {/* ===================================================
                  CTA CARD
              =================================================== */}

              <div
                className="
                  mt-10
                  rounded-2xl
                  border
                  border-[#E7E1D0]
                  bg-white
                  p-5
                  shadow-sm
                  sm:p-6
                "
              >

                <div
                  className="
                    flex
                    flex-col
                    gap-5
                    sm:flex-row
                    sm:items-center
                    sm:justify-between
                  "
                >

                  <div>

                    <h3
                      className="
                        font-display
                        text-2xl
                        uppercase
                        leading-tight
                        text-[#123C5C]
                        sm:text-3xl
                      "
                    >
                      SIMULAN ANG PAG-ORDER
                    </h3>

                    <p className="mt-1 text-[#8B7F72]">
                      Gumawa ng account at kumonekta sa local producers.
                    </p>

                  </div>


                  <Link to="/login">

                    <Button
                      className="
                        w-full
                        rounded-xl
                        bg-[#4D8B35]
                        px-6
                        py-6
                        font-black
                        text-white
                        shadow-md
                        transition-all
                        duration-200
                        hover:-translate-y-1
                        hover:bg-[#3F742C]
                        hover:shadow-lg
                        sm:w-auto
                      "
                    >
                      ORDER NA!
                      <ArrowUpRight className="ml-2 h-5 w-5" />
                    </Button>

                  </Link>

                </div>

              </div>

            </div>

          </div>

        </div>

      </section>


      {/* =========================================================
          BENEFITS
      ========================================================= */}

      <section
        id="benefits"
        className="
          py-20
          sm:py-24
          bg-[#123C5C]
          text-white
          scroll-mt-20
          md:scroll-mt-24
        "
      >

        <div className="container mx-auto px-4">

          <div className="text-center mb-14 md:mb-16">

            <p
              className="
                text-[#FBBE24]
                font-bold
                uppercase
                tracking-[0.18em]
                text-sm
                mb-3
              "
            >
              Why HarborAI
            </p>

            <h2
              className="
                font-display
                text-4xl
                sm:text-5xl
                md:text-6xl
                mb-4
              "
            >
              BUILT FOR LOCAL PRODUCERS
            </h2>

            <p className="text-lg text-white/75 max-w-2xl mx-auto">
              Technology-driven support for Aparri's agricultural
               communities.
            </p>

          </div>


          <div className="grid grid-cols-2 lg:grid-cols-4 gap-8">

            <div className="text-center">

              <div className="font-display text-5xl md:text-6xl text-[#FBBE24] mb-2">
                100%
              </div>

              <div className="text-sm font-semibold text-white/80">
                Transparent Trading
              </div>

            </div>


            <div className="text-center">

              <div className="font-display text-5xl md:text-6xl text-[#FBBE24] mb-2">
                0
              </div>

              <div className="text-sm font-semibold text-white/80">
                Middlemen Dependencies
              </div>

            </div>


            <div className="text-center">

              <div className="font-display text-5xl md:text-6xl text-[#FBBE24] mb-2">
                5+
              </div>

              <div className="text-sm font-semibold text-white/80">
                Government Programs
              </div>

            </div>


            <div className="text-center">

              <div className="font-display text-5xl md:text-6xl text-[#FBBE24] mb-2">
                24/7
              </div>

              <div className="text-sm font-semibold text-white/80">
                Platform Access
              </div>

            </div>

          </div>

        </div>

      </section>


      {/* =========================================================
          WHO WE SERVE
      ========================================================= */}

      <section className="py-20 sm:py-24 bg-[#F5F1E5]">

        <div className="container mx-auto px-4">

          <div className="text-center mb-14 md:mb-16">

            <p
              className="
                text-[#4D8B35]
                font-bold
                uppercase
                tracking-[0.18em]
                text-sm
                mb-3
              "
            >
              Our Community
            </p>

            <h2
              className="
                font-display
                text-4xl
                sm:text-5xl
                md:text-6xl
                text-[#123C5C]
                mb-4
              "
            >
              WHO WE SERVE
            </h2>

            <p className="text-lg text-[#45586B] max-w-2xl mx-auto">
              A role-based platform designed for agriculture
               stakeholders.
            </p>

          </div>


          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">

            {/* PRODUCERS */}

            <Card
              className="
                text-center
                rounded-2xl
                border-2
                border-[#E7E1D0]
                hover:-translate-y-1
                shadow-sm
                hover:shadow-lg
                transition-all
                overflow-hidden
                bg-white
              "
            >

              <div className="h-2.5 bg-[#22C55E]" />

              <CardContent className="p-8">

                <div
                  className="
                    w-16
                    h-16
                    bg-[#22C55E]/15
                    rounded-full
                    flex
                    items-center
                    justify-center
                    mx-auto
                    mb-4
                  "
                >
                  <Sprout className="w-8 h-8 text-[#22C55E]" />
                </div>

                <h3 className="text-2xl font-bold text-[#123C5C] mb-3">
                  Producers
                </h3>

                <p className="text-[#45586B] mb-4">
                  Farmers and fishers in Aparri seeking better
                  market access and income stability.
                </p>

                <ul className="text-sm text-[#45586B] text-left space-y-2 inline-block">

                  <li className="flex items-start gap-2">

                    <CheckCircle2
                      className="
                        w-4
                        h-4
                        text-[#22C55E]
                        mt-0.5
                        flex-shrink-0
                      "
                    />

                    <span>Market insights</span>

                  </li>


                  <li className="flex items-start gap-2">

                    <CheckCircle2
                      className="
                        w-4
                        h-4
                        text-[#22C55E]
                        mt-0.5
                        flex-shrink-0
                      "
                    />

                    <span>Program eligibility matching</span>

                  </li>


                  <li className="flex items-start gap-2">

                    <CheckCircle2
                      className="
                        w-4
                        h-4
                        text-[#22C55E]
                        mt-0.5
                        flex-shrink-0
                      "
                    />

                    <span>Direct institutional access</span>

                  </li>

                </ul>

              </CardContent>

            </Card>


            {/* INSTITUTIONAL BUYERS */}

            <Card
              className="
                text-center
                rounded-2xl
                border-2
                border-[#E7E1D0]
                hover:-translate-y-1
                shadow-sm
                hover:shadow-lg
                transition-all
                overflow-hidden
                bg-white
              "
            >

              <div className="h-2.5 bg-[#123C5C]" />

              <CardContent className="p-8">

                <div
                  className="
                    w-16
                    h-16
                    bg-[#123C5C]/10
                    rounded-full
                    flex
                    items-center
                    justify-center
                    mx-auto
                    mb-4
                  "
                >
                  <Users className="w-8 h-8 text-[#123C5C]" />
                </div>

                <h3 className="text-2xl font-bold text-[#123C5C] mb-3">
                  Institutional Buyers
                </h3>

                <p className="text-[#45586B] mb-4">
                  LGUs, DA/BFAR, and Kadiwa outlets for
                  policy-compliant procurement.
                </p>

                <ul className="text-sm text-[#45586B] text-left space-y-2 inline-block">

                  <li className="flex items-start gap-2">

                    <CheckCircle2
                      className="
                        w-4
                        h-4
                        text-[#123C5C]
                        mt-0.5
                        flex-shrink-0
                      "
                    />

                    <span>Browse verified producers</span>

                  </li>


                  <li className="flex items-start gap-2">

                    <CheckCircle2
                      className="
                        w-4
                        h-4
                        text-[#123C5C]
                        mt-0.5
                        flex-shrink-0
                      "
                    />

                    <span>Post procurement demands</span>

                  </li>


                  <li className="flex items-start gap-2">

                    <CheckCircle2
                      className="
                        w-4
                        h-4
                        text-[#123C5C]
                        mt-0.5
                        flex-shrink-0
                      "
                    />

                    <span>Track order fulfillment</span>

                  </li>

                </ul>

              </CardContent>

            </Card>


            {/* ADMINISTRATORS */}

            <Card
              className="
                text-center
                rounded-2xl
                border-2
                border-[#E7E1D0]
                hover:-translate-y-1
                shadow-sm
                hover:shadow-lg
                transition-all
                overflow-hidden
                bg-white
              "
            >

              <div className="h-2.5 bg-[#0F9488]" />

              <CardContent className="p-8">

                <div
                  className="
                    w-16
                    h-16
                    bg-[#0F9488]/15
                    rounded-full
                    flex
                    items-center
                    justify-center
                    mx-auto
                    mb-4
                  "
                >
                  <Shield className="w-8 h-8 text-[#0F9488]" />
                </div>

                <h3 className="text-2xl font-bold text-[#123C5C] mb-3">
                  Administrators
                </h3>

                <p className="text-[#45586B] mb-4">
                  DA/LGU personnel managing the platform and
                  ensuring compliance.
                </p>

                <ul className="text-sm text-[#45586B] text-left space-y-2 inline-block">

                  <li className="flex items-start gap-2">

                    <CheckCircle2
                      className="
                        w-4
                        h-4
                        text-[#0F9488]
                        mt-0.5
                        flex-shrink-0
                      "
                    />

                    <span>User verification</span>

                  </li>


                  <li className="flex items-start gap-2">

                    <CheckCircle2
                      className="
                        w-4
                        h-4
                        text-[#0F9488]
                        mt-0.5
                        flex-shrink-0
                      "
                    />

                    <span>Analytics & monitoring</span>

                  </li>


                  <li className="flex items-start gap-2">

                    <CheckCircle2
                      className="
                        w-4
                        h-4
                        text-[#0F9488]
                        mt-0.5
                        flex-shrink-0
                      "
                    />

                    <span>Program management</span>

                  </li>

                </ul>

              </CardContent>

            </Card>

          </div>

        </div>

      </section>


      {/* =========================================================
          ABOUT
      ========================================================= */}

      <section
        id="about"
        className="
          py-20
          sm:py-24
          bg-white
          scroll-mt-20
          md:scroll-mt-24
        "
      >

        <div className="container mx-auto px-4">

          <div className="grid md:grid-cols-2 gap-12 items-center">

            {/* TEXT */}

            <div>

              <p
                className="
                  text-[#4D8B35]
                  font-bold
                  uppercase
                  tracking-[0.18em]
                  text-sm
                  mb-3
                "
              >
                Our Story
              </p>

              <h2
                className="
                  font-display
                  text-4xl
                  sm:text-5xl
                  md:text-6xl
                  text-[#123C5C]
                  mb-6
                "
              >
                ABOUT HARBORAI
              </h2>


              <p
                className="
                  text-lg
                  text-[#45586B]
                  mb-6
                  leading-relaxed
                "
              >
                HarborAI is a comprehensive web-based decision
                support and institutional trading platform designed
                specifically for the agricultural and fishery sectors
                in Aparri, Cagayan.
              </p>


              <p
                className="
                  text-lg
                  text-[#45586B]
                  mb-6
                  leading-relaxed
                "
              >
                By combining artificial intelligence with local
                expertise, we help producers identify enterprise
                opportunities, access government support, and engage
                in fair and transparent trading with institutional buyers.
              </p>


              <div className="space-y-4">

                <div className="flex items-start gap-3">

                  <div
                    className="
                      w-8
                      h-8
                      bg-[#22C55E]
                      rounded-full
                      flex
                      items-center
                      justify-center
                      flex-shrink-0
                      mt-1
                    "
                  >
                    <CheckCircle2 className="w-4 h-4 text-white" />
                  </div>

                  <div>

                    <h4 className="font-bold text-[#123C5C] mb-1">
                      Reduce Middleman Dependency
                    </h4>

                    <p className="text-[#45586B]">
                      Connect directly with institutional buyers.
                    </p>

                  </div>

                </div>


                <div className="flex items-start gap-3">

                  <div
                    className="
                      w-8
                      h-8
                      bg-[#0F9488]
                      rounded-full
                      flex
                      items-center
                      justify-center
                      flex-shrink-0
                      mt-1
                    "
                  >
                    <CheckCircle2 className="w-4 h-4 text-white" />
                  </div>

                  <div>

                    <h4 className="font-bold text-[#123C5C] mb-1">
                      Improve Income Stability
                    </h4>

                    <p className="text-[#45586B]">
                      Better pricing and consistent demand.
                    </p>

                  </div>

                </div>


                <div className="flex items-start gap-3">

                  <div
                    className="
                      w-8
                      h-8
                      bg-[#8B27C7]
                      rounded-full
                      flex
                      items-center
                      justify-center
                      flex-shrink-0
                      mt-1
                    "
                  >
                    <CheckCircle2 className="w-4 h-4 text-white" />
                  </div>

                  <div>

                    <h4 className="font-bold text-[#123C5C] mb-1">
                      Policy-Aligned Trading
                    </h4>

                    <p className="text-[#45586B]">
                      Support transparent government procurement.
                    </p>

                  </div>

                </div>

              </div>

            </div>


            {/* IMAGE */}

            <div
              className="
                relative
                rotate-2
                hover:rotate-0
                transition-transform
                duration-300
              "
            >

              <div
                className="
                  bg-white
                  p-2
                  sm:p-3
                  rounded-2xl
                  shadow-xl
                  border-4
                  border-[#0F9488]
                "
              >

                <ImageWithFallback
                  src="/Images/Front.png"
                  alt="Aparri agricultural landscape"
                  className="
                    w-full
                    h-80
                    sm:h-96
                    object-cover
                    rounded-lg
                  "
                />

              </div>

            </div>

          </div>

        </div>

      </section>


      {/* =========================================================
          CTA
      ========================================================= */}

      <section
        className="
          py-20
          sm:py-24
          bg-[#4D8B35]
          text-white
          relative
          overflow-hidden
        "
      >

        {/* Decorative circles */}

        <div className="absolute inset-0 pointer-events-none opacity-10">

          <div
            className="
              absolute
              -left-24
              -top-24
              w-80
              h-80
              rounded-full
              border-[45px]
              border-white
            "
          />

          <div
            className="
              absolute
              -right-24
              -bottom-24
              w-80
              h-80
              rounded-full
              border-[45px]
              border-white
            "
          />

        </div>


        <div
          className="
            relative
            container
            mx-auto
            px-4
            text-center
            max-w-3xl
          "
        >

          <p
            className="
              text-[#FBBE24]
              font-bold
              uppercase
              tracking-[0.18em]
              text-sm
              mb-3
            "
          >
            Start Today
          </p>


          <h2
            className="
              font-display
              text-4xl
              sm:text-5xl
              md:text-6xl
              mb-6
            "
          >
            READY TO TRANSFORM YOUR LIVELIHOOD?
          </h2>


          <p
            className="
              text-lg
              text-white/90
              mb-8
              leading-relaxed
            "
          >
            Join HarborAI and gain access to institutional markets,
            AI-powered insights, and government support programs.
          </p>


          <div className="flex flex-wrap gap-4 justify-center">

            <Link to="/register">

              <Button
                size="lg"
                className="
                  font-bold
                  rounded-xl
                  bg-[#FBBE24]
                  text-[#123C5C]
                  hover:bg-[#F59E0B]
                  shadow-lg
                  hover:scale-105
                  transition-all
                "
              >

                <Sprout className="w-5 h-5 mr-2" />

                Register as Producer

              </Button>

            </Link>


            <Link to="/login">

              <Button
                size="lg"
                variant="outline"
                className="
                  font-bold
                  rounded-xl
                  border-2
                  border-white
                  text-white
                  hover:bg-white
                  hover:text-[#4D8B35]
                  bg-transparent
                "
              >
                Login to Your Account
              </Button>

            </Link>

          </div>

        </div>

      </section>


      {/* =========================================================
          FOOTER
      ========================================================= */}

      <footer
        className="
          bg-[#0E2A44]
          text-[#CBD5E1]
          py-12
          border-t-4
          border-[#FBBE24]
        "
      >

        <div className="container mx-auto px-4">

          <div
            className="
              grid
              grid-cols-2
              gap-x-5
              gap-y-6
              md:flex
              md:flex-row
              md:justify-between
              md:items-start
              gap-10
              md:gap-0
              mb-8
              text-center
              md:text-left
            "
          >

            {/* BRAND */}

            <div
              className="
                flex
                flex-col
                items-center
                md:items-start
                col-span-2
                mb-0
                md:mb-0
                md:w-1/4
              "
            >

              <div
                className="
                  flex
                  items-center
                  gap-2
                  mb-4
                  justify-center
                  md:justify-start
                "
              >

                <img
                  src="/logo.png"
                  alt="HarborAI Logo"
                  className="w-8 h-8 object-contain"
                />

                <span
                  className="
                    font-display
                    text-xl
                    text-white
                    tracking-tight
                  "
                >
                  HarborAI
                </span>

              </div>


              <p className="text-sm max-w-xs">
                Empowering Aparri's agricultural and fishery
                sectors through AI-driven innovation.
              </p>

            </div>


            {/* FOOTER LINKS */}

            <div
              className="
                grid
                grid-cols-2
                gap-5
                md:flex
                md:flex-row
                md:gap-16
                justify-center
                md:w-3/4
              "
            >

              {/* PLATFORM */}

              <div>

                <h4
                  className="
                    font-bold
                    text-white
                    mb-2
                    uppercase
                    text-sm
                    tracking-wide
                  "
                >
                  Platform
                </h4>

                <ul className="space-y-2 text-sm">

                  <li>
                    <Link
                      to="/programs"
                      className="hover:text-[#4ADE80] transition-colors"
                    >
                      Programs
                    </Link>
                  </li>

                  <li>
                    <a
                      href="#features"
                      className="hover:text-[#4ADE80] transition-colors"
                    >
                      Features
                    </a>
                  </li>

                  <li>
                    <a
                      href="#how-it-works"
                      className="hover:text-[#4ADE80] transition-colors"
                    >
                      How It Works
                    </a>
                  </li>

                  <li>
                    <a
                      href="#benefits"
                      className="hover:text-[#4ADE80] transition-colors"
                    >
                      Benefits
                    </a>
                  </li>

                </ul>

              </div>


              {/* SUPPORT */}

              <div>

                <h4
                  className="
                    font-bold
                    text-white
                    mb-4
                    uppercase
                    text-sm
                    tracking-wide
                  "
                >
                  Support
                </h4>

                <ul className="space-y-2 text-sm">

                  <li>
                    <a
                      href="#"
                      className="hover:text-[#4ADE80] transition-colors"
                    >
                      Help Center
                    </a>
                  </li>

                  <li>
                    <Link
                      to="/faq"
                      className="hover:text-[#4ADE80] transition-colors"
                    >
                      FAQ
                    </Link>
                  </li>

                  <li>
                    <a
                      href="#"
                      className="hover:text-[#4ADE80] transition-colors"
                    >
                      Contact Us
                    </a>
                  </li>

                </ul>

              </div>


              {/* LEGAL */}

              <div>

                <h4
                  className="
                    font-bold
                    text-white
                    mb-4
                    uppercase
                    text-sm
                    tracking-wide
                  "
                >
                  Legal
                </h4>

                <ul className="space-y-2 text-sm">

                  <li>
                    <Link
                      to="/privacy"
                      className="hover:text-[#4ADE80] transition-colors"
                    >
                      Privacy Policy
                    </Link>
                  </li>

                  <li>
                    <a
                      href="#"
                      className="hover:text-[#4ADE80] transition-colors"
                    >
                      Terms of Service
                    </a>
                  </li>

                  <li>
                    <a
                      href="#"
                      className="hover:text-[#4ADE80] transition-colors"
                    >
                      Data Protection
                    </a>
                  </li>

                </ul>

              </div>

            </div>

          </div>


          {/* COPYRIGHT */}

          <div
            className="
              border-t
              border-white/10
              pt-4
              md:pt-8
              text-center
              text-xs
              md:text-sm
            "
          >

            <p>
              © 2026 HarborAI. A project for sustainable
              agriculture development in Aparri, Cagayan.
            </p>

          </div>

        </div>

      </footer>

    </div>
  );
}

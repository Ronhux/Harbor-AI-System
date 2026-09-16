import '../../styles/hide-scrollbar.css';
import { Link } from 'react-router-dom';
import { useState } from 'react';
import { Sprout, Fish, TrendingUp, Users, Shield, BarChart3, Brain, CheckCircle2, Menu, X } from 'lucide-react';
import { Button } from '../components/ui/button';
import { Card, CardContent } from '../components/ui/card';
import { ImageWithFallback } from '../components/figma/ImageWithFallback';

/*
  HarborAI design tokens — lively pass
  -------------------------------------
  navy      #123C5C  headings & body text on light backgrounds
  navyDark  #0E2A44  footer / dark accent backgrounds
  teal      #0F9488  secondary actions, icon accents
  tealDark  #0B4842  illustration shading, hover states
  cyan      #22D3EE  bright sparkle accent (sparingly)
  green     #22C55E  primary action color (producers / agriculture)
  greenDark #15803D  hover state, illustration shading
  cream     #F5F1E5  light background
  textSoft  #45586B  muted body copy on light backgrounds
  line      #E7E1D0  hairline borders on light backgrounds
*/

export default function LandingPage() {
  const [menuOpen, setMenuOpen] = useState(false);

  return (
    <div className="min-h-screen bg-[#F5F1E5] font-body text-[#123C5C] scrollbar-hide" style={{overflowX:'hidden'}}>
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Anton&family=Baloo+2:wght@400;500;600;700;800&display=swap');
        .font-display { font-family: 'Anton', ui-sans-serif, sans-serif; }
        .font-body { font-family: 'Baloo 2', ui-rounded, system-ui, sans-serif; }
      `}</style>

      {/* Navigation */}
        <nav className="bg-[#F5F1E5]/95 backdrop-blur-sm fixed top-0 left-0 w-full z-50 h-16 md:h-20 flex items-center border-b border-[#E7E1D0]">
          <div className="h-16 md:h-20" /> {/* Spacer for fixed navbar, matches nav height */}
        <div className="container mx-auto px-4 py-4 flex items-center justify-between relative">
          <div className="flex items-center gap-3 min-w-0">
            <img src="/logo.png" alt="HarborAI Logo" className="w-9 h-9 sm:w-11 sm:h-11 object-contain" />
            <span className="font-display text-xl sm:text-2xl text-[#123C5C] whitespace-nowrap tracking-tight">HarborAI</span>
          </div>
          {/* Desktop Nav */}
          <div className="hidden md:flex items-center gap-7">
            <a href="#features" className="text-sm font-bold uppercase tracking-wide text-[#0F9488] hover:text-[#22C55E] transition-colors">Features</a>
            <a href="#benefits" className="text-sm font-bold uppercase tracking-wide text-[#0F9488] hover:text-[#22C55E] transition-colors">Benefits</a>
            <Link to="/programs" className="text-sm font-bold uppercase tracking-wide text-[#0F9488] hover:text-[#22C55E] transition-colors">Programs</Link>
            <Link to="/faq" className="text-sm font-bold uppercase tracking-wide text-[#0F9488] hover:text-[#22C55E] transition-colors">FAQ</Link>
            <a href="#about" className="text-sm font-bold uppercase tracking-wide text-[#0F9488] hover:text-[#22C55E] transition-colors">About</a>
          </div>
          {/* Desktop Auth */}
          <div className="hidden md:flex gap-3 items-center">
            <Link to="/login">
              <Button variant="outline" className="font-bold rounded-full border-2 border-[#123C5C] text-[#123C5C] hover:bg-[#123C5C] hover:text-white transition-colors duration-200">
                Login
              </Button>
            </Link>
            <Link to="/register">
              <Button className="font-bold rounded-full bg-[#22C55E] hover:bg-[#15803D] text-white shadow-sm transition-colors duration-200">
                Register as Producer
              </Button>
            </Link>
          </div>
          {/* Hamburger */}
          <button
            className="block md:hidden ml-auto p-2 z-20 rounded-full text-[#123C5C] hover:bg-[#123C5C]/10 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#22C55E]"
            aria-label="Open menu"
            onClick={() => setMenuOpen(v => !v)}
          >
            {menuOpen ? <X className="w-7 h-7" /> : <Menu className="w-7 h-7" />}
          </button>
          {/* Mobile Menu */}
          {menuOpen && (
            <div className="absolute top-full left-0 w-full bg-[#F5F1E5] border-t border-[#E7E1D0] z-30 animate-fade-in flex flex-col gap-1 py-4 px-6 md:hidden">
              <a href="#features" className="py-2.5 px-2 rounded-lg font-bold uppercase text-sm tracking-wide text-[#0F9488] hover:bg-[#123C5C]/5" onClick={()=>setMenuOpen(false)}>Features</a>
              <a href="#benefits" className="py-2.5 px-2 rounded-lg font-bold uppercase text-sm tracking-wide text-[#0F9488] hover:bg-[#123C5C]/5" onClick={()=>setMenuOpen(false)}>Benefits</a>
              <Link to="/programs" className="py-2.5 px-2 rounded-lg font-bold uppercase text-sm tracking-wide text-[#0F9488] hover:bg-[#123C5C]/5" onClick={()=>setMenuOpen(false)}>Programs</Link>
              <Link to="/faq" className="py-2.5 px-2 rounded-lg font-bold uppercase text-sm tracking-wide text-[#0F9488] hover:bg-[#123C5C]/5" onClick={()=>setMenuOpen(false)}>FAQ</Link>
              <a href="#about" className="py-2.5 px-2 rounded-lg font-bold uppercase text-sm tracking-wide text-[#0F9488] hover:bg-[#123C5C]/5" onClick={()=>setMenuOpen(false)}>About</a>
              <div className="flex flex-col gap-2 mt-3">
                <Link to="/login">
                  <Button variant="outline" className="w-full rounded-full font-bold border-2 border-[#123C5C] text-[#123C5C]">Login</Button>
                </Link>
                <Link to="/register">
                  <Button className="w-full rounded-full font-bold bg-[#22C55E] hover:bg-[#15803D] text-white">Register as Producer</Button>
                </Link>
              </div>
            </div>
          )}
        </div>
      </nav>

      {/* Hero Section */}
      <section className="relative overflow-hidden bg-[#F5F1E5] pt-24 md:pt-28">
        <div className="max-w-6xl mx-auto w-full px-4 pb-8 sm:pb-12">
          <div className="flex flex-col-reverse md:flex-row items-center gap-10 md:gap-12">
            {/* Text Content */}
            <div className="w-full md:w-1/2 flex flex-col items-center md:items-start text-center md:text-left">
              <div className="inline-flex items-center gap-2 bg-[#22C55E] text-white px-4 py-1.5 rounded-full mb-6 shadow-sm">
                <Brain className="w-4 h-4" />
                <span className="text-xs sm:text-sm font-bold uppercase tracking-wide">AI-Powered Agri-Fish Platform</span>
              </div>
              <h1 className="font-display leading-[0.95] mb-6">
                <span className="block text-2xl sm:text-3xl md:text-4xl text-[#22C55E] -rotate-1 origin-left mb-1">
                  Empowering Aparri's
                </span>
                <span className="block text-5xl sm:text-6xl md:text-7xl text-[#123C5C] rotate-1 origin-left">
                  Farmers &amp; Fishers
                </span>
              </h1>
              <p className="text-base sm:text-lg text-[#45586B] mb-8 max-w-lg leading-relaxed">
                HarborAI connects agricultural and fishery producers with institutional buyers, 
                provides AI-driven recommendations, and unlocks access to government support programs.
              </p>
              <div className="flex flex-col sm:flex-row w-full gap-4 justify-center md:justify-start">
                <Link to="/register" className="w-full sm:w-auto">
                  <Button size="lg" className="w-full sm:w-auto font-bold rounded-full bg-[#22C55E] hover:bg-[#15803D] text-white border-0 shadow-md hover:shadow-lg hover:scale-105 transition-all duration-200">
                    <Sprout className="w-5 h-5 mr-2" />
                    Get Started as Producer
                  </Button>
                </Link>
                <Link to="/login" className="w-full sm:w-auto">
                  <Button size="lg" className="w-full sm:w-auto font-bold rounded-full bg-[#0F9488] hover:bg-[#0B4842] text-white border-0 shadow-md hover:shadow-lg hover:scale-105 transition-all duration-200">
                    Learn More
                  </Button>
                </Link>
              </div>
              <div className="flex flex-col sm:flex-row items-center gap-3 sm:gap-4 mt-8">
                <div className="flex items-center gap-2 bg-white border-2 border-[#E7E1D0] rounded-full px-4 py-1.5 text-sm font-semibold text-[#123C5C]">
                  <CheckCircle2 className="w-4 h-4 text-[#22C55E]" />
                  <span>Verified Producers</span>
                </div>
                <div className="flex items-center gap-2 bg-white border-2 border-[#E7E1D0] rounded-full px-4 py-1.5 text-sm font-semibold text-[#123C5C]">
                  <CheckCircle2 className="w-4 h-4 text-[#0F9488]" />
                  <span>Policy-Aligned Trading</span>
                </div>
              </div>
            </div>
            {/* Hero Image */}
            <div className="w-full md:w-1/2 flex justify-center">
              <div className="relative -rotate-2 hover:rotate-0 transition-transform duration-300 w-full max-w-xs sm:max-w-md md:max-w-lg">
                <div className="bg-white p-2 sm:p-3 rounded-2xl shadow-xl border-4 border-[#22C55E]">
                  <ImageWithFallback 
                    src="/Images/Front.png"
                    alt="Farmers, fisherfolk, and AI-powered connectivity"
                    className="w-full h-56 sm:h-80 md:h-96 object-cover rounded-lg"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Illustrated horizon divider: fields meeting the sea */}
        <div className="relative w-full leading-none -mb-1" aria-hidden="true">
          <svg viewBox="0 0 1440 180" preserveAspectRatio="none" className="w-full h-24 sm:h-36 md:h-44 block">
            <defs>
              <linearGradient id="horizonGrad" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stopColor="#22C55E" />
                <stop offset="55%" stopColor="#22C55E" />
                <stop offset="72%" stopColor="#0F9488" />
                <stop offset="100%" stopColor="#0EA5A0" />
              </linearGradient>
              <filter id="grain">
                <feTurbulence type="fractalNoise" baseFrequency="0.85" numOctaves="2" stitchTiles="stitch" result="noise" />
                <feColorMatrix in="noise" type="matrix" values="0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0 0 0 0.06 0" />
              </filter>
            </defs>
            <path d="M0,95 C180,50 360,95 540,68 C720,40 900,85 1080,58 C1260,30 1350,65 1440,50 L1440,180 L0,180 Z" fill="#BFEFE2" opacity="0.55" />
            <path d="M0,70 L70,108 L140,76 L210,120 L280,86 L350,128 L420,94 L490,132 L560,100 L630,136 L700,112 Q760,146 820,128 T940,136 T1060,108 T1180,130 T1300,98 L1440,116 L1440,180 L0,180 Z" fill="url(#horizonGrad)" />
            <path d="M0,180 L0,150 L80,163 L160,146 L240,168 L320,150 L400,170 L480,152 L560,172 L640,154 L720,166 L740,180 Z" fill="#15803D" />
            <path d="M700,180 L700,168 Q780,176 860,164 T1000,170 T1140,156 T1280,172 T1420,158 L1440,164 L1440,180 Z" fill="#0B4842" />
            <ellipse cx="150" cy="140" rx="7" ry="12" fill="#86EFAC" opacity="0.85" transform="rotate(-20 150 140)" />
            <ellipse cx="330" cy="150" rx="6" ry="10" fill="#86EFAC" opacity="0.85" transform="rotate(15 330 150)" />
            <ellipse cx="520" cy="145" rx="7" ry="12" fill="#86EFAC" opacity="0.85" transform="rotate(-10 520 145)" />
            <path d="M950,150 Q970,142 990,150" stroke="#A7F3EE" strokeWidth="3" fill="none" strokeLinecap="round" opacity="0.85" />
            <path d="M1100,145 Q1120,137 1140,145" stroke="#A7F3EE" strokeWidth="3" fill="none" strokeLinecap="round" opacity="0.85" />
            <path d="M1260,150 Q1280,142 1300,150" stroke="#A7F3EE" strokeWidth="3" fill="none" strokeLinecap="round" opacity="0.85" />
            <rect x="0" y="0" width="1440" height="180" filter="url(#grain)" />
          </svg>
        </div>
      </section>

      {/* Features Section */}
      <section id="features" className="bg-white py-20 sm:py-24 scroll-mt-20 md:scroll-mt-24">
        <div className="container mx-auto px-4">
          <div className="text-center mb-14 md:mb-16">
            <h2 className="font-display text-3xl sm:text-4xl md:text-5xl text-[#123C5C] mb-4 tracking-tight">
              Comprehensive Platform Features
            </h2>
            <p className="text-lg text-[#45586B] max-w-2xl mx-auto">
              Built to support the entire agri-fish value chain in Aparri, Cagayan
            </p>
          </div>
          
          <div className="grid md:grid-cols-3 gap-6">
            <Card className="group rounded-2xl border-2 border-[#E7E1D0] hover:border-[#22C55E] hover:-translate-y-1 shadow-sm hover:shadow-lg transition-all duration-300">
              <CardContent className="p-6">
                <div className="w-14 h-14 bg-[#22C55E] rounded-2xl flex items-center justify-center mb-4 rotate-3 group-hover:rotate-0 transition-transform duration-300">
                  <Brain className="w-7 h-7 text-white" />
                </div>
                <h3 className="text-xl font-bold text-[#123C5C] mb-2">AI-Driven Recommendations</h3>
                <p className="text-[#45586B]">
                  Get personalized enterprise opportunities, optimal pricing guidance, and market timing suggestions powered by AI.
                </p>
              </CardContent>
            </Card>

            <Card className="group rounded-2xl border-2 border-[#E7E1D0] hover:border-[#0F9488] hover:-translate-y-1 shadow-sm hover:shadow-lg transition-all duration-300">
              <CardContent className="p-6">
                <div className="w-14 h-14 bg-[#0F9488] rounded-2xl flex items-center justify-center mb-4 rotate-3 group-hover:rotate-0 transition-transform duration-300">
                  <Shield className="w-7 h-7 text-white" />
                </div>
                <h3 className="text-xl font-bold text-[#123C5C] mb-2">Government Program Matching</h3>
                <p className="text-[#45586B]">
                  Discover government support programs you qualify for through NLP-powered eligibility matching.
                </p>
              </CardContent>
            </Card>

            <Card className="group rounded-2xl border-2 border-[#E7E1D0] hover:border-[#22D3EE] hover:-translate-y-1 shadow-sm hover:shadow-lg transition-all duration-300">
              <CardContent className="p-6">
                <div className="w-14 h-14 bg-[#0EA5A0] rounded-2xl flex items-center justify-center mb-4 rotate-3 group-hover:rotate-0 transition-transform duration-300">
                  <Users className="w-7 h-7 text-white" />
                </div>
                <h3 className="text-xl font-bold text-[#123C5C] mb-2">Institutional Trading</h3>
                <p className="text-[#45586B]">
                  Connect directly with LGUs, DA/BFAR, and Kadiwa outlets for transparent, policy-aligned procurement.
                </p>
              </CardContent>
            </Card>

            <Card className="group rounded-2xl border-2 border-[#E7E1D0] hover:border-[#22C55E] hover:-translate-y-1 shadow-sm hover:shadow-lg transition-all duration-300">
              <CardContent className="p-6">
                <div className="w-14 h-14 bg-[#22C55E] rounded-2xl flex items-center justify-center mb-4 rotate-3 group-hover:rotate-0 transition-transform duration-300">
                  <TrendingUp className="w-7 h-7 text-white" />
                </div>
                <h3 className="text-xl font-bold text-[#123C5C] mb-2">Price &amp; Demand Forecasting</h3>
                <p className="text-[#45586B]">
                  Make informed decisions with AI-assisted smart pricing prompts and demand predictions.
                </p>
              </CardContent>
            </Card>

            <Card className="group rounded-2xl border-2 border-[#E7E1D0] hover:border-[#0F9488] hover:-translate-y-1 shadow-sm hover:shadow-lg transition-all duration-300">
              <CardContent className="p-6">
                <div className="w-14 h-14 bg-[#0F9488] rounded-2xl flex items-center justify-center mb-4 rotate-3 group-hover:rotate-0 transition-transform duration-300">
                  <BarChart3 className="w-7 h-7 text-white" />
                </div>
                <h3 className="text-xl font-bold text-[#123C5C] mb-2">Digital Marketplace</h3>
                <p className="text-[#45586B]">
                  List your products, track orders, and manage transactions all in one secure platform.
                </p>
              </CardContent>
            </Card>

            <Card className="group rounded-2xl border-2 border-[#E7E1D0] hover:border-[#22D3EE] hover:-translate-y-1 shadow-sm hover:shadow-lg transition-all duration-300">
              <CardContent className="p-6">
                <div className="w-14 h-14 bg-[#0EA5A0] rounded-2xl flex items-center justify-center mb-4 rotate-3 group-hover:rotate-0 transition-transform duration-300">
                  <Fish className="w-7 h-7 text-white" />
                </div>
                <h3 className="text-xl font-bold text-[#123C5C] mb-2">Enterprise Profiling</h3>
                <p className="text-[#45586B]">
                  Create comprehensive profiles showcasing your agricultural or fishery enterprise capabilities.
                </p>
              </CardContent>
            </Card>
          </div>
        </div>
      </section>

      {/* Benefits Section */}
      <section id="benefits" className="py-20 sm:py-24 bg-[#0F9488] text-white scroll-mt-20 md:scroll-mt-24">
        <div className="container mx-auto px-4">
          <div className="text-center mb-14 md:mb-16">
            <h2 className="font-display text-3xl sm:text-4xl md:text-5xl mb-4 tracking-tight">Why Choose HarborAI?</h2>
            <p className="text-lg text-white/85 max-w-2xl mx-auto">
              Transform your livelihood with technology-driven support
            </p>
          </div>

          <div className="grid grid-cols-2 lg:grid-cols-4 gap-8">
            <div className="text-center">
              <div className="font-display text-5xl text-[#FDE68A] mb-2">100%</div>
              <div className="text-sm font-semibold text-white/85">Transparent Trading</div>
            </div>
            <div className="text-center">
              <div className="font-display text-5xl text-[#FDE68A] mb-2">0</div>
              <div className="text-sm font-semibold text-white/85">Middlemen Dependencies</div>
            </div>
            <div className="text-center">
              <div className="font-display text-5xl text-[#FDE68A] mb-2">5+</div>
              <div className="text-sm font-semibold text-white/85">Government Programs</div>
            </div>
            <div className="text-center">
              <div className="font-display text-5xl text-[#FDE68A] mb-2">24/7</div>
              <div className="text-sm font-semibold text-white/85">Platform Access</div>
            </div>
          </div>
        </div>
      </section>

      {/* Target Users */}
      <section className="py-20 sm:py-24 bg-[#F5F1E5]">
        <div className="container mx-auto px-4">
          <div className="text-center mb-14 md:mb-16">
            <h2 className="font-display text-3xl sm:text-4xl md:text-5xl text-[#123C5C] mb-4 tracking-tight">Who We Serve</h2>
            <p className="text-lg text-[#45586B] max-w-2xl mx-auto">
              Role-based platform designed for all stakeholders
            </p>
          </div>

          <div className="grid md:grid-cols-3 gap-8">
            <Card className="text-center rounded-2xl border-2 border-[#E7E1D0] hover:-translate-y-1 shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden bg-white">
              <div className="h-2.5 bg-[#22C55E]"></div>
              <CardContent className="p-8">
                <div className="w-16 h-16 bg-[#22C55E]/15 rounded-full flex items-center justify-center mx-auto mb-4">
                  <Sprout className="w-8 h-8 text-[#22C55E]" />
                </div>
                <h3 className="text-2xl font-bold text-[#123C5C] mb-3">Producers</h3>
                <p className="text-[#45586B] mb-4">
                  Farmers and fishers in Aparri seeking better market access and income stability
                </p>
                <ul className="text-sm text-[#45586B] text-left space-y-2 inline-block">
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="w-4 h-4 text-[#22C55E] mt-0.5 flex-shrink-0" />
                    <span>Market insights</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="w-4 h-4 text-[#22C55E] mt-0.5 flex-shrink-0" />
                    <span>Program eligibility matching</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="w-4 h-4 text-[#22C55E] mt-0.5 flex-shrink-0" />
                    <span>Direct institutional access</span>
                  </li>
                </ul>
              </CardContent>
            </Card>

            <Card className="text-center rounded-2xl border-2 border-[#E7E1D0] hover:-translate-y-1 shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden bg-white">
              <div className="h-2.5 bg-[#123C5C]"></div>
              <CardContent className="p-8">
                <div className="w-16 h-16 bg-[#123C5C]/10 rounded-full flex items-center justify-center mx-auto mb-4">
                  <Users className="w-8 h-8 text-[#123C5C]" />
                </div>
                <h3 className="text-2xl font-bold text-[#123C5C] mb-3">Institutional Buyers</h3>
                <p className="text-[#45586B] mb-4">
                  LGUs, DA/BFAR, and Kadiwa outlets for policy-compliant procurement
                </p>
                <ul className="text-sm text-[#45586B] text-left space-y-2 inline-block">
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="w-4 h-4 text-[#123C5C] mt-0.5 flex-shrink-0" />
                    <span>Browse verified producers</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="w-4 h-4 text-[#123C5C] mt-0.5 flex-shrink-0" />
                    <span>Post procurement demands</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="w-4 h-4 text-[#123C5C] mt-0.5 flex-shrink-0" />
                    <span>Track order fulfillment</span>
                  </li>
                </ul>
              </CardContent>
            </Card>

            <Card className="text-center rounded-2xl border-2 border-[#E7E1D0] hover:-translate-y-1 shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden bg-white">
              <div className="h-2.5 bg-[#0F9488]"></div>
              <CardContent className="p-8">
                <div className="w-16 h-16 bg-[#0F9488]/15 rounded-full flex items-center justify-center mx-auto mb-4">
                  <Shield className="w-8 h-8 text-[#0F9488]" />
                </div>
                <h3 className="text-2xl font-bold text-[#123C5C] mb-3">Administrators</h3>
                <p className="text-[#45586B] mb-4">
                  DA/LGU personnel managing the platform and ensuring compliance
                </p>
                <ul className="text-sm text-[#45586B] text-left space-y-2 inline-block">
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="w-4 h-4 text-[#0F9488] mt-0.5 flex-shrink-0" />
                    <span>User verification</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="w-4 h-4 text-[#0F9488] mt-0.5 flex-shrink-0" />
                    <span>Analytics &amp; monitoring</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="w-4 h-4 text-[#0F9488] mt-0.5 flex-shrink-0" />
                    <span>Program management</span>
                  </li>
                </ul>
              </CardContent>
            </Card>
          </div>
        </div>
      </section>

      {/* About Section */}
      <section id="about" className="py-20 sm:py-24 bg-white scroll-mt-20 md:scroll-mt-24">
        <div className="container mx-auto px-4">
          <div className="grid md:grid-cols-2 gap-12 items-center">
            <div>
              <h2 className="font-display text-3xl sm:text-4xl md:text-5xl text-[#123C5C] mb-6 tracking-tight">
                About HarborAI
              </h2>
              <p className="text-lg text-[#45586B] mb-6 leading-relaxed">
                HarborAI is a comprehensive web-based decision support and institutional trading platform 
                designed specifically for the agricultural and fishery sectors in Aparri, Cagayan.
              </p>
              <p className="text-lg text-[#45586B] mb-6 leading-relaxed">
                By combining artificial intelligence with local expertise, we help producers identify 
                profitable enterprise opportunities, access government support, and engage in fair, 
                transparent trading with institutional buyers.
              </p>
              <div className="space-y-4">
                <div className="flex items-start gap-3">
                  <div className="w-8 h-8 bg-[#22C55E] rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                    <CheckCircle2 className="w-4 h-4 text-white" />
                  </div>
                  <div>
                    <h4 className="font-bold text-[#123C5C] mb-1">Reduce Middleman Dependency</h4>
                    <p className="text-[#45586B]">Connect directly with institutional buyers</p>
                  </div>
                </div>
                <div className="flex items-start gap-3">
                  <div className="w-8 h-8 bg-[#0F9488] rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                    <CheckCircle2 className="w-4 h-4 text-white" />
                  </div>
                  <div>
                    <h4 className="font-bold text-[#123C5C] mb-1">Improve Income Stability</h4>
                    <p className="text-[#45586B]">Better pricing and consistent demand</p>
                  </div>
                </div>
                <div className="flex items-start gap-3">
                  <div className="w-8 h-8 bg-[#123C5C] rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                    <CheckCircle2 className="w-4 h-4 text-white" />
                  </div>
                  <div>
                    <h4 className="font-bold text-[#123C5C] mb-1">Policy-Aligned Trading</h4>
                    <p className="text-[#45586B]">Compliant with government procurement standards</p>
                  </div>
                </div>
              </div>
            </div>
            <div className="relative rotate-2 hover:rotate-0 transition-transform duration-300">
              <div className="bg-white p-2 sm:p-3 rounded-2xl shadow-xl border-4 border-[#0F9488]">
                <ImageWithFallback 
                  src="https://images.unsplash.com/photo-1637699612250-ab2859a0826e?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxmaXNoJTIwbWFya2V0JTIwdHJhZGluZyUyMHN1c3RhaW5hYmxlfGVufDF8fHx8MTc3MTA4NDg0OHww&ixlib=rb-4.1.0&q=80&w=1080"
                  alt="Fish market trading"
                  className="w-full h-96 object-cover rounded-lg"
                />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-20 sm:py-24 bg-[#22C55E] text-white">
        <div className="container mx-auto px-4 text-center max-w-2xl">
          <h2 className="font-display text-3xl sm:text-4xl md:text-5xl mb-6 tracking-tight">Ready to Transform Your Livelihood?</h2>
          <p className="text-lg text-white/90 mb-8 leading-relaxed">
            Join HarborAI today and gain access to institutional markets, AI-powered insights, and government support programs.
          </p>
          <div className="flex flex-wrap gap-4 justify-center">
            <Link to="/register">
              <Button size="lg" variant="secondary" className="font-bold rounded-full bg-white text-[#15803D] hover:bg-[#F5F1E5] shadow-md hover:scale-105 transition-all duration-200">
                <Sprout className="w-5 h-5 mr-2" />
                Register as Producer
              </Button>
            </Link>
            <Link to="/login">
              <Button size="lg" variant="outline" className="font-bold rounded-full border-2 border-white text-white hover:bg-white hover:text-[#15803D] bg-transparent hover:scale-105 transition-all duration-200">
                Login to Your Account
              </Button>
            </Link>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="bg-[#0E2A44] text-[#CBD5E1] py-12 border-t-4 border-[#22C55E]">
        <div className="container mx-auto px-4">
          <div className="flex flex-col md:flex-row md:justify-between md:items-start gap-10 md:gap-0 mb-8 text-center md:text-left">
            <div className="flex flex-col items-center md:items-start mb-8 md:mb-0 md:w-1/4">
              <div className="flex items-center gap-2 mb-4 justify-center md:justify-start">
                <img src="/logo.png" alt="HarborAI Logo" className="w-8 h-8 object-contain" />
                <span className="font-display text-xl text-white tracking-tight">HarborAI</span>
              </div>
              <p className="text-sm max-w-xs">
                Empowering Aparri's agricultural and fishery sectors through AI-driven innovation.
              </p>
            </div>
            <div className="flex flex-col gap-6 md:flex-row md:gap-16 justify-center md:w-3/4">
              <div>
                <h4 className="font-bold text-white mb-4 uppercase text-sm tracking-wide">Platform</h4>
                <ul className="space-y-2 text-sm">
                  <li><Link to="/programs" className="hover:text-[#4ADE80] transition-colors">Programs</Link></li>
                  <li><a href="#features" className="hover:text-[#4ADE80] transition-colors">Features</a></li>
                  <li><a href="#benefits" className="hover:text-[#4ADE80] transition-colors">Benefits</a></li>
                </ul>
              </div>
              <div>
                <h4 className="font-bold text-white mb-4 uppercase text-sm tracking-wide">Support</h4>
                <ul className="space-y-2 text-sm">
                  <li><a href="#" className="hover:text-[#4ADE80] transition-colors">Help Center</a></li>
                  <li><Link to="/faq" className="hover:text-[#4ADE80] transition-colors">FAQ</Link></li>
                  <li><a href="#" className="hover:text-[#4ADE80] transition-colors">Contact Us</a></li>
                </ul>
              </div>
              <div>
                <h4 className="font-bold text-white mb-4 uppercase text-sm tracking-wide">Legal</h4>
                <ul className="space-y-2 text-sm">
                  <li><Link to="/privacy" className="hover:text-[#4ADE80] transition-colors">Privacy Policy</Link></li>
                  <li><a href="#" className="hover:text-[#4ADE80] transition-colors">Terms of Service</a></li>
                  <li><a href="#" className="hover:text-[#4ADE80] transition-colors">Data Protection</a></li>
                </ul>
              </div>
            </div>
          </div>
          <div className="border-t border-white/10 pt-8 text-center text-sm">
            <p>&copy; 2026 HarborAI. A project for sustainable agri-fish development in Aparri, Cagayan.</p>
          </div>
        </div>
      </footer>
    </div>
  );
}
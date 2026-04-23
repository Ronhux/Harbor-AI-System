import '../../styles/hide-scrollbar.css';
import { Link } from 'react-router-dom';
import { useState } from 'react';
import { Sprout, Fish, TrendingUp, Users, Shield, BarChart3, Brain, CheckCircle2, Menu, X } from 'lucide-react';
import { Button } from '../components/ui/button';
import { Card, CardContent } from '../components/ui/card';
import { ImageWithFallback } from '../components/figma/ImageWithFallback';

export default function LandingPage() {
  const [menuOpen, setMenuOpen] = useState(false);
  return (
    <div className="min-h-screen bg-gradient-to-b from-blue-50 to-white scrollbar-hide" style={{overflowX:'hidden'}}>
      {/* Navigation */}
        <nav className="bg-white shadow-sm fixed top-0 left-0 w-full z-50 h-16 md:h-20 flex items-center">
          <div className="h-16 md:h-20" /> {/* Spacer for fixed navbar, matches nav height */}
        <div className="container mx-auto px-4 py-4 flex items-center justify-between relative">
          <div className="flex items-center gap-3 min-w-0">
            <img src="/logo.png" alt="HarborAI Logo" className="w-10 h-10 sm:w-12 sm:h-12 object-contain" />
            <span className="text-xl sm:text-2xl font-bold text-blue-900 whitespace-nowrap">HarborAI</span>
          </div>
          {/* Desktop Nav */}
          <div className="hidden md:flex items-center gap-6">
            <a href="#features" className="text-gray-700 hover:text-blue-600 transition">Features</a>
            <a href="#benefits" className="text-gray-700 hover:text-blue-600 transition">Benefits</a>
            <Link to="/programs" className="text-gray-700 hover:text-blue-600 transition">Programs</Link>
            <a href="#about" className="text-gray-700 hover:text-blue-600 transition">About</a>
          </div>
          {/* Desktop Auth */}
          <div className="hidden md:flex gap-3">
            <Link to="/login">
              <Button variant="outline">Login</Button>
            </Link>
            <Link to="/register">
              <Button className="bg-blue-600 hover:bg-blue-700">Register as Producer</Button>
            </Link>
          </div>
          {/* Hamburger */}
          <button className="block md:hidden ml-auto p-2 z-20" aria-label="Open menu" onClick={() => setMenuOpen(v => !v)}>
            {menuOpen ? <X className="w-7 h-7" /> : <Menu className="w-7 h-7" />}
          </button>
          {/* Mobile Menu */}
          {menuOpen && (
            <div className="absolute top-full left-0 w-full bg-white shadow-lg border-t z-30 animate-fade-in flex flex-col gap-2 py-4 px-6 md:hidden">
              <a href="#features" className="py-2 text-gray-700 hover:text-blue-600 transition" onClick={()=>setMenuOpen(false)}>Features</a>
              <a href="#benefits" className="py-2 text-gray-700 hover:text-blue-600 transition" onClick={()=>setMenuOpen(false)}>Benefits</a>
              <Link to="/programs" className="py-2 text-gray-700 hover:text-blue-600 transition" onClick={()=>setMenuOpen(false)}>Programs</Link>
              <a href="#about" className="py-2 text-gray-700 hover:text-blue-600 transition" onClick={()=>setMenuOpen(false)}>About</a>
              <div className="flex flex-col gap-2 mt-2">
                <Link to="/login">
                  <Button variant="outline" className="w-full">Login</Button>
                </Link>
                <Link to="/register">
                  <Button className="w-full bg-blue-600 hover:bg-blue-700">Register as Producer</Button>
                </Link>
              </div>
            </div>
          )}
        </div>
      </nav>

      {/* Hero Section */}
      <section className="relative px-2 sm:px-4 py-10 sm:py-20 overflow-hidden pt-20 md:pt-24">
        {/* Background Image with Overlay */}
        <div 
          className="absolute inset-0 bg-cover bg-center bg-no-repeat opacity-20 blur-sm"
          style={{
            backgroundImage: `url('/Images/Front.png')`
          }}
        ></div>
        {/* Gradient Overlay */}
        <div className="absolute inset-0 bg-gradient-to-br from-blue-600/80 via-blue-500/60 to-green-500/40"></div>
        {/* Content */}
        <div className="relative z-10 max-w-6xl mx-auto w-full">
          <div className="flex flex-col-reverse md:flex-row items-center gap-8 md:gap-12">
            {/* Text Content */}
            <div className="w-full md:w-1/2 text-white flex flex-col items-center md:items-start text-center md:text-left">
              <div className="inline-flex items-center gap-2 bg-white/20 backdrop-blur-sm text-white px-3 py-1.5 rounded-full mb-4 border border-white/30 text-xs sm:text-sm">
                <Brain className="w-4 h-4" />
                <span className="font-medium">AI-Powered Agri-Fish Platform</span>
              </div>
              <h1 className="text-3xl sm:text-4xl md:text-5xl font-bold mb-4 sm:mb-6 leading-tight">
                Empowering Aparri's Farmers & Fishers
              </h1>
              <p className="text-base sm:text-lg md:text-xl mb-6 sm:mb-8 opacity-90 max-w-lg">
                HarborAI connects agricultural and fishery producers with institutional buyers, 
                provides AI-driven recommendations, and unlocks access to government support programs.
              </p>
              <div className="flex flex-col sm:flex-row w-full gap-3 sm:gap-4">
                <Link to="/register" className="w-full sm:w-auto">
                  <Button size="lg" className="w-full sm:w-auto bg-green-600 hover:bg-green-700 text-white border-0">
                    <Sprout className="w-5 h-5 mr-2" />
                    Get Started as Producer
                  </Button>
                </Link>
                <Link to="/login" className="w-full sm:w-auto">
                  <Button size="lg" className="w-full sm:w-auto bg-white text-green-600 hover:bg-green-600 hover:text-white border-0">
                    Learn More
                  </Button>
                </Link>
              </div>
              <div className="flex flex-col sm:flex-row items-center gap-4 sm:gap-8 mt-6 sm:mt-8 text-xs sm:text-sm opacity-90">
                <div className="flex items-center gap-2">
                  <CheckCircle2 className="w-5 h-5 text-green-400" />
                  <span>Verified Producers</span>
                </div>
                <div className="flex items-center gap-2">
                  <CheckCircle2 className="w-5 h-5 text-green-400" />
                  <span>Policy-Aligned Trading</span>
                </div>
              </div>
            </div>
            {/* Hero Image */}
            <div className="w-full md:w-1/2 flex justify-center mb-8 md:mb-0">
              <div className="rounded-2xl overflow-hidden shadow-2xl w-full max-w-xs sm:max-w-md md:max-w-lg">
                <ImageWithFallback 
                  src="/Images/Front.png"
                  alt="Farmers, fisherfolk, and AI-powered connectivity"
                  className="w-full h-56 sm:h-80 md:h-96 object-cover mx-auto"
                />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section id="features" className="bg-white py-20">
        <div className="container mx-auto px-4">
          <div className="text-center mb-16">
            <h2 className="text-4xl font-bold text-gray-900 mb-4">
              Comprehensive Platform Features
            </h2>
            <p className="text-xl text-gray-600 max-w-2xl mx-auto">
              Built to support the entire agri-fish value chain in Aparri, Cagayan
            </p>
          </div>
          
          <div className="grid md:grid-cols-3 gap-8">
            <Card className="border-2 hover:border-blue-500 transition">
              <CardContent className="p-6">
                <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                  <Brain className="w-6 h-6 text-blue-600" />
                </div>
                <h3 className="text-xl font-bold mb-3">AI-Driven Recommendations</h3>
                <p className="text-gray-600">
                  Get personalized enterprise opportunities, optimal pricing guidance, and market timing suggestions powered by AI.
                </p>
              </CardContent>
            </Card>

            <Card className="border-2 hover:border-green-500 transition">
              <CardContent className="p-6">
                <div className="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                  <Shield className="w-6 h-6 text-green-600" />
                </div>
                <h3 className="text-xl font-bold mb-3">Government Program Matching</h3>
                <p className="text-gray-600">
                  Discover government support programs you qualify for through NLP-powered eligibility matching.
                </p>
              </CardContent>
            </Card>

            <Card className="border-2 hover:border-purple-500 transition">
              <CardContent className="p-6">
                <div className="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                  <Users className="w-6 h-6 text-purple-600" />
                </div>
                <h3 className="text-xl font-bold mb-3">Institutional Trading</h3>
                <p className="text-gray-600">
                  Connect directly with LGUs, DA/BFAR, and Kadiwa outlets for transparent, policy-aligned procurement.
                </p>
              </CardContent>
            </Card>

            <Card className="border-2 hover:border-orange-500 transition">
              <CardContent className="p-6">
                <div className="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mb-4">
                  <TrendingUp className="w-6 h-6 text-orange-600" />
                </div>
                <h3 className="text-xl font-bold mb-3">Price & Demand Forecasting</h3>
                <p className="text-gray-600">
                  Make informed decisions with AI-assisted price insights and demand predictions.
                </p>
              </CardContent>
            </Card>

            <Card className="border-2 hover:border-cyan-500 transition">
              <CardContent className="p-6">
                <div className="w-12 h-12 bg-cyan-100 rounded-lg flex items-center justify-center mb-4">
                  <BarChart3 className="w-6 h-6 text-cyan-600" />
                </div>
                <h3 className="text-xl font-bold mb-3">Digital Marketplace</h3>
                <p className="text-gray-600">
                  List your products, track orders, and manage commitments all in one secure platform.
                </p>
              </CardContent>
            </Card>

            <Card className="border-2 hover:border-red-500 transition">
              <CardContent className="p-6">
                <div className="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                  <Fish className="w-6 h-6 text-red-600" />
                </div>
                <h3 className="text-xl font-bold mb-3">Enterprise Profiling</h3>
                <p className="text-gray-600">
                  Create comprehensive profiles showcasing your agricultural or fishery enterprise capabilities.
                </p>
              </CardContent>
            </Card>
          </div>
        </div>
      </section>

      {/* Benefits Section */}
      <section id="benefits" className="py-20 bg-gradient-to-r from-blue-600 to-green-600 text-white">
        <div className="container mx-auto px-4">
          <div className="text-center mb-16">
            <h2 className="text-4xl font-bold mb-4">Why Choose HarborAI?</h2>
            <p className="text-xl opacity-90 max-w-2xl mx-auto">
              Transform your livelihood with technology-driven support
            </p>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
            <div className="text-center">
              <div className="text-5xl font-bold mb-2">100%</div>
              <div className="text-lg opacity-90">Transparent Trading</div>
            </div>
            <div className="text-center">
              <div className="text-5xl font-bold mb-2">0</div>
              <div className="text-lg opacity-90">Middlemen Dependencies</div>
            </div>
            <div className="text-center">
              <div className="text-5xl font-bold mb-2">5+</div>
              <div className="text-lg opacity-90">Government Programs</div>
            </div>
            <div className="text-center">
              <div className="text-5xl font-bold mb-2">24/7</div>
              <div className="text-lg opacity-90">Platform Access</div>
            </div>
          </div>
        </div>
      </section>

      {/* Target Users */}
      <section className="py-20 bg-gray-50">
        <div className="container mx-auto px-4">
          <div className="text-center mb-16">
            <h2 className="text-4xl font-bold text-gray-900 mb-4">Who We Serve</h2>
            <p className="text-xl text-gray-600 max-w-2xl mx-auto">
              Role-based platform designed for all stakeholders
            </p>
          </div>

          <div className="grid md:grid-cols-3 gap-8">
            <Card className="text-center">
              <CardContent className="p-8">
                <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                  <Sprout className="w-8 h-8 text-green-600" />
                </div>
                <h3 className="text-2xl font-bold mb-3">Producers</h3>
                <p className="text-gray-600 mb-4">
                  Farmers and fishers in Aparri seeking better market access and income stability
                </p>
                <ul className="text-sm text-gray-600 text-left space-y-2">
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="w-4 h-4 text-green-600 mt-0.5 flex-shrink-0" />
                    <span>Enterprise recommendations</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="w-4 h-4 text-green-600 mt-0.5 flex-shrink-0" />
                    <span>Program eligibility matching</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="w-4 h-4 text-green-600 mt-0.5 flex-shrink-0" />
                    <span>Direct institutional access</span>
                  </li>
                </ul>
              </CardContent>
            </Card>

            <Card className="text-center">
              <CardContent className="p-8">
                <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                  <Users className="w-8 h-8 text-blue-600" />
                </div>
                <h3 className="text-2xl font-bold mb-3">Institutional Buyers</h3>
                <p className="text-gray-600 mb-4">
                  LGUs, DA/BFAR, and Kadiwa outlets for policy-compliant procurement
                </p>
                <ul className="text-sm text-gray-600 text-left space-y-2">
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" />
                    <span>Browse verified producers</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" />
                    <span>Post procurement demands</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" />
                    <span>Track order fulfillment</span>
                  </li>
                </ul>
              </CardContent>
            </Card>

            <Card className="text-center">
              <CardContent className="p-8">
                <div className="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                  <Shield className="w-8 h-8 text-purple-600" />
                </div>
                <h3 className="text-2xl font-bold mb-3">Administrators</h3>
                <p className="text-gray-600 mb-4">
                  DA/LGU personnel managing the platform and ensuring compliance
                </p>
                <ul className="text-sm text-gray-600 text-left space-y-2">
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="w-4 h-4 text-purple-600 mt-0.5 flex-shrink-0" />
                    <span>User verification</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="w-4 h-4 text-purple-600 mt-0.5 flex-shrink-0" />
                    <span>Analytics & monitoring</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle2 className="w-4 h-4 text-purple-600 mt-0.5 flex-shrink-0" />
                    <span>Program management</span>
                  </li>
                </ul>
              </CardContent>
            </Card>
          </div>
        </div>
      </section>

      {/* About Section */}
      <section id="about" className="py-20 bg-white">
        <div className="container mx-auto px-4">
          <div className="grid md:grid-cols-2 gap-12 items-center">
            <div>
              <h2 className="text-4xl font-bold text-gray-900 mb-6">
                About HarborAI
              </h2>
              <p className="text-lg text-gray-600 mb-6">
                HarborAI is a comprehensive web-based decision support and institutional trading platform 
                designed specifically for the agricultural and fishery sectors in Aparri, Cagayan.
              </p>
              <p className="text-lg text-gray-600 mb-6">
                By combining artificial intelligence with local expertise, we help producers identify 
                profitable enterprise opportunities, access government support, and engage in fair, 
                transparent trading with institutional buyers.
              </p>
              <div className="space-y-4">
                <div className="flex items-start gap-3">
                  <div className="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                    <CheckCircle2 className="w-4 h-4 text-green-600" />
                  </div>
                  <div>
                    <h4 className="font-bold mb-1">Reduce Middleman Dependency</h4>
                    <p className="text-gray-600">Connect directly with institutional buyers</p>
                  </div>
                </div>
                <div className="flex items-start gap-3">
                  <div className="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                    <CheckCircle2 className="w-4 h-4 text-green-600" />
                  </div>
                  <div>
                    <h4 className="font-bold mb-1">Improve Income Stability</h4>
                    <p className="text-gray-600">Better pricing and consistent demand</p>
                  </div>
                </div>
                <div className="flex items-start gap-3">
                  <div className="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                    <CheckCircle2 className="w-4 h-4 text-green-600" />
                  </div>
                  <div>
                    <h4 className="font-bold mb-1">Policy-Aligned Trading</h4>
                    <p className="text-gray-600">Compliant with government procurement standards</p>
                  </div>
                </div>
              </div>
            </div>
            <div className="rounded-2xl overflow-hidden shadow-xl">
              <ImageWithFallback 
                src="https://images.unsplash.com/photo-1637699612250-ab2859a0826e?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxmaXNoJTIwbWFya2V0JTIwdHJhZGluZyUyMHN1c3RhaW5hYmxlfGVufDF8fHx8MTc3MTA4NDg0OHww&ixlib=rb-4.1.0&q=80&w=1080"
                alt="Fish market trading"
                className="w-full h-96 object-cover"
              />
            </div>
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-20 bg-gradient-to-r from-green-600 to-blue-600 text-white">
        <div className="container mx-auto px-4 text-center">
          <h2 className="text-4xl font-bold mb-6">Ready to Transform Your Livelihood?</h2>
          <p className="text-xl mb-8 opacity-90 max-w-2xl mx-auto">
            Join HarborAI today and gain access to institutional markets, AI-powered insights, and government support programs.
          </p>
          <div className="flex flex-wrap gap-4 justify-center">
            <Link to="/register">
              <Button size="lg" variant="secondary" className="bg-white text-green-700 hover:bg-gray-100">
                <Sprout className="w-5 h-5 mr-2" />
                Register as Producer
              </Button>
            </Link>
            <Link to="/login">
              <Button size="lg" variant="outline" className="border-black text-black hover:bg-black hover:text-white bg-white">
                Login to Your Account
              </Button>
            </Link>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="bg-gray-900 text-gray-300 py-12">
        <div className="container mx-auto px-4">
          <div className="flex flex-col md:flex-row md:justify-between md:items-start gap-10 md:gap-0 mb-8 text-center md:text-left">
            <div className="flex flex-col items-center md:items-start mb-8 md:mb-0 md:w-1/4">
              <div className="flex items-center gap-2 mb-4 justify-center md:justify-start">
                <img src="/logo.png" alt="HarborAI Logo" className="w-8 h-8 object-contain" />
                <span className="text-2xl font-bold text-white">HarborAI</span>
              </div>
              <p className="text-sm max-w-xs">
                Empowering Aparri's agricultural and fishery sectors through AI-driven innovation.
              </p>
            </div>
            <div className="flex flex-col gap-6 md:flex-row md:gap-16 justify-center md:w-3/4">
              <div>
                <h4 className="font-bold text-white mb-4">Platform</h4>
                <ul className="space-y-2 text-sm">
                  <li><Link to="/programs" className="hover:text-white transition">Programs</Link></li>
                  <li><a href="#features" className="hover:text-white transition">Features</a></li>
                  <li><a href="#benefits" className="hover:text-white transition">Benefits</a></li>
                </ul>
              </div>
              <div>
                <h4 className="font-bold text-white mb-4">Support</h4>
                <ul className="space-y-2 text-sm">
                  <li><a href="#" className="hover:text-white transition">Help Center</a></li>
                  <li><a href="#" className="hover:text-white transition">FAQ</a></li>
                  <li><a href="#" className="hover:text-white transition">Contact Us</a></li>
                </ul>
              </div>
              <div>
                <h4 className="font-bold text-white mb-4">Legal</h4>
                <ul className="space-y-2 text-sm">
                  <li><a href="#" className="hover:text-white transition">Privacy Policy</a></li>
                  <li><a href="#" className="hover:text-white transition">Terms of Service</a></li>
                  <li><a href="#" className="hover:text-white transition">Data Protection</a></li>
                </ul>
              </div>
            </div>
          </div>
          <div className="border-t border-gray-800 pt-8 text-center text-sm">
            <p>&copy; 2026 HarborAI. A project for sustainable agri-fish development in Aparri, Cagayan.</p>
          </div>
        </div>
      </footer>
    </div>
  );
}

import { Link } from 'react-router-dom';
import { Shield, Fish, Sprout, Building2, FileText, ArrowLeft, Brain } from 'lucide-react';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../components/ui/card';
import { Badge } from '../components/ui/badge';

export default function ProgramsPage() {
  const programs = [
    {
      id: 1,
      name: 'Sagip Saka Act (RA 11321)',
      icon: Shield,
      color: 'navy',
      description: 'Comprehensive support for farmers and fisherfolk through consolidation, protection, and enhancement of programs and services.',
      benefits: [
        'Financial assistance for farming and fishing operations',
        'Access to modern farming/fishing equipment',
        'Training and capacity building programs',
        'Insurance and risk mitigation support',
      ],
      eligibility: 'Registered farmers and fisherfolk in Aparri, Cagayan',
      agency: 'Department of Agriculture (DA)',
    },
    {
      id: 2,
      name: 'Kadiwa Program',
      icon: Sprout,
      color: 'green',
      description: 'Direct farm-to-consumer and farm-to-institution marketing program promoting accessible and affordable food.',
      benefits: [
        'Direct market access without middlemen',
        'Fair pricing for producers',
        'Stable and regular demand',
        'Marketing and promotional support',
      ],
      eligibility: 'Verified producers with quality agricultural/fishery products',
      agency: 'Department of Agriculture (DA)',
    },
    {
      id: 3,
      name: 'LGU Direct Procurement',
      icon: Building2,
      color: 'teal',
      description: 'Local Government Unit programs for direct procurement from local producers for public institutions and feeding programs.',
      benefits: [
        'Guaranteed institutional buyers',
        'Regular procurement schedules',
        'Priority for local producers',
        'Transparent pricing mechanisms',
      ],
      eligibility: 'Verified local producers in Aparri municipality',
      agency: 'Local Government Unit of Aparri',
    },
  ];

  const getColorClasses = (color: string) => {
    const colors: Record<string, { bg: string; text: string; badge: string }> = {
      navy: { bg: 'bg-[#123C5C]/10', text: 'text-[#123C5C]', badge: 'bg-[#123C5C]' },
      teal: { bg: 'bg-[#0F9488]/15', text: 'text-[#0F9488]', badge: 'bg-[#0F9488]' },
      green: { bg: 'bg-[#22C55E]/15', text: 'text-[#22C55E]', badge: 'bg-[#22C55E]' },
      cyan: { bg: 'bg-[#0E7490]/15', text: 'text-[#0E7490]', badge: 'bg-[#0E7490]' },
    };
    return colors[color] || colors.navy;
  };

  return (
    <div className="min-h-screen bg-[#F5F1E5] font-body">
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Anton&family=Baloo+2:wght@400;500;600;700;800&display=swap');
        .font-display { font-family: 'Anton', ui-sans-serif, sans-serif; }
        .font-body { font-family: 'Baloo 2', ui-rounded, system-ui, sans-serif; }
      `}</style>

      {/* Navigation */}
      <nav className="bg-[#F5F1E5]/95 backdrop-blur-sm shadow-sm fixed top-0 left-0 w-full z-50 border-b border-[#E7E1D0]">
        <div className="container mx-auto px-4 py-4 flex items-center justify-between relative">
          <div className="flex items-center gap-3 min-w-0">
            <Link to="/" className="flex items-center gap-2 min-w-0">
              <img src="/logo.png" alt="HarborAI Logo" className="w-10 h-10 sm:w-12 sm:h-12 object-contain" />
              <span className="font-display text-xl sm:text-2xl text-[#123C5C] whitespace-nowrap tracking-tight">HarborAI</span>
            </Link>
          </div>
          <div className="flex gap-3 items-center">
            <Link to="/">
              <Button variant="ghost" className="p-2 flex items-center justify-center rounded-full text-[#123C5C] hover:bg-[#123C5C]/5" aria-label="Back to Home">
                <ArrowLeft className="w-6 h-6" />
              </Button>
            </Link>
            <Link to="/login">
              <Button variant="outline" className="rounded-full font-bold border-2 border-[#123C5C] text-[#123C5C] hover:bg-[#123C5C] hover:text-white">
                Login
              </Button>
            </Link>
          </div>
        </div>
      </nav>
      <div className="h-20 md:h-24" /> {/* Spacer for fixed navbar */}

      {/* Header */}
      <section className="container mx-auto px-4 py-16">
        <div className="text-center max-w-3xl mx-auto">
          <div className="inline-flex items-center gap-2 bg-[#22C55E] text-white px-4 py-2 rounded-full mb-6 shadow-sm">
            <Brain className="w-4 h-4" />
            <span className="text-sm font-bold uppercase tracking-wide">AI-Powered Program Matching</span>
          </div>
          <h1 className="font-display text-4xl sm:text-5xl text-[#123C5C] mb-6 tracking-tight">
            Government Support Programs
          </h1>
          <p className="text-xl text-[#45586B]">
            HarborAI uses advanced AI to automatically match you with eligible government programs 
            based on your profile, enterprise, and needs. No manual applications required.
          </p>
        </div>
      </section>

      {/* Programs List */}
      <section className="container mx-auto px-4 pb-20">
        <div className="space-y-6">
          {programs.map((program) => {
            const Icon = program.icon;
            const colors = getColorClasses(program.color);
            
            return (
              <Card key={program.id} className="border-2 border-[#E7E1D0] hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300">
                <CardHeader>
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex items-start gap-4 flex-1">
                      <div className={`w-14 h-14 ${colors.bg} rounded-lg flex items-center justify-center flex-shrink-0`}>
                        <Icon className={`w-7 h-7 ${colors.text}`} />
                      </div>
                      <div className="flex-1">
                        <div className="flex items-center gap-3 mb-2">
                          <CardTitle className="text-2xl text-[#123C5C]">{program.name}</CardTitle>
                          <Badge className={`${colors.badge} text-white`}>Active</Badge>
                        </div>
                        <CardDescription className="text-base text-[#45586B]">
                          {program.description}
                        </CardDescription>
                      </div>
                    </div>
                  </div>
                </CardHeader>
                <CardContent>
                  <div className="grid md:grid-cols-2 gap-6">
                    <div>
                      <h4 className="font-bold text-[#123C5C] mb-3">Key Benefits</h4>
                      <ul className="space-y-2">
                        {program.benefits.map((benefit, idx) => (
                          <li key={idx} className="flex items-start gap-2 text-sm text-[#45586B]">
                            <div className={`w-1.5 h-1.5 rounded-full ${colors.badge} mt-1.5 flex-shrink-0`} />
                            <span>{benefit}</span>
                          </li>
                        ))}
                      </ul>
                    </div>
                    <div>
                      <div className="mb-4">
                        <h4 className="font-bold text-[#123C5C] mb-2">Eligibility</h4>
                        <p className="text-sm text-[#45586B]">{program.eligibility}</p>
                      </div>
                      <div>
                        <h4 className="font-bold text-[#123C5C] mb-2">Implementing Agency</h4>
                        <p className="text-sm text-[#45586B]">{program.agency}</p>
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            );
          })}
        </div>

        {/* How It Works */}
        <Card className="mt-12 bg-gradient-to-r from-[#22C55E]/5 via-[#0F9488]/5 to-[#123C5C]/5 border-2 border-[#E7E1D0]">
          <CardHeader>
            <CardTitle className="text-2xl text-[#123C5C] flex items-center gap-2">
              <Brain className="w-6 h-6 text-[#0F9488]" />
              How AI-Powered Program Matching Works
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid md:grid-cols-3 gap-6">
              <div>
                <div className="w-10 h-10 bg-[#123C5C] text-white rounded-full flex items-center justify-center font-bold mb-3">
                  1
                </div>
                <h4 className="font-bold text-[#123C5C] mb-2">Create Your Profile</h4>
                <p className="text-sm text-[#45586B]">
                  Register and provide information about your enterprise, location, and production capabilities.
                </p>
              </div>
              <div>
                <div className="w-10 h-10 bg-[#22C55E] text-white rounded-full flex items-center justify-center font-bold mb-3">
                  2
                </div>
                <h4 className="font-bold text-[#123C5C] mb-2">AI Analyzes Eligibility</h4>
                <p className="text-sm text-[#45586B]">
                  Our NLP-powered system automatically matches your profile with program requirements.
                </p>
              </div>
              <div>
                <div className="w-10 h-10 bg-[#0F9488] text-white rounded-full flex items-center justify-center font-bold mb-3">
                  3
                </div>
                <h4 className="font-bold text-[#123C5C] mb-2">Get Recommendations</h4>
                <p className="text-sm text-[#45586B]">
                  View personalized program recommendations in your dashboard with application guidance.
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* CTA */}
        <div className="mt-12 text-center bg-gradient-to-r from-[#22C55E] to-[#0F9488] text-white rounded-2xl p-12">
          <h3 className="font-display text-3xl mb-4 tracking-tight">Ready to Access These Programs?</h3>
          <p className="text-lg mb-6 opacity-90 max-w-2xl mx-auto">
            Register as a producer to receive AI-powered program recommendations tailored to your needs.
          </p>
          <div className="flex gap-4 justify-center flex-wrap">
            <Link to="/register">
              <Button size="lg" variant="secondary" className="rounded-full font-bold bg-white text-[#15803D] hover:bg-[#F5F1E5]">
                Register as Producer
              </Button>
            </Link>
            <Link to="/login">
              <Button size="lg" variant="outline" className="rounded-full font-bold border-2 border-white text-white hover:bg-white/10">
                Login to Dashboard
              </Button>
            </Link>
          </div>
        </div>
      </section>
    </div>
  );
}
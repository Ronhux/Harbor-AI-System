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
      color: 'blue',
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
      color: 'orange',
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
      blue: { bg: 'bg-blue-100', text: 'text-blue-600', badge: 'bg-blue-500' },
      cyan: { bg: 'bg-cyan-100', text: 'text-cyan-600', badge: 'bg-cyan-500' },
      green: { bg: 'bg-green-100', text: 'text-green-600', badge: 'bg-green-500' },
      purple: { bg: 'bg-purple-100', text: 'text-purple-600', badge: 'bg-purple-500' },
      orange: { bg: 'bg-orange-100', text: 'text-orange-600', badge: 'bg-orange-500' },
    };
    return colors[color] || colors.blue;
  };

  return (
    <div className="min-h-screen bg-gradient-to-b from-blue-50 to-white">
      {/* Navigation */}
      <nav className="bg-white shadow-sm fixed top-0 left-0 w-full z-50">
        <div className="container mx-auto px-4 py-4 flex items-center justify-between relative">
          <div className="flex items-center gap-3 min-w-0">
            <Link to="/" className="flex items-center gap-2 min-w-0">
              <img src="/logo.png" alt="HarborAI Logo" className="w-10 h-10 sm:w-12 sm:h-12 object-contain" />
              <span className="text-xl sm:text-2xl font-bold text-blue-900 whitespace-nowrap">HarborAI</span>
            </Link>
          </div>
          <div className="flex gap-3 items-center">
            <Link to="/">
              <Button variant="ghost" className="p-2 flex items-center justify-center" aria-label="Back to Home">
                <ArrowLeft className="w-6 h-6" />
              </Button>
            </Link>
            <Link to="/login">
              <Button>Login</Button>
            </Link>
          </div>
        </div>
      </nav>
      <div className="h-20 md:h-24" /> {/* Spacer for fixed navbar */}

      {/* Header */}
      <section className="container mx-auto px-4 py-16">
        <div className="text-center max-w-3xl mx-auto">
          <div className="inline-flex items-center gap-2 bg-green-100 text-green-700 px-4 py-2 rounded-full mb-6">
            <Brain className="w-4 h-4" />
            <span className="text-sm font-medium">AI-Powered Program Matching</span>
          </div>
          <h1 className="text-5xl font-bold text-gray-900 mb-6">
            Government Support Programs
          </h1>
          <p className="text-xl text-gray-600">
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
              <Card key={program.id} className="border-2 hover:shadow-lg transition">
                <CardHeader>
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex items-start gap-4 flex-1">
                      <div className={`w-14 h-14 ${colors.bg} rounded-lg flex items-center justify-center flex-shrink-0`}>
                        <Icon className={`w-7 h-7 ${colors.text}`} />
                      </div>
                      <div className="flex-1">
                        <div className="flex items-center gap-3 mb-2">
                          <CardTitle className="text-2xl">{program.name}</CardTitle>
                          <Badge className={`${colors.badge} text-white`}>Active</Badge>
                        </div>
                        <CardDescription className="text-base">
                          {program.description}
                        </CardDescription>
                      </div>
                    </div>
                  </div>
                </CardHeader>
                <CardContent>
                  <div className="grid md:grid-cols-2 gap-6">
                    <div>
                      <h4 className="font-bold text-gray-900 mb-3">Key Benefits</h4>
                      <ul className="space-y-2">
                        {program.benefits.map((benefit, idx) => (
                          <li key={idx} className="flex items-start gap-2 text-sm text-gray-600">
                            <div className={`w-1.5 h-1.5 rounded-full ${colors.badge} mt-1.5 flex-shrink-0`} />
                            <span>{benefit}</span>
                          </li>
                        ))}
                      </ul>
                    </div>
                    <div>
                      <div className="mb-4">
                        <h4 className="font-bold text-gray-900 mb-2">Eligibility</h4>
                        <p className="text-sm text-gray-600">{program.eligibility}</p>
                      </div>
                      <div>
                        <h4 className="font-bold text-gray-900 mb-2">Implementing Agency</h4>
                        <p className="text-sm text-gray-600">{program.agency}</p>
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            );
          })}
        </div>

        {/* How It Works */}
        <Card className="mt-12 bg-gradient-to-r from-blue-50 to-green-50 border-2">
          <CardHeader>
            <CardTitle className="text-2xl flex items-center gap-2">
              <Brain className="w-6 h-6 text-blue-600" />
              How AI-Powered Program Matching Works
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid md:grid-cols-3 gap-6">
              <div>
                <div className="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold mb-3">
                  1
                </div>
                <h4 className="font-bold mb-2">Create Your Profile</h4>
                <p className="text-sm text-gray-600">
                  Register and provide information about your enterprise, location, and production capabilities.
                </p>
              </div>
              <div>
                <div className="w-10 h-10 bg-green-600 text-white rounded-full flex items-center justify-center font-bold mb-3">
                  2
                </div>
                <h4 className="font-bold mb-2">AI Analyzes Eligibility</h4>
                <p className="text-sm text-gray-600">
                  Our NLP-powered system automatically matches your profile with program requirements.
                </p>
              </div>
              <div>
                <div className="w-10 h-10 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold mb-3">
                  3
                </div>
                <h4 className="font-bold mb-2">Get Recommendations</h4>
                <p className="text-sm text-gray-600">
                  View personalized program recommendations in your dashboard with application guidance.
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* CTA */}
        <div className="mt-12 text-center bg-gradient-to-r from-green-600 to-blue-600 text-white rounded-2xl p-12">
          <h3 className="text-3xl font-bold mb-4">Ready to Access These Programs?</h3>
          <p className="text-lg mb-6 opacity-90 max-w-2xl mx-auto">
            Register as a producer to receive AI-powered program recommendations tailored to your needs.
          </p>
          <div className="flex gap-4 justify-center flex-wrap">
            <Link to="/register">
              <Button size="lg" variant="secondary" className="bg-white text-green-700 hover:bg-gray-100">
                Register as Producer
              </Button>
            </Link>
            <Link to="/login">
              <Button size="lg" variant="outline" className="border-white text-white hover:bg-white/10">
                Login to Dashboard
              </Button>
            </Link>
          </div>
        </div>
      </section>
    </div>
  );
}

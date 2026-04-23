import { Brain, TrendingUp, Target, Calendar, MapPin, Lightbulb, CheckCircle2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Badge } from '../../../components/ui/badge';
import { Progress } from '../../../components/ui/progress';

export default function EnterpriseRecommendations() {
  const recommendations = [
    {
      id: 1,
      title: 'High-Value Organic Vegetables',
      match: 92,
      category: 'Agriculture',
      profitPotential: 'High',
      timeToMarket: '45-60 days',
      initialInvestment: '₱15,000 - ₱25,000',
      demandTrend: 'Rising',
      reasons: [
        'Growing demand from Kadiwa outlets in your area',
        'Your soil type is ideal for organic cultivation',
        'Low competition in Aparri region',
        'Year-round production possible',
      ],
      nextSteps: [
        'Attend organic farming training at DA-RFO II',
        'Apply for Sagip Saka Act seed support',
        'Connect with Kadiwa buyers through platform',
      ],
    },
    {
      id: 2,
      title: 'Tilapia Aquaculture Expansion',
      match: 88,
      category: 'Fisheries',
      profitPotential: 'Very High',
      timeToMarket: '4-6 months',
      initialInvestment: '₱30,000 - ₱50,000',
      demandTrend: 'Stable High',
      reasons: [
        'Consistent institutional demand from LGUs',
        'Your current infrastructure can be expanded',
        'Favorable water quality in your location',
        'BFAR support programs available',
      ],
      nextSteps: [
        'Explore BFAR fingerling support program',
        'Request water quality assessment',
        'Connect with feed suppliers',
      ],
    },
    {
      id: 3,
      title: 'Hybrid Rice Production',
      match: 85,
      category: 'Agriculture',
      profitPotential: 'High',
      timeToMarket: '120-130 days',
      initialInvestment: '₱20,000 - ₱35,000',
      demandTrend: 'Growing',
      reasons: [
        'DA-RFO II prioritizing hybrid rice farmers',
        'Higher yield potential than current variety',
        'Direct procurement commitment from LGU',
        'Technical support readily available',
      ],
      nextSteps: [
        'Apply for hybrid seed subsidy',
        'Join farmer field school training',
        'Coordinate with LGU procurement office',
      ],
    },
  ];

  const insights = [
    {
      icon: TrendingUp,
      title: 'Market Timing',
      description: 'Best planting season starts in 2 weeks based on weather and market demand forecasts.',
      color: 'blue',
    },
    {
      icon: Target,
      title: 'Optimal Scale',
      description: 'Starting with 500-1000 sq meters provides best ROI for your current resources.',
      color: 'green',
    },
    {
      icon: MapPin,
      title: 'Location Advantage',
      description: 'Your proximity to Aparri port gives 15% logistics cost advantage.',
      color: 'purple',
    },
  ];

  const getProfitColor = (level: string) => {
    const colors: Record<string, string> = {
      'Very High': 'bg-green-500',
      'High': 'bg-blue-500',
      'Medium': 'bg-yellow-500',
      'Low': 'bg-gray-500',
    };
    return colors[level] || 'bg-gray-500';
  };

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-3">
            <Brain className="w-8 h-8 text-blue-600" />
            Enterprise Recommendations
          </h1>
          <p className="text-gray-600 mt-1">AI-powered opportunities tailored to your profile and market conditions</p>
        </div>
        <Button variant="outline" onClick={() => alert('AI feature not yet implemented. This would refresh the analysis with latest market data.')}>
          Refresh Analysis
        </Button>
      </div>

      {/* AI Insights */}
      <div className="grid md:grid-cols-3 gap-4">
        {insights.map((insight, idx) => {
          const Icon = insight.icon;
          return (
            <Card key={idx} className={`border-l-4 border-${insight.color}-500`}>
              <CardContent className="p-4">
                <div className="flex items-start gap-3">
                  <div className={`w-10 h-10 rounded-lg bg-${insight.color}-100 flex items-center justify-center flex-shrink-0`}>
                    <Icon className={`w-5 h-5 text-${insight.color}-600`} />
                  </div>
                  <div>
                    <h3 className="font-bold text-gray-900 mb-1">{insight.title}</h3>
                    <p className="text-sm text-gray-600">{insight.description}</p>
                  </div>
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      {/* Recommendations */}
      <div className="space-y-6">
        {recommendations.map((rec) => (
          <Card key={rec.id} className="border-2 hover:shadow-lg transition">
            <CardHeader>
              <div className="flex items-start justify-between gap-4">
                <div className="flex-1">
                  <div className="flex items-center gap-3 mb-2">
                    <CardTitle className="text-2xl">{rec.title}</CardTitle>
                    <Badge variant="secondary">{rec.category}</Badge>
                  </div>
                  <CardDescription>
                    AI Match Score: <span className="font-bold text-green-600">{rec.match}%</span>
                  </CardDescription>
                  <div className="mt-2">
                    <Progress value={rec.match} className="h-2" />
                  </div>
                </div>
                <Badge className={getProfitColor(rec.profitPotential) + ' text-white'}>
                  {rec.profitPotential} Profit Potential
                </Badge>
              </div>
            </CardHeader>
            <CardContent className="space-y-6">
              {/* Quick Stats */}
              <div className="grid md:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-lg">
                <div>
                  <div className="text-xs text-gray-600 mb-1">Time to Market</div>
                  <div className="font-bold text-gray-900">{rec.timeToMarket}</div>
                </div>
                <div>
                  <div className="text-xs text-gray-600 mb-1">Initial Investment</div>
                  <div className="font-bold text-gray-900">{rec.initialInvestment}</div>
                </div>
                <div>
                  <div className="text-xs text-gray-600 mb-1">Demand Trend</div>
                  <div className="font-bold text-green-600 flex items-center gap-1">
                    <TrendingUp className="w-4 h-4" />
                    {rec.demandTrend}
                  </div>
                </div>
                <div>
                  <div className="text-xs text-gray-600 mb-1">Category</div>
                  <div className="font-bold text-gray-900">{rec.category}</div>
                </div>
              </div>

              <div className="grid md:grid-cols-2 gap-6">
                {/* Why This Recommendation */}
                <div>
                  <div className="flex items-center gap-2 mb-3">
                    <Lightbulb className="w-5 h-5 text-yellow-600" />
                    <h4 className="font-bold text-gray-900">Why This Opportunity?</h4>
                  </div>
                  <ul className="space-y-2">
                    {rec.reasons.map((reason, idx) => (
                      <li key={idx} className="flex items-start gap-2 text-sm text-gray-600">
                        <CheckCircle2 className="w-4 h-4 text-green-600 mt-0.5 flex-shrink-0" />
                        <span>{reason}</span>
                      </li>
                    ))}
                  </ul>
                </div>

                {/* Next Steps */}
                <div>
                  <div className="flex items-center gap-2 mb-3">
                    <Calendar className="w-5 h-5 text-blue-600" />
                    <h4 className="font-bold text-gray-900">Recommended Next Steps</h4>
                  </div>
                  <ul className="space-y-2">
                    {rec.nextSteps.map((step, idx) => (
                      <li key={idx} className="flex items-start gap-2 text-sm text-gray-600">
                        <div className="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0 text-xs font-bold mt-0.5">
                          {idx + 1}
                        </div>
                        <span>{step}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>

              {/* Actions */}
              <div className="flex gap-3 pt-4 border-t">
                <Button className="bg-green-600 hover:bg-green-700" onClick={() => alert('AI feature not yet implemented. This would start the enterprise setup process.')}>
                  Start This Enterprise
                </Button>
                <Button variant="outline" onClick={() => alert('AI feature not yet implemented. This would save the recommendation for later review.')}>
                  Save for Later
                </Button>
                <Button variant="ghost" onClick={() => alert('AI feature not yet implemented. This would show detailed analysis and projections.')}>
                  View Full Analysis
                </Button>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Additional Info */}
      <Card className="bg-blue-50 border-blue-200">
        <CardContent className="p-6">
          <div className="flex items-start gap-4">
            <div className="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
              <Brain className="w-6 h-6 text-white" />
            </div>
            <div>
              <h3 className="font-bold text-blue-900 mb-2">How AI Recommendations Work</h3>
              <p className="text-sm text-blue-800 mb-3">
                Our AI analyzes your location, current capabilities, soil/water conditions, market demand, 
                government programs, and seasonal factors to identify the best opportunities for your enterprise.
              </p>
              <p className="text-sm text-blue-800">
                Recommendations are updated weekly based on real-time market data and procurement patterns.
              </p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

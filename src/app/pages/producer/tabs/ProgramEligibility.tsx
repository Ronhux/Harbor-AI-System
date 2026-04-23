import { useState } from 'react';
import { Shield, CheckCircle2, Clock, AlertCircle, ExternalLink, FileText, Download } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Badge } from '../../../components/ui/badge';
import { Progress } from '../../../components/ui/progress';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '../../../components/ui/dialog';

export default function ProgramEligibility() {
  const [selectedProgram, setSelectedProgram] = useState<typeof programs[0] | null>(null);
  const programs = [
    {
      id: 1,
      name: 'Sagip Saka Act (RA 11321)',
      agency: 'Department of Agriculture',
      status: 'Eligible',
      match: 95,
      benefits: '₱25,000 - ₱50,000 in financial assistance',
      requirements: [
        { name: 'Registered Farmer/Fisher', met: true },
        { name: 'Active in last 2 years', met: true },
        { name: 'Land ownership/lease proof', met: true },
        { name: 'Barangay certification', met: false },
      ],
      nextSteps: 'Submit barangay certification to complete application',
      deadline: 'February 28, 2026',
    },
    {
      id: 2,
      name: 'Kadiwa Program',
      agency: 'Department of Agriculture',
      status: 'Eligible',
      match: 100,
      benefits: 'Direct market access, Fair pricing, Regular orders',
      requirements: [
        { name: 'Quality product standards', met: true },
        { name: 'Consistent supply capability', met: true },
        { name: 'Product certification', met: true },
        { name: 'Profile verification', met: true },
      ],
      nextSteps: 'Ready to connect with Kadiwa outlets',
      deadline: 'Ongoing',
    },
    {
      id: 3,
      name: 'LGU Direct Procurement',
      agency: 'LGU Aparri',
      status: 'Eligible',
      match: 100,
      benefits: 'Priority procurement, Stable demand, Fair pricing',
      requirements: [
        { name: 'Aparri resident', met: true },
        { name: 'Verified producer status', met: true },
        { name: 'Food safety compliance', met: true },
        { name: 'Delivery capability', met: true },
      ],
      nextSteps: 'Ready for LGU procurement participation',
      deadline: 'Ongoing',
    },
  ];

  const getStatusBadge = (status: string) => {
    const variants: Record<string, { variant: any; color: string }> = {
      'Eligible': { variant: 'default', color: 'bg-green-500' },
      'Partially Eligible': { variant: 'secondary', color: 'bg-yellow-500' },
      'Monitoring': { variant: 'outline', color: 'bg-gray-500' },
      'Not Eligible': { variant: 'destructive', color: 'bg-red-500' },
    };
    return variants[status] || variants['Monitoring'];
  };

  const getStatusIcon = (status: string) => {
    if (status === 'Eligible') return <CheckCircle2 className="w-5 h-5 text-green-600" />;
    if (status === 'Partially Eligible') return <Clock className="w-5 h-5 text-yellow-600" />;
    return <AlertCircle className="w-5 h-5 text-gray-600" />;
  };

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-3">
          <Shield className="w-8 h-8 text-blue-600" />
          Program Eligibility
        </h1>
        <p className="text-gray-600 mt-1">AI-matched government programs based on your profile</p>
      </div>

      {/* Summary Stats */}
      <div className="grid md:grid-cols-4 gap-4">
        <Card>
          <CardContent className="p-4">
            <div className="text-3xl font-bold text-green-600">2</div>
            <div className="text-sm text-gray-600">Fully Eligible</div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <div className="text-3xl font-bold text-yellow-600">1</div>
            <div className="text-sm text-gray-600">Partially Eligible</div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <div className="text-3xl font-bold text-blue-600">2</div>
            <div className="text-sm text-gray-600">Active Programs</div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <div className="text-3xl font-bold text-purple-600">₱75K+</div>
            <div className="text-sm text-gray-600">Potential Benefits</div>
          </CardContent>
        </Card>
      </div>

      {/* Programs List */}
      <div className="space-y-4">
        {programs.map((program) => {
          const statusConfig = getStatusBadge(program.status);
          const completedReqs = program.requirements.filter(r => r.met).length;
          const totalReqs = program.requirements.length;
          const completionRate = (completedReqs / totalReqs) * 100;

          return (
            <Card key={program.id} className="border-2 hover:shadow-lg transition">
              <CardHeader>
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1">
                    <div className="flex items-center gap-3 mb-2">
                      {getStatusIcon(program.status)}
                      <CardTitle className="text-xl">{program.name}</CardTitle>
                      <Badge className={statusConfig.color + ' text-white'}>
                        {program.status}
                      </Badge>
                    </div>
                    <CardDescription>{program.agency}</CardDescription>
                    <div className="mt-2">
                      <div className="text-sm text-gray-600 mb-1">
                        Match Score: <span className="font-bold text-blue-600">{program.match}%</span>
                      </div>
                      <Progress value={program.match} className="h-2" />
                    </div>
                  </div>
                </div>
              </CardHeader>
              <CardContent className="space-y-4">
                {/* Benefits */}
                <div className="p-4 bg-blue-50 rounded-lg">
                  <h4 className="font-bold text-blue-900 mb-2">Program Benefits</h4>
                  <p className="text-sm text-blue-800">{program.benefits}</p>
                </div>

                {/* Requirements */}
                <div>
                  <div className="flex items-center justify-between mb-3">
                    <h4 className="font-bold text-gray-900">Eligibility Requirements</h4>
                    <span className="text-sm text-gray-600">
                      {completedReqs} of {totalReqs} completed ({Math.round(completionRate)}%)
                    </span>
                  </div>
                  <div className="space-y-2">
                    {program.requirements.map((req, idx) => (
                      <div key={idx} className="flex items-center justify-between p-3 border rounded-lg">
                        <div className="flex items-center gap-3">
                          {req.met ? (
                            <CheckCircle2 className="w-5 h-5 text-green-600" />
                          ) : (
                            <Clock className="w-5 h-5 text-yellow-600" />
                          )}
                          <span className={req.met ? 'text-gray-900' : 'text-gray-600'}>
                            {req.name}
                          </span>
                        </div>
                        {req.met ? (
                          <Badge variant="outline" className="text-green-700 border-green-300">
                            Completed
                          </Badge>
                        ) : (
                          <Badge variant="outline" className="text-yellow-700 border-yellow-300">
                            Pending
                          </Badge>
                        )}
                      </div>
                    ))}
                  </div>
                </div>

                {/* Next Steps */}
                <div className="flex items-start gap-3 p-4 bg-green-50 border border-green-200 rounded-lg">
                  <AlertCircle className="w-5 h-5 text-green-600 mt-0.5" />
                  <div className="flex-1">
                    <h4 className="font-bold text-green-900 mb-1">Next Steps</h4>
                    <p className="text-sm text-green-800">{program.nextSteps}</p>
                  </div>
                </div>

                {/* Deadline & Actions */}
                <div className="flex items-center justify-between pt-4 border-t">
                  <div className="text-sm text-gray-600">
                    <span className="font-medium">Deadline:</span> {program.deadline}
                  </div>
                  <div className="flex gap-2">
                    <Dialog>
                      <DialogTrigger asChild>
                        <Button variant="outline" size="sm" onClick={() => setSelectedProgram(program)}>
                          <FileText className="w-4 h-4 mr-2" />
                          View Details
                        </Button>
                      </DialogTrigger>
                      {selectedProgram && (
                        <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
                          <DialogHeader>
                            <DialogTitle className="text-2xl">{selectedProgram.name}</DialogTitle>
                            <DialogDescription>{selectedProgram.agency}</DialogDescription>
                          </DialogHeader>
                          <div className="space-y-6">
                            <div>
                              <h4 className="font-bold text-gray-900 mb-2">Program Overview</h4>
                              <p className="text-sm text-gray-600">
                                Comprehensive support for farmers and fisherfolk through consolidation, protection, and enhancement of programs and services.
                              </p>
                            </div>
                            <div>
                              <h4 className="font-bold text-gray-900 mb-3">Key Benefits</h4>
                              <ul className="space-y-2">
                                <li className="flex items-start gap-2 text-sm text-gray-600">
                                  <CheckCircle2 className="w-4 h-4 text-green-600 mt-0.5 flex-shrink-0" />
                                  <span>Financial assistance for farming and fishing operations</span>
                                </li>
                                <li className="flex items-start gap-2 text-sm text-gray-600">
                                  <CheckCircle2 className="w-4 h-4 text-green-600 mt-0.5 flex-shrink-0" />
                                  <span>Access to modern farming/fishing equipment</span>
                                </li>
                                <li className="flex items-start gap-2 text-sm text-gray-600">
                                  <CheckCircle2 className="w-4 h-4 text-green-600 mt-0.5 flex-shrink-0" />
                                  <span>Training and capacity building programs</span>
                                </li>
                                <li className="flex items-start gap-2 text-sm text-gray-600">
                                  <CheckCircle2 className="w-4 h-4 text-green-600 mt-0.5 flex-shrink-0" />
                                  <span>Insurance and risk mitigation support</span>
                                </li>
                              </ul>
                            </div>
                            <div>
                              <h4 className="font-bold text-gray-900 mb-2">Eligibility Requirements</h4>
                              <div className="space-y-2">
                                {selectedProgram.requirements.map((req, idx) => (
                                  <div key={idx} className="flex items-center justify-between p-3 border rounded-lg">
                                    <div className="flex items-center gap-3">
                                      {req.met ? (
                                        <CheckCircle2 className="w-5 h-5 text-green-600" />
                                      ) : (
                                        <Clock className="w-5 h-5 text-yellow-600" />
                                      )}
                                      <span className={req.met ? 'text-gray-900' : 'text-gray-600'}>
                                        {req.name}
                                      </span>
                                    </div>
                                    {req.met ? (
                                      <Badge variant="outline" className="text-green-700 border-green-300">
                                        Completed
                                      </Badge>
                                    ) : (
                                      <Badge variant="outline" className="text-yellow-700 border-yellow-300">
                                        Pending
                                      </Badge>
                                    )}
                                  </div>
                                ))}
                              </div>
                            </div>
                            <div className="p-4 bg-blue-50 rounded-lg">
                              <h4 className="font-bold text-blue-900 mb-2">Next Steps</h4>
                              <p className="text-sm text-blue-800">{selectedProgram.nextSteps}</p>
                            </div>
                            <div className="flex items-center justify-between pt-4 border-t">
                              <div className="text-sm text-gray-600">
                                <span className="font-medium">Deadline:</span> {selectedProgram.deadline}
                              </div>
                              <div className="flex gap-2">
                                {selectedProgram.status === 'Eligible' && (
                                  <Button size="sm" className="bg-green-600 hover:bg-green-700">
                                    <Download className="w-4 h-4 mr-2" />
                                    Apply Now
                                  </Button>
                                )}
                                {selectedProgram.status === 'Partially Eligible' && (
                                  <Button size="sm" variant="secondary">
                                    Complete Requirements
                                  </Button>
                                )}
                              </div>
                            </div>
                          </div>
                        </DialogContent>
                      )}
                    </Dialog>
                    {program.status === 'Eligible' && (
                      <Button size="sm" className="bg-green-600 hover:bg-green-700">
                        <Download className="w-4 h-4 mr-2" />
                        Apply Now
                      </Button>
                    )}
                    {program.status === 'Partially Eligible' && (
                      <Button size="sm" variant="secondary">
                        Complete Requirements
                      </Button>
                    )}
                  </div>
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      {/* Help Section */}
      <Card className="bg-purple-50 border-purple-200">
        <CardContent className="p-6">
          <div className="flex items-start gap-4">
            <div className="w-12 h-12 bg-purple-600 rounded-full flex items-center justify-center flex-shrink-0">
              <Shield className="w-6 h-6 text-white" />
            </div>
            <div>
              <h3 className="font-bold text-purple-900 mb-2">Need Help with Applications?</h3>
              <p className="text-sm text-purple-800 mb-3">
                Our AI system automatically checks your eligibility, but you can also get personalized 
                assistance from DA/LGU personnel for any program applications.
              </p>
              <div className="flex gap-2">
                <Button variant="outline" size="sm" className="border-purple-600 text-purple-700">
                  <ExternalLink className="w-4 h-4 mr-2" />
                  Contact Support
                </Button>
                <Button variant="outline" size="sm" className="border-purple-600 text-purple-700">
                  View FAQs
                </Button>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

import { FileText, Users, CheckCircle2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../components/ui/card';
import { Badge } from '../../../components/ui/badge';
import { Button } from '../../../components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '../../../components/ui/dialog';
import { useState } from 'react';

export default function ProgramManagement() {
  const [selectedProgram, setSelectedProgram] = useState<typeof programs[0] | null>(null);
  const programs = [
    { id: 1, name: 'Sagip Saka Act (RA 11321)', applicants: 45, approved: 32, pending: 13, budget: '₱1.2M' },
    { id: 2, name: 'Kadiwa Program', applicants: 78, approved: 78, pending: 0, budget: 'N/A' },
    { id: 3, name: 'LGU Direct Procurement', applicants: 156, approved: 142, pending: 14, budget: '₱2.8M' },
  ];

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-gray-900">Program Management</h1>
        <p className="text-gray-600 mt-1">Oversee government support programs</p>
      </div>

      <div className="grid md:grid-cols-2 gap-6">
        {programs.map((program) => (
          <Card key={program.id} className="border-2">
            <CardHeader>
              <CardTitle className="text-lg">{program.name}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-3 gap-4 text-center">
                <div>
                  <div className="text-2xl font-bold text-blue-600">{program.applicants}</div>
                  <div className="text-xs text-gray-600">Applicants</div>
                </div>
                <div>
                  <div className="text-2xl font-bold text-green-600">{program.approved}</div>
                  <div className="text-xs text-gray-600">Approved</div>
                </div>
                <div>
                  <div className="text-2xl font-bold text-yellow-600">{program.pending}</div>
                  <div className="text-xs text-gray-600">Pending</div>
                </div>
              </div>
              <div className="flex justify-between items-center pt-3 border-t">
                <div className="text-sm">
                  <span className="text-gray-600">Budget:</span>
                  <span className="font-bold ml-2">{program.budget}</span>
                </div>
                <Dialog>
                  <DialogTrigger asChild>
                    <Button size="sm" variant="outline" onClick={() => setSelectedProgram(program)}>Manage</Button>
                  </DialogTrigger>
                  <DialogContent>
                    <DialogHeader>
                      <DialogTitle>{selectedProgram?.name} - Management</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                      <div className="grid grid-cols-3 gap-4 text-center">
                        <div>
                          <div className="text-3xl font-bold text-blue-600">{selectedProgram?.applicants}</div>
                          <div className="text-sm text-gray-600">Number of Applicants</div>
                        </div>
                        <div>
                          <div className="text-3xl font-bold text-green-600">{selectedProgram?.approved}</div>
                          <div className="text-sm text-gray-600">Number of Approved</div>
                        </div>
                        <div>
                          <div className="text-3xl font-bold text-yellow-600">{selectedProgram?.pending}</div>
                          <div className="text-sm text-gray-600">Number of Pending</div>
                        </div>
                      </div>
                    </div>
                  </DialogContent>
                </Dialog>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}

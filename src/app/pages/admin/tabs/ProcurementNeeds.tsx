import { useState } from 'react';
import { ShoppingCart, Plus, Edit, CheckCircle2, Clock } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Badge } from '../../../components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '../../../components/ui/dialog';
import { Input } from '../../../components/ui/input';
import { Label } from '../../../components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../../components/ui/select';

export default function ProcurementNeeds() {
  const [showNewProcurement, setShowNewProcurement] = useState(false);
  const [selectedProcurement, setSelectedProcurement] = useState<typeof procurements[0] | null>(null);
  const [showMatches, setShowMatches] = useState(false);
  const procurements = [
    { id: 1, buyer: 'LGU Aparri', product: 'Rice', quantity: '2000 kg', deadline: '2026-02-28', status: 'Active', matches: 15 },
    { id: 2, buyer: 'Kadiwa Outlet 1', product: 'Fresh Fish', quantity: '500 kg', deadline: '2026-02-20', status: 'Active', matches: 8 },
    { id: 3, buyer: 'DA-RFO II', product: 'Corn', quantity: '1500 kg', deadline: '2026-03-05', status: 'Active', matches: 12 },
    { id: 4, buyer: 'BFAR', product: 'Tilapia', quantity: '800 kg', deadline: '2026-02-25', status: 'Fulfilled', matches: 6 },
  ];

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Procurement Needs</h1>
          <p className="text-gray-600 mt-1">Manage institutional procurement requests</p>
        </div>
        <Button className="bg-purple-600 hover:bg-purple-700" onClick={() => setShowNewProcurement(true)}>
          <Plus className="w-4 h-4 mr-2" />
          New Procurement
        </Button>
      </div>

      <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
        {procurements.map((proc) => (
          <Card key={proc.id} className="border-2">
            <CardHeader>
              <div className="flex items-start justify-between">
                <div>
                  <CardTitle className="text-lg">{proc.product}</CardTitle>
                  <p className="text-sm text-gray-600 mt-1">{proc.buyer}</p>
                </div>
                <Badge className={proc.status === 'Active' ? 'bg-green-500' : 'bg-gray-500'}>
                  {proc.status}
                </Badge>
              </div>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Quantity:</span>
                <span className="font-medium">{proc.quantity}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Deadline:</span>
                <span className="font-medium">{proc.deadline}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Matching Producers:</span>
                <span className="font-bold text-blue-600">{proc.matches}</span>
              </div>
              <div className="flex gap-2 pt-3 border-t">
                <Button variant="outline" size="sm" className="flex-1" onClick={() => setSelectedProcurement(proc)}>
                  <Edit className="w-4 h-4 mr-1" />
                  Edit
                </Button>
                <Button size="sm" className="flex-1" onClick={() => { setSelectedProcurement(proc); setShowMatches(true); }}>View Matches</Button>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* New Procurement Dialog */}
      <Dialog open={showNewProcurement} onOpenChange={setShowNewProcurement}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Add New Procurement</DialogTitle>
            <DialogDescription>Create a new procurement request</DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div>
              <Label>Buyer/Institution</Label>
              <Input placeholder="e.g., LGU Aparri" />
            </div>
            <div>
              <Label>Product Type</Label>
              <Select>
                <SelectTrigger>
                  <SelectValue placeholder="Select product" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="rice">Rice</SelectItem>
                  <SelectItem value="corn">Corn</SelectItem>
                  <SelectItem value="fish">Fresh Fish</SelectItem>
                  <SelectItem value="vegetables">Vegetables</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label>Quantity</Label>
              <Input type="number" placeholder="e.g., 2000" />
            </div>
            <div>
              <Label>Unit</Label>
              <Select>
                <SelectTrigger>
                  <SelectValue placeholder="Select unit" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="kg">Kilogram (kg)</SelectItem>
                  <SelectItem value="sack">Sack</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label>Deadline</Label>
              <Input type="date" />
            </div>
            <div className="flex gap-2 pt-4">
              <Button variant="outline" onClick={() => setShowNewProcurement(false)} className="flex-1">Cancel</Button>
              <Button className="flex-1 bg-purple-600 hover:bg-purple-700" onClick={() => { alert('Procurement added successfully!'); setShowNewProcurement(false); }}>Add Procurement</Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      {/* View Matches Dialog */}
      <Dialog open={showMatches} onOpenChange={setShowMatches}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Matched Producers</DialogTitle>
            <DialogDescription>Producers matching the procurement request for {selectedProcurement?.product}</DialogDescription>
          </DialogHeader>
          {selectedProcurement && (
            <div className="space-y-4">
              <div className="p-4 bg-gray-50 rounded-lg">
                <h4 className="font-bold mb-2">Procurement Details</h4>
                <p className="text-sm text-gray-600">{selectedProcurement.buyer} • {selectedProcurement.quantity} • Due: {selectedProcurement.deadline}</p>
              </div>
              <div className="space-y-3">
                {/* Mock matched producers */}
                <div className="p-4 border rounded-lg">
                  <div className="flex justify-between items-center">
                    <div>
                      <p className="font-bold">Juan Dela Cruz</p>
                      <p className="text-sm text-gray-600">Brgy. Centro • Rice Farmer • 4.8★</p>
                      <p className="text-sm text-green-600">Available: 500 kg • ₱120/kg</p>
                    </div>
                    <Button size="sm">Contact Producer</Button>
                  </div>
                </div>
                <div className="p-4 border rounded-lg">
                  <div className="flex justify-between items-center">
                    <div>
                      <p className="font-bold">Maria Santos</p>
                      <p className="text-sm text-gray-600">Brgy. Macanaya • Farmer • 4.9★</p>
                      <p className="text-sm text-green-600">Available: 800 kg • ₱115/kg</p>
                    </div>
                    <Button size="sm">Contact Producer</Button>
                  </div>
                </div>
                <div className="p-4 border rounded-lg">
                  <div className="flex justify-between items-center">
                    <div>
                      <p className="font-bold">Pedro Reyes</p>
                      <p className="text-sm text-gray-600">Brgy. Sanja • Fisher • 4.7★</p>
                      <p className="text-sm text-green-600">Available: 300 kg • ₱125/kg</p>
                    </div>
                    <Button size="sm">Contact Producer</Button>
                  </div>
                </div>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}

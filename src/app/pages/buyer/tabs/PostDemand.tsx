import { useState } from 'react';
import { FileText, Plus, Calendar } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Input } from '../../../components/ui/input';
import { Label } from '../../../components/ui/label';
import { Textarea } from '../../../components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../../components/ui/select';
import { Badge } from '../../../components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '../../../components/ui/dialog';

export default function PostDemand() {
  const [selectedDemand, setSelectedDemand] = useState<typeof postedDemands[0] | null>(null);
  const postedDemands = [
    { id: 1, product: 'Rice', quantity: '2000 kg', deadline: '2026-02-28', matches: 15, status: 'Active' },
    { id: 2, product: 'Fresh Fish', quantity: '500 kg', deadline: '2026-02-20', matches: 8, status: 'Active' },
  ];

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-gray-900">Post Procurement Demand</h1>
        <p className="text-gray-600 mt-1">Create procurement requests and receive matches from producers</p>
      </div>

      <div className="grid lg:grid-cols-3 gap-6">
        {/* Post Form */}
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>New Procurement Request</CardTitle>
            <CardDescription>Fill in the details of your procurement need</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="product">Product Type</Label>
                <Select>
                  <SelectTrigger id="product">
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
                <Label htmlFor="quantity">Quantity Needed</Label>
                <Input id="quantity" type="number" placeholder="e.g., 2000" />
              </div>
            </div>

            <div className="grid md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="unit">Unit</Label>
                <Select>
                  <SelectTrigger id="unit">
                    <SelectValue placeholder="Select unit" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="kg">Kilogram (kg)</SelectItem>
                    <SelectItem value="sack">Sack</SelectItem>
                    <SelectItem value="piece">Piece</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label htmlFor="deadline">Deadline</Label>
                <Input id="deadline" type="date" />
              </div>
            </div>

            <div>
              <Label htmlFor="budget">Budget Range (Optional)</Label>
              <Input id="budget" placeholder="e.g., ₱100-120 per kg" />
            </div>

            <div>
              <Label htmlFor="requirements">Special Requirements</Label>
              <Textarea 
                id="requirements" 
                placeholder="Quality standards, certifications, delivery preferences..."
                rows={3}
              />
            </div>

            <Button className="w-full bg-blue-600 hover:bg-blue-700">
              <Plus className="w-4 h-4 mr-2" />
              Post Procurement Demand
            </Button>
          </CardContent>
        </Card>

        {/* Posted Demands */}
        <Card>
          <CardHeader>
            <CardTitle>Your Posted Demands</CardTitle>
            <CardDescription>Active procurement requests</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {postedDemands.map((demand) => (
                <div key={demand.id} className="p-3 border rounded-lg">
                  <div className="flex items-start justify-between mb-2">
                    <h4 className="font-bold text-gray-900">{demand.product}</h4>
                    <Badge className="bg-green-500">Active</Badge>
                  </div>
                  <div className="text-sm text-gray-600 space-y-1 mb-3">
                    <div>Quantity: {demand.quantity}</div>
                    <div className="flex items-center gap-1">
                      <Calendar className="w-3 h-3" />
                      <span>Due: {demand.deadline}</span>
                    </div>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium text-blue-600">{demand.matches} matches</span>
                    <Button size="sm" variant="outline" onClick={() => setSelectedDemand(demand)}>View</Button>
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Demand Details Dialog */}
      <Dialog open={!!selectedDemand} onOpenChange={() => setSelectedDemand(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Demand Details</DialogTitle>
            <DialogDescription>Procurement request for {selectedDemand?.product}</DialogDescription>
          </DialogHeader>
          {selectedDemand && (
            <div className="space-y-4">
              <div className="space-y-3">
                <div className="flex justify-between">
                  <span className="text-gray-600">Product:</span>
                  <span className="font-bold">{selectedDemand.product}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600">Quantity:</span>
                  <span className="font-bold">{selectedDemand.quantity}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600">Deadline:</span>
                  <span className="font-bold">{selectedDemand.deadline}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600">Status:</span>
                  <Badge className="bg-green-500">{selectedDemand.status}</Badge>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600">Matches Found:</span>
                  <span className="font-bold text-blue-600">{selectedDemand.matches} producers</span>
                </div>
              </div>
              <div className="pt-4 border-t">
                <h4 className="font-bold mb-3">Matched Producers</h4>
                <div className="space-y-2">
                  {/* Mock matched producers */}
                  <div className="p-3 bg-gray-50 rounded-lg">
                    <div className="flex justify-between items-center">
                      <div>
                        <p className="font-medium">Juan Dela Cruz</p>
                        <p className="text-sm text-gray-600">Brgy. Centro • Rice Farmer</p>
                      </div>
                      <Button size="sm" variant="outline">Contact</Button>
                    </div>
                  </div>
                  <div className="p-3 bg-gray-50 rounded-lg">
                    <div className="flex justify-between items-center">
                      <div>
                        <p className="font-medium">Maria Santos</p>
                        <p className="text-sm text-gray-600">Brgy. Macanaya • Farmer</p>
                      </div>
                      <Button size="sm" variant="outline">Contact</Button>
                    </div>
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

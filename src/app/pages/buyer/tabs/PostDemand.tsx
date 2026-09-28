import { FormEvent, useEffect, useState } from 'react';
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
  type Demand = {
    id: number;
    product: string;
    quantity: string;
    deadline: string;
    matches: number;
    status: string;
  };

  const [selectedDemand, setSelectedDemand] = useState<Demand | null>(null);
  const [postedDemands, setPostedDemands] = useState<Demand[]>([]);
  const [product, setProduct] = useState('');
  const [quantity, setQuantity] = useState('');
  const [unit, setUnit] = useState('');
  const [deadline, setDeadline] = useState('');
  const [budget, setBudget] = useState('');
  const [requirements, setRequirements] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');

  const loadDemands = async () => {
    try {
      const { request } = await import('../../../../lib/api');
      const response = await request('/api/buyer/demands');
      setPostedDemands((response.data || []).map((demand: any) => ({
        id: demand.id,
        product: demand.product_name,
        quantity: `${demand.quantity_needed} ${demand.unit}`,
        deadline: demand.deadline,
        matches: demand.demand_details_count || 0,
        status: demand.status,
      })));
    } catch (err: any) {
      setError(err.message || 'Hindi ma-load ang demand notices.');
    }
  };

  useEffect(() => {
    loadDemands();
  }, []);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setError('');

    if (!product || !quantity || !unit || !deadline) {
      setError('Kumpletuhin ang product, quantity, unit, at deadline fields.');
      return;
    }

    setIsSubmitting(true);
    try {
      const { request } = await import('../../../../lib/api');
      await request('/api/buyer/demands', {
        method: 'POST',
        body: {
          product_name: product,
          category: product,
          quantity_needed: Number(quantity),
          unit,
          max_price_per_unit: budget ? Number(budget) : null,
          description: requirements || null,
          deadline,
        },
      });
      setProduct('');
      setQuantity('');
      setUnit('');
      setDeadline('');
      setBudget('');
      setRequirements('');
      await loadDemands();
    } catch (err: any) {
      setError(err.message || 'Hindi ma-post ang demand notice.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="font-display text-2xl sm:text-3xl text-[#123C5C] tracking-tight">Mag-post ng Demand Notice</h1>
        <p className="text-[#45586B] mt-1">Gumawa ng demand postings at makatanggap ng matches mula sa producers</p>
      </div>

      <div className="grid lg:grid-cols-3 gap-6">
        {/* Post Form */}
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Bagong Demand Notice</CardTitle>
            <CardDescription>Punan ang detalye ng iyong institutional demand</CardDescription>
          </CardHeader>
          <CardContent>
            <form className="space-y-4" onSubmit={handleSubmit}>
            <div className="grid md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="product">Uri ng Produkto</Label>
                <Select value={product} onValueChange={setProduct}>
                  <SelectTrigger id="product">
                    <SelectValue placeholder="Pumili ng produkto" />
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
                <Label htmlFor="quantity">Kailangang Quantity</Label>
                <Input id="quantity" type="number" min="1" value={quantity} onChange={(event) => setQuantity(event.target.value)} placeholder="e.g., 2000" />
              </div>
            </div>

            <div className="grid md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="unit">Unit</Label>
                <Select value={unit} onValueChange={setUnit}>
                  <SelectTrigger id="unit">
                    <SelectValue placeholder="Pumili ng unit" />
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
                <Input id="deadline" type="date" value={deadline} onChange={(event) => setDeadline(event.target.value)} />
              </div>
            </div>

            <div>
              <Label htmlFor="budget">Budget Range (Optional)</Label>
              <Input id="budget" type="number" min="0" value={budget} onChange={(event) => setBudget(event.target.value)} placeholder="Pinakamataas na presyo bawat unit" />
            </div>

            <div>
              <Label htmlFor="requirements">Mga Special Requirement</Label>
              <Textarea 
                id="requirements" 
                placeholder="Quality standards, certifications, delivery preferences..."
                rows={3}
                value={requirements}
                onChange={(event) => setRequirements(event.target.value)}
              />
            </div>

            {error && <p className="text-sm text-red-600" role="alert">{error}</p>}

            <Button type="submit" disabled={isSubmitting} className="w-full rounded-full font-bold bg-[#22C55E] hover:bg-[#15803D]">
              <Plus className="w-4 h-4 mr-2" />
              {isSubmitting ? 'Nino-post...' : 'Mag-post ng Demand Notice'}
            </Button>
            </form>
          </CardContent>
        </Card>

        {/* Posted Demands */}
        <Card>
          <CardHeader>
            <CardTitle>Iyong Na-post na Demands</CardTitle>
            <CardDescription>Mga active demand posting</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {postedDemands.map((demand) => (
                <div key={demand.id} className="p-3 border border-[#E7E1D0] rounded-xl">
                  <div className="flex items-start justify-between mb-2">
                    <h4 className="font-bold text-[#123C5C]">{demand.product}</h4>
                    <Badge className="bg-[#22C55E]">{demand.status}</Badge>
                  </div>
                  <div className="text-sm text-[#45586B] space-y-1 mb-3">
                    <div>Quantity: {demand.quantity}</div>
                    <div className="flex items-center gap-1">
                      <Calendar className="w-3 h-3" />
                      <span>Due: {demand.deadline}</span>
                    </div>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium text-[#0F9488]">{demand.matches} matches</span>
                    <Button size="sm" variant="outline" onClick={() => setSelectedDemand(demand)}>Tingnan</Button>
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
            <DialogTitle>Detalye ng Demand</DialogTitle>
            <DialogDescription>Demand notice para sa {selectedDemand?.product}</DialogDescription>
          </DialogHeader>
          {selectedDemand && (
            <div className="space-y-4">
              <div className="space-y-3">
                <div className="flex justify-between">
                  <span className="text-[#45586B]">Product:</span>
                  <span className="font-bold text-[#123C5C]">{selectedDemand.product}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-[#45586B]">Quantity:</span>
                  <span className="font-bold text-[#123C5C]">{selectedDemand.quantity}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-[#45586B]">Deadline:</span>
                  <span className="font-bold text-[#123C5C]">{selectedDemand.deadline}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-[#45586B]">Status:</span>
                  <Badge className="bg-[#22C55E]">{selectedDemand.status}</Badge>
                </div>
                <div className="flex justify-between">
                  <span className="text-[#45586B]">Matches Found:</span>
                  <span className="font-bold text-[#0F9488]">{selectedDemand.matches} producers</span>
                </div>
              </div>
              <div className="pt-4 border-t border-[#E7E1D0]">
                <h4 className="font-bold text-[#123C5C] mb-3">Matched Producers</h4>
                <div className="space-y-2">
                  {/* Mock matched producers */}
                  <div className="p-3 bg-[#F5F1E5] rounded-xl">
                    <div className="flex justify-between items-center">
                      <div>
                        <p className="font-medium text-[#123C5C]">Juan Dela Cruz</p>
                        <p className="text-sm text-[#45586B]">Brgy. Centro • Rice Farmer</p>
                      </div>
                      <Button size="sm" variant="outline">Contact</Button>
                    </div>
                  </div>
                  <div className="p-3 bg-[#F5F1E5] rounded-xl">
                    <div className="flex justify-between items-center">
                      <div>
                        <p className="font-medium text-[#123C5C]">Maria Santos</p>
                        <p className="text-sm text-[#45586B]">Brgy. Macanaya • Farmer</p>
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
import { useState, FormEvent } from 'react';
import { User, Building2, Mail, Phone, MapPin } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../components/ui/card';
import { Badge } from '../../../components/ui/badge';
import { Button } from '../../../components/ui/button';
import { Input } from '../../../components/ui/input';
import { Label } from '../../../components/ui/label';

export default function Profile() {
  const [form, setForm] = useState({
    organizationName: 'Local Government Unit of Aparri',
    type: 'Local Government Unit',
    registrationDate: 'January 15, 2026',
    contactPerson: 'Maria Gonzales',
    role: 'Institutional Buyer Lead',
    email: 'contact@aparri.gov.ph',
    phone: '+63 78 123 4567',
    location: 'Aparri, Cagayan',
    focus: 'Food security programs, school feeding, institutional needs',
  });
  const [message, setMessage] = useState('');
  const [errors, setErrors] = useState<{ [key: string]: string }>({});

  const handleChange = (field: string, value: string) => {
    setForm((current) => ({ ...current, [field]: value }));
    setErrors((current) => ({ ...current, [field]: '' }));
  };

  const validate = () => {
    const nextErrors: { [key: string]: string } = {};
    if (!form.organizationName.trim()) nextErrors.organizationName = 'Organization name is required.';
    if (!form.contactPerson.trim()) nextErrors.contactPerson = 'Contact person is required.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) nextErrors.email = 'Enter a valid email address.';
    if (!/^\+?[0-9\s-]{7,20}$/.test(form.phone)) nextErrors.phone = 'Enter a valid phone number.';
    if (!form.location.trim()) nextErrors.location = 'Location is required.';
    if (!form.focus.trim()) nextErrors.focus = 'Buyer focus description is required.';
    setErrors(nextErrors);
    return Object.keys(nextErrors).length === 0;
  };

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!validate()) {
      setMessage('Ayusin ang mga naka-highlight na fields.');
      return;
    }
    setMessage('Matagumpay na na-update ang profile.');
  };

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-gray-900">Profile</h1>
        <p className="text-gray-600 mt-1">I-edit ang iyong institutional buyer profile at organization details.</p>
      </div>

      <div className="grid lg:grid-cols-3 gap-6">
        <Card>
          <CardContent className="p-6 text-center">
            <div className="w-24 h-24 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center mx-auto mb-4">
              <Building2 className="w-12 h-12 text-white" />
            </div>
            <h2 className="text-xl font-bold text-gray-900 mb-2">{form.organizationName}</h2>
            <Badge className="bg-blue-500 mb-4">Institutional Buyer</Badge>
            <div className="space-y-3 text-left pt-4 border-t">
              <div className="flex items-center gap-3 text-sm">
                <Mail className="w-4 h-4 text-gray-400" />
                <span>{form.email}</span>
              </div>
              <div className="flex items-center gap-3 text-sm">
                <Phone className="w-4 h-4 text-gray-400" />
                <span>{form.phone}</span>
              </div>
              <div className="flex items-center gap-3 text-sm">
                <MapPin className="w-4 h-4 text-gray-400" />
                <span>{form.location}</span>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Detalye ng Organization</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-6">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="organizationName">Pangalan ng Organization</Label>
                  <Input
                    id="organizationName"
                    value={form.organizationName}
                    onChange={(event) => handleChange('organizationName', event.target.value)}
                  />
                  {errors.organizationName && <p className="text-sm text-red-600 mt-1">{errors.organizationName}</p>}
                </div>
                <div>
                  <Label htmlFor="contactPerson">Contact Person</Label>
                  <Input
                    id="contactPerson"
                    value={form.contactPerson}
                    onChange={(event) => handleChange('contactPerson', event.target.value)}
                  />
                  {errors.contactPerson && <p className="text-sm text-red-600 mt-1">{errors.contactPerson}</p>}
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="email">Email Address</Label>
                  <Input
                    id="email"
                    type="email"
                    value={form.email}
                    onChange={(event) => handleChange('email', event.target.value)}
                  />
                  {errors.email && <p className="text-sm text-red-600 mt-1">{errors.email}</p>}
                </div>
                <div>
                  <Label htmlFor="phone">Phone Number</Label>
                  <Input
                    id="phone"
                    type="tel"
                    value={form.phone}
                    onChange={(event) => handleChange('phone', event.target.value)}
                  />
                  {errors.phone && <p className="text-sm text-red-600 mt-1">{errors.phone}</p>}
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="location">Lokasyon</Label>
                  <Input
                    id="location"
                    value={form.location}
                    onChange={(event) => handleChange('location', event.target.value)}
                  />
                  {errors.location && <p className="text-sm text-red-600 mt-1">{errors.location}</p>}
                </div>
                <div>
                  <Label htmlFor="role">Role / Posisyon</Label>
                  <Input
                    id="role"
                    value={form.role}
                    onChange={(event) => handleChange('role', event.target.value)}
                  />
                </div>
              </div>

              <div>
                  <Label htmlFor="focus">Institutional Focus</Label>
                <Input
                  id="focus"
                  value={form.focus}
                  onChange={(event) => handleChange('focus', event.target.value)}
                />
                {errors.focus && <p className="text-sm text-red-600 mt-1">{errors.focus}</p>}
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <Label>Type</Label>
                  <Input value={form.type} disabled />
                </div>
                <div>
                  <Label>Petsa ng Registration</Label>
                  <Input value={form.registrationDate} disabled />
                </div>
              </div>

              <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pt-4 border-t">
                <p className="text-sm text-green-700">{message}</p>
                <Button type="submit" className="bg-blue-600 hover:bg-blue-700">
                  I-save ang Changes
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

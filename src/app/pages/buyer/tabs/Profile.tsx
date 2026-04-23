import { User, Building2, Mail, Phone, MapPin } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../components/ui/card';
import { Badge } from '../../../components/ui/badge';

export default function Profile() {
  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-gray-900">Profile</h1>
        <p className="text-gray-600 mt-1">Your institutional buyer information</p>
      </div>

      <div className="grid lg:grid-cols-3 gap-6">
        <Card>
          <CardContent className="p-6 text-center">
            <div className="w-24 h-24 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center mx-auto mb-4">
              <Building2 className="w-12 h-12 text-white" />
            </div>
            <h2 className="text-xl font-bold text-gray-900 mb-2">LGU Aparri</h2>
            <Badge className="bg-blue-500 mb-4">Institutional Buyer</Badge>
            <div className="space-y-3 text-left pt-4 border-t">
              <div className="flex items-center gap-3 text-sm">
                <Mail className="w-4 h-4 text-gray-400" />
                <span>procurement@aparri.gov.ph</span>
              </div>
              <div className="flex items-center gap-3 text-sm">
                <Phone className="w-4 h-4 text-gray-400" />
                <span>+63 78 123 4567</span>
              </div>
              <div className="flex items-center gap-3 text-sm">
                <MapPin className="w-4 h-4 text-gray-400" />
                <span>Aparri, Cagayan</span>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Organization Details</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div>
              <div className="text-sm text-gray-600 mb-1">Organization Name</div>
              <div className="font-medium">Local Government Unit of Aparri</div>
            </div>
            <div>
              <div className="text-sm text-gray-600 mb-1">Type</div>
              <div className="font-medium">Local Government Unit</div>
            </div>
            <div>
              <div className="text-sm text-gray-600 mb-1">Registration Date</div>
              <div className="font-medium">January 15, 2026</div>
            </div>
            <div>
              <div className="text-sm text-gray-600 mb-1">Contact Person</div>
              <div className="font-medium">Maria Gonzales - Procurement Officer</div>
            </div>
            <div>
              <div className="text-sm text-gray-600 mb-1">Procurement Focus</div>
              <div className="font-medium">Food security programs, school feeding, institutional needs</div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

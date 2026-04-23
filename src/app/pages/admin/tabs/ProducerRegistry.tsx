import { Shield, CheckCircle2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../components/ui/card';

export default function ProducerRegistry() {
  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-gray-900">Producer Validation System</h1>
        <p className="text-gray-600 mt-1">Automated verification through external databases</p>
      </div>

      <Card className="border-2 border-blue-200 bg-blue-50">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-blue-900">
            <Shield className="w-6 h-6" />
            Automated Producer Verification
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <p className="text-blue-800">
            The producer registry has been replaced with an automated validation system. 
            When users register as producers, the system automatically validates them against 
            external government databases and tags verified farmers and fisherfolk accordingly.
          </p>
          <div className="grid md:grid-cols-2 gap-4 mt-6">
            <div className="p-4 bg-white rounded-lg border">
              <div className="flex items-center gap-2 mb-2">
                <CheckCircle2 className="w-5 h-5 text-green-600" />
                <span className="font-bold text-green-900">Verified Producers</span>
              </div>
              <p className="text-sm text-gray-600">
                Users found in DA/BFAR databases are automatically tagged as verified producers.
              </p>
            </div>
            <div className="p-4 bg-white rounded-lg border">
              <div className="flex items-center gap-2 mb-2">
                <CheckCircle2 className="w-5 h-5 text-blue-600" />
                <span className="font-bold text-blue-900">Real-time Validation</span>
              </div>
              <p className="text-sm text-gray-600">
                Validation occurs during registration and can be re-verified periodically.
              </p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

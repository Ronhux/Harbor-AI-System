import { Shield, CheckCircle2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../components/ui/card';

export default function ProducerRegistry() {
  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="font-display text-2xl sm:text-3xl text-[#123C5C] tracking-tight">Producer Validation System</h1>
        <p className="text-[#45586B] mt-1">Automated verification through external databases</p>
      </div>

      <Card className="border-2 border-[#0F9488]/30 bg-[#0F9488]/5">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-[#123C5C]">
            <Shield className="w-6 h-6 text-[#0F9488]" />
            Automated Producer Verification
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <p className="text-[#0B4842]">
            The producer registry has been replaced with an automated validation system. 
            When users register as producers, the system automatically validates them against 
            external government databases and tags verified farmers and fisherfolk accordingly.
          </p>
          <div className="grid md:grid-cols-2 gap-4 mt-6">
            <div className="p-4 bg-white rounded-xl border border-[#E7E1D0]">
              <div className="flex items-center gap-2 mb-2">
                <CheckCircle2 className="w-5 h-5 text-[#22C55E]" />
                <span className="font-bold text-[#15803D]">Verified Producers</span>
              </div>
              <p className="text-sm text-[#45586B]">
                Users found in DA/BFAR databases are automatically tagged as verified producers.
              </p>
            </div>
            <div className="p-4 bg-white rounded-xl border border-[#E7E1D0]">
              <div className="flex items-center gap-2 mb-2">
                <CheckCircle2 className="w-5 h-5 text-[#0E7490]" />
                <span className="font-bold text-[#0E7490]">Real-time Validation</span>
              </div>
              <p className="text-sm text-[#45586B]">
                Validation occurs during registration and can be re-verified periodically.
              </p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
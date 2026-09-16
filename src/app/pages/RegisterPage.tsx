import { useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { UserPlus, Sprout, Fish } from 'lucide-react';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../components/ui/select';
import { Checkbox } from '../components/ui/checkbox';

export default function RegisterPage() {
  const navigate = useNavigate();
  const [formData, setFormData] = useState({
    first_name: '',
    middle_name: '',
    last_name: '',
    suffix: '',
    contact_number: '',
    email: '',
    password: '',
    confirmPassword: '',
    producer_type: '',
    rsbsa_number: '',
    agreeToTerms: false,
  });
  const [passwordError, setPasswordError] = useState('');
  const [error, setError] = useState<string>('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    setError('');

    if (formData.password !== formData.confirmPassword) {
      setPasswordError('Passwords do not match');
      return;
    } else {
      setPasswordError('');
    }

    if (!formData.agreeToTerms) {
      setError('Please accept the terms and conditions');
      return;
    }

    try {
      // Get CSRF token from meta tag
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

      const response = await fetch('/api/register', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
          first_name: formData.first_name,
          middle_name: formData.middle_name,
          last_name: formData.last_name,
          suffix: formData.suffix,
          contact_number: formData.contact_number,
          email: formData.email,
          password: formData.password,
          user_type: 'producer',
          producer_type: formData.producer_type,
          rsbsa_number: formData.rsbsa_number,
        }),
      });

      const result = await response.json();

      if (response.ok) {
        // Store user data and token
        localStorage.setItem('userEmail', result.user.email);
        localStorage.setItem('userRole', result.user.user_type === 'Farmer' ? 'producer' : result.user.user_type.toLowerCase());
        localStorage.setItem('userName', `${result.user.first_name} ${result.user.last_name}`);
        localStorage.setItem('userId', result.user.user_id.toString());
        
        // If there's a token (verified user), store it for authenticated requests
        if (result.token) {
          localStorage.setItem('authToken', result.token);
          const message = `Registration successful! Your account is ${result.verification_status}. Redirecting to dashboard...`;
          alert(message);
          navigate('/producer');
        } else {
          // For pending verification, redirect to login
          const message = `Registration successful! Your account is ${result.verification_status}. Please login to continue.`;
          alert(message);
          navigate('/login');
        }
      } else {
        // Extract error messages from response
        if (result.errors) {
          const errorMessages = Object.entries(result.errors)
            .map(([field, messages]: [string, any]) => {
              const fieldName = field.replace(/_/g, ' ').charAt(0).toUpperCase() + field.slice(1);
              return `${fieldName}: ${Array.isArray(messages) ? messages.join(', ') : messages}`;
            })
            .join('\n');
          setError(errorMessages);
        } else if (result.message) {
          setError(result.message);
        } else {
          setError('Registration failed. Please try again.');
        }
      }
    } catch (error) {
      console.error('Registration error:', error);
      setError('Registration failed. Please check your connection and try again.');
    }
  };

  return (
    <div className="min-h-screen bg-[#F5F1E5] font-body relative overflow-hidden px-4 py-8 sm:py-12">
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Anton&family=Baloo+2:wght@400;500;600;700;800&display=swap');
        .font-display { font-family: 'Anton', ui-sans-serif, sans-serif; }
        .font-body { font-family: 'Baloo 2', ui-rounded, system-ui, sans-serif; }
      `}</style>

      <div className="pointer-events-none absolute -top-24 -left-24 h-72 w-72 rounded-full bg-[#22C55E]/20 blur-[100px]" />
      <div className="pointer-events-none absolute -right-16 -bottom-16 h-80 w-80 rounded-full bg-[#0F9488]/20 blur-[100px]" />
      <div className="absolute bottom-0 left-0 z-0 w-full leading-none" aria-hidden="true">
        <svg viewBox="0 0 1440 180" preserveAspectRatio="none" className="block h-20 w-full sm:h-28 md:h-32">
          <defs>
            <linearGradient id="registerHorizonGrad" x1="0" y1="0" x2="1" y2="0">
              <stop offset="0%" stopColor="#22C55E" />
              <stop offset="55%" stopColor="#22C55E" />
              <stop offset="72%" stopColor="#0F9488" />
              <stop offset="100%" stopColor="#0EA5A0" />
            </linearGradient>
          </defs>
          <path d="M0,95 C180,50 360,95 540,68 C720,40 900,85 1080,58 C1260,30 1350,65 1440,50 L1440,180 L0,180 Z" fill="#BFEFE2" opacity="0.5" />
          <path d="M0,70 L70,108 L140,76 L210,120 L280,86 L350,128 L420,94 L490,132 L560,100 L630,136 L700,112 Q760,146 820,128 T940,136 T1060,108 T1180,130 T1300,98 L1440,116 L1440,180 L0,180 Z" fill="url(#registerHorizonGrad)" />
        </svg>
      </div>

      <div className="relative z-10 mx-auto max-w-3xl">
        <div className="mb-5 flex items-center justify-between gap-4 px-1">
          <Link to="/" className="flex items-center gap-3 text-[#123C5C]">
            <img src="/logo.png" alt="HarborAI Logo" className="h-10 w-10 object-contain sm:h-12 sm:w-12" />
            <span className="font-display text-xl tracking-tight sm:text-2xl">HarborAI</span>
          </Link>
          <Link to="/login" className="text-sm font-bold text-[#0F9488] hover:text-[#0B4842] hover:underline">Login</Link>
        </div>

        <Card className="overflow-hidden rounded-2xl border-2 border-[#E7E1D0] bg-white/95 shadow-xl backdrop-blur-sm">
          <div className="h-2 bg-gradient-to-r from-[#22C55E] via-[#0F9488] to-[#22D3EE]" />
          <CardHeader className="px-5 pt-7 text-center sm:px-10 sm:pt-9">
            <div className="flex items-center justify-center gap-3 mb-4">
              <img src="/logo.png" alt="HarborAI Logo" className="w-14 h-14 object-contain" />
              <span className="font-display text-3xl tracking-tight text-[#123C5C]">HarborAI</span>
            </div>
            <CardTitle className="font-display text-2xl tracking-tight text-[#123C5C]">Register as Producer</CardTitle>
            <CardDescription className="text-[#45586B]">
              Join HarborAI to access institutional markets and AI-powered insights
            </CardDescription>
          </CardHeader>
          <CardContent className="px-5 pb-8 sm:px-10">
            <form onSubmit={handleSubmit} className="space-y-5">
              {error && (
                <div className="p-3 bg-red-50 border border-red-200 rounded-lg">
                  <p className="text-red-800 text-sm whitespace-pre-line">{error}</p>
                </div>
              )}

              <div className="grid md:grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="first_name">First Name</Label>
                  <Input
                    id="first_name"
                    type="text"
                    placeholder="Juan"
                    value={formData.first_name}
                    onChange={(e) => setFormData({ ...formData, first_name: e.target.value })}
                    required
                    className="rounded-full"
                  />
                </div>
                <div>
                  <Label htmlFor="middle_name">Middle Name <span className="text-gray-400">(optional)</span></Label>
                  <Input
                    id="middle_name"
                    type="text"
                    placeholder="Santos"
                    value={formData.middle_name}
                    onChange={(e) => setFormData({ ...formData, middle_name: e.target.value })}
                    className="rounded-full"
                  />
                </div>
              </div>
              <div className="grid md:grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="last_name">Last Name</Label>
                  <Input
                    id="last_name"
                    type="text"
                    placeholder="Dela Cruz"
                    value={formData.last_name}
                    onChange={(e) => setFormData({ ...formData, last_name: e.target.value })}
                    required
                    className="rounded-full"
                  />
                </div>
                <div>
                  <Label htmlFor="suffix">Suffix <span className="text-gray-400">(optional)</span></Label>
                  <Input
                    id="suffix"
                    type="text"
                    placeholder="Jr., Sr., III, etc."
                    value={formData.suffix}
                    onChange={(e) => setFormData({ ...formData, suffix: e.target.value })}
                    className="rounded-full"
                  />
                </div>
              </div>
              <div>
                <Label htmlFor="contact_number">Contact Number</Label>
                <Input
                  id="contact_number"
                  type="tel"
                  placeholder="09XXXXXXXXX"
                  value={formData.contact_number}
                  onChange={(e) => setFormData({ ...formData, contact_number: e.target.value })}
                  required
                  className="rounded-full"
                />
              </div>

              <div>
                <Label htmlFor="email">Email Address</Label>
                <Input
                  id="email"
                  type="email"
                  placeholder="your.email@example.com"
                  value={formData.email}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                  required
                  className="rounded-full"
                />
              </div>


              <div className="grid md:grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="password">Password</Label>
                  <Input
                    id="password"
                    type="password"
                    placeholder="Create password"
                    value={formData.password}
                    onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                    required
                  />
                </div>
                <div>
                  <Label htmlFor="confirmPassword">Confirm Password</Label>
                  <Input
                    id="confirmPassword"
                    type="password"
                    placeholder="Re-enter password"
                    value={formData.confirmPassword}
                    onChange={(e) => setFormData({ ...formData, confirmPassword: e.target.value })}
                    required
                  />
                </div>
              </div>
              {passwordError && (
                <div className="text-red-600 text-sm font-medium mb-2 text-center">{passwordError}</div>
              )}

              <div>
                <Label htmlFor="producer_type">Producer Type</Label>
                <Select 
                  value={formData.producer_type} 
                  onValueChange={(value) => setFormData({ ...formData, producer_type: value })}
                  required
                  className="rounded-full"
                >
                    <SelectTrigger id="producer_type" className="rounded-full">
                    <SelectValue placeholder="Select producer type" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="Farmer">Farmer</SelectItem>
                    <SelectItem value="Fisherfolk">Fisherfolk</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              {formData.producer_type && (
                <div>
                  <Label htmlFor="rsbsa_number">RSBSA Number</Label>
                  <Input
                    id="rsbsa_number"
                    type="text"
                    placeholder="Enter your RSBSA number for verification"
                    value={formData.rsbsa_number}
                    onChange={(e) => setFormData({ ...formData, rsbsa_number: e.target.value })}
                    required
                    className="rounded-full"
                  />
                  <p className="mt-1 text-sm text-[#45586B]">
                    Provide your Registry System for Basic Sectors in Agriculture number for automatic verification.
                  </p>
                </div>
              )}

              <div className="rounded-xl border border-[#0F9488]/20 bg-[#0F9488]/5 p-4">
                <label htmlFor="terms" className="flex cursor-pointer items-center gap-3 text-sm leading-relaxed text-[#45586B]">
                  <Checkbox
                    id="terms"
                    checked={formData.agreeToTerms}
                    onCheckedChange={(checked) => 
                      setFormData({ ...formData, agreeToTerms: checked as boolean })
                    }
                  />
                  <span>
                    I agree to the{' '}
                    <a href="#" className="font-semibold text-[#0F9488] hover:underline">Terms & Conditions</a>
                      {' '}and{' '}
                      <Link to="/privacy" className="font-semibold text-[#0F9488] hover:underline">Privacy Policy</Link>.
                    I understand my information will be verified by DA/LGU personnel.
                  </span>
                </label>
              </div>

              <Button type="submit" className="w-full rounded-full bg-[#22C55E] font-bold text-white hover:bg-[#15803D]">
                <UserPlus className="w-4 h-4 mr-2" />
                Create Producer Account
              </Button>
            </form>

            <div className="mt-6 text-center text-sm text-[#45586B]">
              <p>
                Already have an account?{' '}
                <Link to="/login" className="font-bold text-[#0F9488] hover:text-[#0B4842] hover:underline">
                  Login here
                </Link>
              </p>
            </div>

            <div className="mt-6 border-t border-[#E7E1D0] pt-6">
              <Link to="/">
                <Button variant="ghost" className="w-full rounded-full font-semibold text-[#123C5C] hover:bg-[#123C5C]/5">
                  Back to Home
                </Button>
              </Link>
            </div>

            <div className="mt-6 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] p-4">
              <p className="text-sm text-[#92400E]">
                <strong>Note:</strong> Your account will be reviewed and verified by DA/LGU personnel 
                before gaining full access to the platform.
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

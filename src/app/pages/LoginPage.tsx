import { useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Eye, EyeOff, LogIn } from 'lucide-react';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../components/ui/card';
export default function LoginPage() {
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    
    if (!email || !password) {
      setError('Please fill in all fields');
      setLoading(false);
      return;
    }

    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      
      const response = await fetch('/api/login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
          email,
          password,
        }),
      });

      const result = await response.json();

      if (response.ok) {
        const userType = result.user?.user_type;
        const normalizedRole = userType === 'Farmer' ? 'producer' : userType === 'Buyer' ? 'buyer' : userType === 'Admin' ? 'admin' : 'buyer';

        localStorage.setItem('userRole', normalizedRole);
        localStorage.setItem('userEmail', result.user.email);
        localStorage.setItem('userName', `${result.user.first_name} ${result.user.last_name}`);
        localStorage.setItem('userId', result.user.user_id.toString());
        if (result.token) {
          localStorage.setItem('authToken', result.token);
        }
        
        if (result.user.verification_status) {
          localStorage.setItem('verificationStatus', result.user.verification_status);
        }
        
        if (normalizedRole === 'admin') {
          navigate('/admin');
        } else if (normalizedRole === 'producer') {
          navigate('/producer');
        } else if (normalizedRole === 'buyer') {
          navigate('/buyer');
        }
      } else {
        setError(result.message || 'Login failed. Please check your credentials.');
      }
    } catch (error) {
      console.error('Login error:', error);
      setError('Login failed. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-[#F5F1E5] font-body relative overflow-hidden flex items-center justify-center p-4">
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Anton&family=Baloo+2:wght@400;500;600;700;800&display=swap');
        .font-display { font-family: 'Anton', ui-sans-serif, sans-serif; }
        .font-body { font-family: 'Baloo 2', ui-rounded, system-ui, sans-serif; }
      `}</style>

      {/* Ambient color glow */}
      <div className="pointer-events-none absolute -top-24 -left-24 w-72 h-72 rounded-full bg-[#22C55E]/20 blur-[100px]"></div>
      <div className="pointer-events-none absolute -bottom-16 -right-16 w-80 h-80 rounded-full bg-[#0F9488]/20 blur-[100px]"></div>

      {/* Illustrated horizon, echoes the landing page hero */}
      <div className="absolute bottom-0 left-0 w-full leading-none z-0" aria-hidden="true">
        <svg viewBox="0 0 1440 180" preserveAspectRatio="none" className="w-full h-20 sm:h-28 md:h-32 block">
          <defs>
            <linearGradient id="loginHorizonGrad" x1="0" y1="0" x2="1" y2="0">
              <stop offset="0%" stopColor="#22C55E" />
              <stop offset="55%" stopColor="#22C55E" />
              <stop offset="72%" stopColor="#0F9488" />
              <stop offset="100%" stopColor="#0EA5A0" />
            </linearGradient>
          </defs>
          <path d="M0,95 C180,50 360,95 540,68 C720,40 900,85 1080,58 C1260,30 1350,65 1440,50 L1440,180 L0,180 Z" fill="#BFEFE2" opacity="0.5" />
          <path d="M0,70 L70,108 L140,76 L210,120 L280,86 L350,128 L420,94 L490,132 L560,100 L630,136 L700,112 Q760,146 820,128 T940,136 T1060,108 T1180,130 T1300,98 L1440,116 L1440,180 L0,180 Z" fill="url(#loginHorizonGrad)" />
        </svg>
      </div>

      <Card className="w-full max-w-md relative z-10 rounded-2xl border-0 shadow-xl overflow-hidden">
        <div className="h-2 bg-gradient-to-r from-[#22C55E] via-[#0F9488] to-[#22D3EE]"></div>
        <CardHeader className="text-center pt-8">
          <div className="flex items-center justify-center gap-3 mb-4">
            <img src="/logo.png" alt="HarborAI Logo" className="w-14 h-14 object-contain" />
            <span className="font-display text-3xl text-[#123C5C] tracking-tight">HarborAI</span>
          </div>
          <CardTitle className="text-2xl text-[#123C5C]">Welcome Back</CardTitle>
          <CardDescription className="text-[#45586B]">
            Login to access your HarborAI dashboard
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleLogin} className="space-y-4">
            {error && (
              <div className="p-3 bg-red-50 border border-red-200 rounded-lg">
                <p className="text-red-800 text-sm">{error}</p>
              </div>
            )}
            <div>
              <Label htmlFor="email">Email Address</Label>
              <Input
                id="email"
                type="email"
                placeholder="your.email@example.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                className="rounded-full"
              />
            </div>

            <div>
              <Label htmlFor="password">Password</Label>
              <div className="relative">
                <Input
                  id="password"
                  type={showPassword ? 'text' : 'password'}
                  placeholder="Enter your password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                  className="pr-10 rounded-full"
                />
                <button
                  type="button"
                  aria-label={showPassword ? 'Hide password' : 'Show password'}
                  onClick={() => setShowPassword((prev) => !prev)}
                  className="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-gray-700"
                >
                  {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
            </div>

            <Button type="submit" className="w-full rounded-full font-bold bg-[#22C55E] hover:bg-[#15803D]" disabled={loading}>
              <LogIn className="w-4 h-4 mr-2" />
              {loading ? 'Logging in...' : 'Login'}
            </Button>
          </form>

          <div className="mt-6 text-center text-sm text-[#45586B]">
            <p>
              Producer without an account?{' '}
              <Link to="/register" className="text-[#0F9488] hover:text-[#0B4842] hover:underline font-bold">
                Register here
              </Link>
            </p>
            <p className="mt-2 text-xs text-[#45586B]/70">
              Note: Institutional buyers and administrators receive login credentials from DA/LGU
            </p>
          </div>

          <div className="mt-6 pt-6 border-t border-[#E7E1D0]">
            <Link to="/">
              <Button variant="ghost" className="w-full rounded-full font-semibold text-[#123C5C] hover:bg-[#123C5C]/5">
                Back to Home
              </Button>
            </Link>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
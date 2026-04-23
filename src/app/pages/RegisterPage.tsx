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
    <div className="min-h-screen bg-gradient-to-br from-green-50 via-blue-50 to-green-50 py-12 px-4">
      <div className="container mx-auto max-w-2xl">
        <Card>
          <CardHeader className="text-center">
            <div className="flex items-center justify-center gap-3 mb-4">
              <img src="/logo.png" alt="HarborAI Logo" className="w-14 h-14 object-contain" />
              <span className="text-3xl font-bold text-green-900">HarborAI</span>
            </div>
            <CardTitle className="text-2xl">Register as Producer</CardTitle>
            <CardDescription>
              Join HarborAI to access institutional markets and AI-powered insights
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">
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
                >
                  <SelectTrigger id="producer_type">
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
                  />
                  <p className="text-sm text-gray-600 mt-1">
                    Provide your Registry System for Basic Sectors in Agriculture number for automatic verification.
                  </p>
                </div>
              )}

              <div className="p-4 bg-blue-50 rounded-lg">
                <label htmlFor="terms" className="flex items-center gap-3 text-sm text-gray-700 leading-relaxed cursor-pointer">
                  <Checkbox
                    id="terms"
                    checked={formData.agreeToTerms}
                    onCheckedChange={(checked) => 
                      setFormData({ ...formData, agreeToTerms: checked as boolean })
                    }
                  />
                  <span>
                    I agree to the{' '}
                    <a href="#" className="text-blue-600 hover:underline">Terms & Conditions</a>
                    {' '}and{' '}
                    <a href="#" className="text-blue-600 hover:underline">Privacy Policy</a>.
                    I understand my information will be verified by DA/LGU personnel.
                  </span>
                </label>
              </div>

              <Button type="submit" className="w-full bg-green-600 hover:bg-green-700">
                <UserPlus className="w-4 h-4 mr-2" />
                Create Producer Account
              </Button>
            </form>

            <div className="mt-6 text-center text-sm text-gray-600">
              <p>
                Already have an account?{' '}
                <Link to="/login" className="text-green-600 hover:underline font-medium">
                  Login here
                </Link>
              </p>
            </div>

            <div className="mt-6 pt-6 border-t">
              <Link to="/">
                <Button variant="ghost" className="w-full">
                  Back to Home
                </Button>
              </Link>
            </div>

            <div className="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
              <p className="text-sm text-yellow-800">
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

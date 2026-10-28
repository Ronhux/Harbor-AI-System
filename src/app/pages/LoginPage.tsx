import { clearAuthStorage } from '../../lib/authStorage';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '../components/ui/dialog';
import { useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Eye, EyeOff, LogIn } from 'lucide-react';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../components/ui/card';
import LanguageSwitcher from '../i18n/LanguageSwitcher';
import { useLanguage } from '../i18n/useLanguage';
export default function LoginPage() {
  const navigate = useNavigate();
  const { t, lang } = useLanguage();
  const tr=(en:string,fil:string)=>lang==='fil'?fil:en;
  const [registrationResult,setRegistrationResult]=useState<{status:'Pending'|'Rejected';reason:string|null}|null>(null);
  const [identifier, setIdentifier] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  /**
   * Maps a failed sign-in response to a message that matches what actually
   * happened. A server or network problem is never reported as "wrong
   * password", and internal server detail is never echoed back to the browser.
   */
  const describeFailure = (response: Response, result: any): string => {
    const serverMessage = typeof result?.message === 'string' ? result.message : '';
    const firstValidationMessage = () => {
      const errors = result?.errors;
      if (errors && typeof errors === 'object') {
        for (const value of Object.values(errors as Record<string, unknown>)) {
          if (Array.isArray(value) && typeof value[0] === 'string') return value[0];
        }
      }
      return '';
    };

    if (response.status === 401) return t('login.invalidCredentials');
    if (response.status === 403) return serverMessage || t('login.accountRestricted');
    if (response.status === 419) return t('login.connectionError');
    if (response.status === 422) return firstValidationMessage() || serverMessage || t('login.fillAllFields');
    if (response.status === 429) return t('login.tooManyAttempts');
    // Never surface a 5xx body: it can contain SQL or internal detail.
    if (response.status >= 500) return t('login.serverError');
    return serverMessage || t('login.loginFailed');
  };

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    if(loading)return;
    setRegistrationResult(null);
    setError('');
    setLoading(true);

    if (!identifier.trim() || !password) {
      setError(t('login.fillAllFields'));
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
        // A single identifier field accepts either your mobile number or your
        // email address; the API decides which account to match.
        body: JSON.stringify({
          identifier: identifier.trim(),
          password,
        }),
      });

      const result = await response.json();

      if (response.ok) {
        const userType = result.user?.user_type;
        const normalizedRole = ['Farmer', 'Fisherfolk', 'Producer'].includes(userType) ? 'producer' : userType === 'Buyer' ? 'buyer' : userType === 'Admin' ? 'admin' : userType === 'Super Admin' ? 'superadmin' : userType === 'KADIWA Manager' ? 'kadiwa' : 'buyer';

        localStorage.setItem('userRole', normalizedRole);
        localStorage.setItem('userName', `${result.user.first_name ?? ''} ${result.user.last_name ?? ''}`.trim());
        localStorage.setItem('userId', result.user.user_id.toString());
        if (result.token) {
          localStorage.setItem('authToken', result.token);
        }

        if (result.user.verification_status) {
          localStorage.setItem('verificationStatus', result.user.verification_status);
        }

        localStorage.setItem(
          'userEmail',
          result.user.email ?? identifier.trim(),
        );

        if (normalizedRole === 'kadiwa') {
          navigate('/kadiwa');
        } else if (normalizedRole === 'admin') {
          navigate('/admin');
        } else if (normalizedRole === 'superadmin') {
          navigate('/superadmin');
        } else if (normalizedRole === 'producer') {
          navigate('/producer');
        } else if (normalizedRole === 'buyer') {
          navigate('/buyer');
        }
      } else if(response.status===403 && ['Pending','Rejected'].includes(result.registration_status)) {
        clearAuthStorage();
        setPassword('');
        setRegistrationResult({status:result.registration_status,reason:typeof result.applicant_reason==='string'?result.applicant_reason:null});
      } else {
        setError(describeFailure(response, result));
      }
    } catch (error) {
      console.error('Login request failed:', error);
      setError(t('login.connectionError'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-background font-body relative overflow-hidden flex items-center justify-center p-4">
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Anton&family=Baloo+2:wght@400;500;600;700;800&display=swap');
        .font-display { font-family: 'Anton', ui-sans-serif, sans-serif; }
        .font-body { font-family: 'Baloo 2', ui-rounded, system-ui, sans-serif; }
      `}</style>

      <Dialog open={registrationResult!==null} onOpenChange={open=>{if(!open)setRegistrationResult(null)}}><DialogContent className="bg-card text-foreground"><DialogHeader><DialogTitle>{registrationResult?.status==='Rejected'?tr('Registration Not Approved','Hindi Naaprubahan ang Rehistrasyon'):tr('Pending Review','Naghihintay ng Pagsusuri')}</DialogTitle><DialogDescription>{registrationResult?.status==='Rejected'?tr('Your Producer registration was not approved following administrative review.','Hindi naaprubahan ang iyong rehistrasyon bilang Producer matapos ang pagsusuri ng administrator.'):tr('Your registration has been submitted and is awaiting administrative approval.','Naisumite ang iyong rehistrasyon at naghihintay ng pag-apruba ng administrator.')}</DialogDescription></DialogHeader>{registrationResult?.reason&&<p className="whitespace-pre-wrap break-words text-sm"><strong>{tr('Reason:','Dahilan:')}</strong> {registrationResult.reason}</p>}<p className="text-sm text-muted-foreground">{tr('Please contact the Municipal Agriculture Office for clarification.','Mangyaring makipag-ugnayan sa Municipal Agriculture Office para sa paglilinaw.')}</p><DialogFooter><Button type="button" onClick={()=>setRegistrationResult(null)}>{tr('Return to Login','Bumalik sa Pag-sign in')}</Button></DialogFooter></DialogContent></Dialog>
      {/* Ambient color glow */}
      <div className="pointer-events-none absolute -top-24 -left-24 w-72 h-72 rounded-full bg-[#22C55E]/20 blur-[100px]"></div>

      <Card className="w-full max-w-md relative z-10 rounded-2xl border-0 shadow-xl overflow-hidden">
        <div className="h-2 bg-gradient-to-r from-[#22C55E] via-[#0F9488] to-[#22D3EE]"></div>
        <CardHeader className="text-center pt-8">
          <div className="mb-4 flex items-center justify-between gap-3">
            <div className="flex min-w-0 items-center gap-3">
              <img src="/logo.png" alt="HarborAI Logo" className="h-14 w-14 shrink-0 object-contain" />
              <span className="truncate font-display text-3xl text-foreground tracking-tight">HarborAI</span>
            </div>
            <div className="shrink-0">
              <LanguageSwitcher />
            </div>
          </div>
          <CardTitle className="text-2xl text-foreground">{t('login.welcomeBack')}</CardTitle>
          <CardDescription className="text-muted-foreground">
            {t('login.loginToAccess')}
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
              <Label htmlFor="identifier">{t('login.mobileOrEmail')}</Label>
              <Input
                id="identifier"
                name="identifier"
                type="text"
                inputMode="text"
                autoComplete="username"
                placeholder="09171234567 or your.email@example.com"
                aria-describedby="identifier-help"
                value={identifier}
                onChange={(e) => setIdentifier(e.target.value)}
                required
                className="rounded-full"
              />
              <p id="identifier-help" className="mt-1 text-xs text-muted-foreground">
                {t('login.identifierHelp')}
              </p>
            </div>

            <div>
              <Label htmlFor="password">{t('login.password')}</Label>
              <div className="relative">
                <Input
                  id="password"
                  type={showPassword ? 'text' : 'password'}
                  placeholder={t('login.enterPassword')}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                  className="pr-10 rounded-full"
                />
                <button
                  type="button"
                  aria-label={showPassword ? t('login.hidePassword') : t('login.showPassword')}
                  onClick={() => setShowPassword((prev) => !prev)}
                  className="absolute inset-y-0 right-3 flex items-center text-muted-foreground hover:text-muted-foreground"
                >
                  {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
            </div>

            <Button type="submit" className="w-full rounded-full font-bold bg-[#22C55E] hover:bg-[#15803D]" disabled={loading}>
              <LogIn className="w-4 h-4 mr-2" />
              {loading ? t('login.loggingIn') : t('login.login')}
            </Button>
          </form>

          <div className="mt-6 text-center text-sm text-muted-foreground">
            <p>
              {t('login.producerNoAccount')}{' '}
              <Link to="/register" className="text-[#0F9488] hover:text-[#0B4842] hover:underline font-bold">
                {t('login.registerHere')}
              </Link>
            </p>
            <p className="mt-2 text-xs text-muted-foreground/70">
              {t('login.institutionalNote')}
            </p>
          </div>

          <div className="mt-6 pt-6 border-t border-border">
            <Link to="/">
              <Button variant="ghost" className="w-full rounded-full font-semibold text-foreground hover:bg-[#123C5C]/5">
                {t('login.backToHome')}
              </Button>
            </Link>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
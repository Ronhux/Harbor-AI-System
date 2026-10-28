import ThemeProvider from './theme/ThemeProvider';
import { Suspense } from 'react';
import LanguageProvider from './i18n/LanguageProvider';
import { RouterProvider } from 'react-router-dom';
import { router } from './routes';
import { Toaster } from './components/ui/sonner';
export default function App() {
  return <ThemeProvider><LanguageProvider><Suspense fallback={<main role="status" className="min-h-screen bg-background p-6 text-foreground">Loading…</main>}><RouterProvider router={router} /></Suspense><Toaster richColors position="top-center" /></LanguageProvider></ThemeProvider>;
}

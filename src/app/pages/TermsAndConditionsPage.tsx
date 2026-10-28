import { Link } from 'react-router-dom';
import { ArrowLeft, FileText } from 'lucide-react';
import { Button } from '../components/ui/button';
import { TERMS_VERSION, termsSections } from '../data/termsSections';

export default function TermsAndConditionsPage() {
  return (
    <div className="min-h-screen bg-background font-body text-foreground">
      <style>{`@import url('https://fonts.googleapis.com/css2?family=Anton&family=Baloo+2:wght@400;500;600;700;800&display=swap'); .font-display{font-family:'Anton',ui-sans-serif,sans-serif}.font-body{font-family:'Baloo 2',ui-rounded,system-ui,sans-serif}`}</style>

      <header className="sticky top-0 z-20 border-b border-border bg-background/95 shadow-sm backdrop-blur-sm">
        <div className="container mx-auto flex min-h-16 items-center justify-between gap-4 px-4 py-3">
          <Link to="/" className="flex items-center gap-3">
            <img src="/logo.png" alt="HarborAI Logo" className="h-11 w-11 object-contain" />
            <span className="font-display text-xl tracking-tight">HarborAI</span>
          </Link>
          <div className="flex gap-2">
            <Link to="/faq">
              <Button variant="outline" className="rounded-full border-2 border-[#123C5C] font-bold">FAQ</Button>
            </Link>
            <Link to="/contact">
              <Button variant="outline" className="rounded-full border-2 border-[#123C5C] font-bold">Contact</Button>
            </Link>
          </div>
        </div>
      </header>

      <main className="container mx-auto max-w-5xl px-4 py-8 sm:py-12">
        <section className="mb-8 rounded-2xl border border-[#0F9488]/25 bg-card p-6 shadow-sm sm:p-10">
          <div className="flex items-start gap-5">
            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#0F9488]/15 text-[#0F9488]">
              <FileText className="h-7 w-7" />
            </div>
            <div>
              <p className="text-sm font-bold uppercase tracking-[0.18em] text-[#0F9488]">HarborAI</p>
              <h1 className="mt-1 font-display text-3xl tracking-tight sm:text-5xl">Terms and Conditions</h1>
              <p className="mt-3 text-muted-foreground">
                Version {TERMS_VERSION} · Updated October 8, 2026
              </p>
              <p className="mt-4 max-w-3xl leading-7 text-muted-foreground">
                These Terms and Conditions govern the use of HarborAI, the smart trading and enterprise
                platform for agriculture in Aparri. Creating an account or using the platform means you
                agree to these Terms and acknowledge the{' '}
                <Link to="/privacy" className="font-semibold text-[#0F9488] hover:underline">
                  HarborAI Privacy Policy
                </Link>
                . HarborAI records the policy version you accept and the date and time of that
                acceptance.
              </p>
            </div>
          </div>
        </section>

        <nav aria-label="Terms sections" className="mb-8 rounded-2xl border border-border bg-card p-6 shadow-sm">
          <p className="mb-3 text-xs font-black uppercase tracking-[0.18em] text-foreground">
            Contents ({termsSections.length} sections)
          </p>
          <ol className="grid gap-1 text-sm text-muted-foreground sm:grid-cols-2">
            {termsSections.map((section, index) => (
              <li key={section.title} className="flex gap-2">
                <span className="font-semibold text-[#0F9488]">{index + 1}.</span>
                <a href={`#terms-section-${index + 1}`} className="hover:underline">
                  {section.title}
                </a>
              </li>
            ))}
          </ol>
        </nav>

        <div className="space-y-5">
          {termsSections.map((section, index) => (
            <section
              key={section.title}
              id={`terms-section-${index + 1}`}
              className="scroll-mt-24 rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8"
            >
              <h2 className="font-display text-2xl sm:text-3xl">
                {index + 1}. {section.title}
              </h2>
              <div className="mt-3 space-y-3 leading-7 text-muted-foreground">
                {section.paragraphs.map((paragraph) => (
                  <p key={paragraph}>{paragraph}</p>
                ))}
              </div>
            </section>
          ))}
        </div>

        <section className="mt-8 rounded-2xl border border-[#0F9488]/30 bg-[#0F9488]/5 p-6 sm:p-8">
          <h2 className="font-display text-2xl sm:text-3xl">Need help with these Terms?</h2>
          <p className="mt-3 leading-7 text-muted-foreground">
            If you have a question about these Terms, about your account, or about how HarborAI uses
            your information, contact the HarborAI operator through the project contact channel.
          </p>
          <Link to="/contact" className="mt-4 inline-block">
            <Button className="rounded-full bg-[#0F9488] font-bold text-white hover:bg-[#0B7F74]">
              Contact HarborAI
            </Button>
          </Link>
        </section>

        <div className="mt-8 border-t border-border pt-6">
          <Link to="/register">
            <Button
              variant="outline"
              className="w-full rounded-full border-2 border-[#123C5C] font-bold text-foreground hover:bg-[#123C5C]/5 sm:w-auto"
            >
              <ArrowLeft className="mr-2 h-4 w-4" />
              Back to Registration
            </Button>
          </Link>
        </div>
      </main>
    </div>
  );
}